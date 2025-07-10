<?php

namespace App\Services\V1\Reviews;

use App\Models\Review;
use App\Models\Product;
use App\Models\ReviewReport;
use App\Models\ReviewHelpfulness;
use App\Models\ReviewMedia;
use App\Resources\V1\ReviewResource;
use App\Services\V1\Media\SecureMedia;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReviewService
{
    use ApiResponses;

    protected SecureMedia $mediaService;

    public function __construct(SecureMedia $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function getProductReviews($request, Product $product)
    {
        try {
            $perPage = min($request->input('per_page', 15), 50);
            $sortBy = $request->input('sort_by', 'newest');
            $rating = $request->input('rating', []);
            $verifiedOnly = $request->boolean('verified_only', false);
            $withMedia = $request->boolean('with_media', false);

            $query = Review::with([
                'user:id,name,email',
                'media',
                'response.vendor:id,name'
            ])
                ->where('product_id', $product->id)
                ->where('is_approved', true);

            // Apply filters
            if (!empty($rating)) {
                $query->whereIn('rating', $rating);
            }

            if ($verifiedOnly) {
                $query->where('is_verified_purchase', true);
            }

            if ($withMedia) {
                $query->whereHas('media');
            }

            // Apply sorting
            $query = $this->applySorting($query, $sortBy);

            $reviews = $query->paginate($perPage);

            // Load user vote status if authenticated
            $user = $request->user();
            if ($user) {
                $this->loadUserVoteStatus($reviews->getCollection(), $user->id);
            }

            // Get product review statistics
            $reviewStats = $this->getProductReviewStats($product);

            $responseData = $reviews->toArray();
            $responseData['meta'] = array_merge($responseData['meta'] ?? [], [
                'product_id' => $product->id,
                'total_reviews' => $reviewStats['total_reviews'],
                'average_rating' => $reviewStats['average_rating'],
                'rating_breakdown' => $reviewStats['rating_breakdown'],
                'verified_purchase_count' => $reviewStats['verified_purchase_count'],
                'with_media_count' => $reviewStats['with_media_count'],
            ]);

            return $this->ok('Product reviews retrieved successfully.', $responseData);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve product reviews', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to retrieve reviews.', 500);
        }
    }

    public function getReview(Request $request, Review $review)
    {
        try {
            // Only show approved reviews to public
            if (!$review->is_approved) {
                throw new \Exception('Review not found.', 404);
            }

            $review->load([
                'user:id,name,email',
                'product:id,name,price',
                'media',
                'response.vendor:id,name'
            ]);

            // Load user vote status if authenticated
            $user = $request->user();
            if ($user) {
                $this->loadUserVoteStatus(collect([$review]), $user->id);
            }

            return $this->ok('Review retrieved successfully.', new ReviewResource($review));

        } catch (\Exception $e) {
            Log::error('Failed to retrieve review', [
                'review_id' => $review->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function createReview($request)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            return DB::transaction(function () use ($data, $user, $request) {
                // Create the review
                $review = Review::create([
                    'user_id' => $user->id,
                    'product_id' => $data['product_id'],
                    'order_item_id' => $data['order_item_id'] ?? null,
                    'rating' => $data['rating'],
                    'title' => $data['title'] ?? null,
                    'content' => $data['content'],
                    'is_verified_purchase' => !empty($data['order_item_id']),
                    'is_approved' => true, // Auto-approve for now
                    'approved_at' => now(),
                ]);

                // Handle media uploads
                if ($request->hasFile('media')) {
                    $this->handleReviewMediaUpload($review, $request);
                }

                // Update product review statistics
                $product = $review->product;
                $product->recalculateReviewStats();

                // Load relationships for response
                $review->load([
                    'user:id,name',
                    'product:id,name,price',
                    'media'
                ]);

                Log::info('Review created successfully', [
                    'review_id' => $review->id,
                    'product_id' => $data['product_id'],
                    'user_id' => $user->id,
                    'rating' => $data['rating'],
                    'verified_purchase' => $review->is_verified_purchase
                ]);

                return $this->ok('Review submitted successfully.', new ReviewResource($review), [], 201);
            });

        } catch (\Exception $e) {
            Log::error('Failed to create review', [
                'product_id' => $data['product_id'] ?? null,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to submit review. Please try again.', 500);
        }
    }

    public function updateReview($request, Review $review)
    {
        try {
            $data = $request->validated();

            return DB::transaction(function () use ($data, $review, $request) {
                // Update review fields
                $updateData = array_intersect_key($data, array_flip([
                    'rating', 'title', 'content'
                ]));

                if (!empty($updateData)) {
                    $review->update($updateData);
                }

                // Handle media removal
                if (!empty($data['remove_media'])) {
                    $mediaToRemove = ReviewMedia::where('review_id', $review->id)
                        ->whereIn('id', $data['remove_media'])
                        ->get();

                    foreach ($mediaToRemove as $media) {
                        Storage::delete($media->media_path);
                        if ($media->thumbnail_url) {
                            $thumbnailPath = str_replace('/storage/', '', parse_url($media->thumbnail_url, PHP_URL_PATH));
                            Storage::delete($thumbnailPath);
                        }
                        $media->delete();
                    }
                }

                // Handle new media uploads
                if ($request->hasFile('media')) {
                    $currentMediaCount = $review->media()->count();
                    $newMediaCount = count($request->file('media'));

                    if ($currentMediaCount + $newMediaCount > 5) {
                        throw new \Exception('Cannot exceed 5 media files per review.', 422);
                    }

                    $this->handleReviewMediaUpload($review, $request);
                }

                // Update product review statistics if rating changed
                if (isset($updateData['rating'])) {
                    $review->product->recalculateReviewStats();
                }

                $review->load(['user:id,name', 'product:id,name', 'media']);

                Log::info('Review updated successfully', [
                    'review_id' => $review->id,
                    'user_id' => $request->user()->id,
                    'changes' => $updateData
                ]);

                return $this->ok('Review updated successfully.', new ReviewResource($review));
            });

        } catch (\Exception $e) {
            Log::error('Failed to update review', [
                'review_id' => $review->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function deleteReview(Request $request, Review $review)
    {
        try {
            $user = $request->user();

            if (!$review->canBeDeletedBy($user)) {
                throw new \Exception('You can only delete your own reviews.', 403);
            }

            return DB::transaction(function () use ($review, $user) {
                $productId = $review->product_id;

                // Delete associated media files
                foreach ($review->media as $media) {
                    Storage::delete($media->media_path);
                    if ($media->thumbnail_url) {
                        $thumbnailPath = str_replace('/storage/', '', parse_url($media->thumbnail_url, PHP_URL_PATH));
                        Storage::delete($thumbnailPath);
                    }
                }

                // Delete the review (cascade will handle related records)
                $review->delete();

                // Update product review statistics
                $product = Product::find($productId);
                if ($product) {
                    $product->recalculateReviewStats();
                }

                Log::info('Review deleted successfully', [
                    'review_id' => $review->id,
                    'product_id' => $productId,
                    'user_id' => $user->id
                ]);

                return $this->ok('Review deleted successfully.');
            });

        } catch (\Exception $e) {
            Log::error('Failed to delete review', [
                'review_id' => $review->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function voteHelpfulness($request, Review $review)
    {
        try {
            $user = $request->user();
            $isHelpful = $request->validated()['is_helpful'];

            // Check if user can vote
            if ($review->user_id === $user->id) {
                throw new \Exception('You cannot vote on your own review.', 403);
            }

            $voteResult = $isHelpful
                ? $review->markAsHelpful($user)
                : $review->markAsNotHelpful($user);

            if (!$voteResult) {
                throw new \Exception('You have already voted on this review with the same rating.', 409);
            }

            // Refresh review to get updated vote counts
            $review->refresh();

            Log::info('Review helpfulness vote recorded', [
                'review_id' => $review->id,
                'user_id' => $user->id,
                'is_helpful' => $isHelpful,
                'new_helpful_votes' => $review->helpful_votes,
                'new_total_votes' => $review->total_votes
            ]);

            return $this->ok('Thank you for your feedback!', [
                'review_id' => $review->id,
                'helpful_votes' => $review->helpful_votes,
                'total_votes' => $review->total_votes,
                'helpfulness_ratio' => $review->getHelpfulnessRatio(),
                'user_vote' => $isHelpful
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to record helpfulness vote', [
                'review_id' => $review->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function reportReview($request, Review $review)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            // Check if user has already reported this review
            $existingReport = ReviewReport::where('review_id', $review->id)
                ->where('reported_by', $user->id)
                ->first();

            if ($existingReport) {
                throw new \Exception('You have already reported this review.', 409);
            }

            $report = ReviewReport::create([
                'review_id' => $review->id,
                'reported_by' => $user->id,
                'reason' => $data['reason'],
                'details' => $data['details'] ?? null,
                'status' => 'pending'
            ]);

            Log::info('Review reported', [
                'report_id' => $report->id,
                'review_id' => $review->id,
                'reported_by' => $user->id,
                'reason' => $data['reason']
            ]);

            return $this->ok('Review reported successfully. Our moderation team will review it shortly.', [
                'report_id' => $report->id,
                'review_id' => $review->id,
                'reason' => $data['reason'],
                'status' => 'pending'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to report review', [
                'review_id' => $review->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Helper Methods
     */

    protected function applySorting($query, string $sortBy)
    {
        switch ($sortBy) {
            case 'oldest':
                return $query->oldest();

            case 'rating_high':
                return $query->orderBy('rating', 'desc')->latest();

            case 'rating_low':
                return $query->orderBy('rating', 'asc')->latest();

            case 'helpful':
                return $query->orderByRaw('(helpful_votes / GREATEST(total_votes, 1)) DESC')
                    ->orderBy('helpful_votes', 'desc')
                    ->latest();

            case 'newest':
            default:
                return $query->latest();
        }
    }

    protected function loadUserVoteStatus($reviews, int $userId): void
    {
        $reviewIds = $reviews->pluck('id')->toArray();

        if (empty($reviewIds)) {
            return;
        }

        $userVotes = ReviewHelpfulness::whereIn('review_id', $reviewIds)
            ->where('user_id', $userId)
            ->get()
            ->keyBy('review_id');

        $reviews->each(function ($review) use ($userVotes, $userId) {
            $vote = $userVotes->get($review->id);
            $review->user_voted = $vote ? $vote->is_helpful : null;

            // Add permission checks using existing auth user
            $authUser = auth()->user();
            if ($authUser) {
                $review->can_edit = $review->canBeEditedBy($authUser);
                $review->can_delete = $review->canBeDeletedBy($authUser);
            } else {
                $review->can_edit = false;
                $review->can_delete = false;
            }
        });
    }

    protected function getProductReviewStats(Product $product): array
    {
        return [
            'total_reviews' => $product->total_reviews,
            'average_rating' => $product->average_rating,
            'rating_breakdown' => $product->rating_breakdown,
            'verified_purchase_count' => $product->reviews()->where('is_verified_purchase', true)->count(),
            'with_media_count' => $product->reviews()->whereHas('media')->count(),
        ];
    }

    protected function handleReviewMediaUpload(Review $review, Request $request): void
    {
        try {
            $mediaFiles = $request->file('media');
            if (!is_array($mediaFiles)) {
                $mediaFiles = [$mediaFiles];
            }

            $sortOrder = $review->media()->max('sort_order') ?? 0;

            foreach ($mediaFiles as $file) {
                if ($file && $file->isValid()) {
                    $sortOrder++;

                    // Store the file
                    $path = $file->store('reviews', 'public');

                    // Create media record
                    ReviewMedia::create([
                        'review_id' => $review->id,
                        'media_path' => $path,
                        'media_type' => str_starts_with($file->getMimeType(), 'image/') ? 'image' : 'video',
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'sort_order' => $sortOrder,
                        'metadata' => [
                            'dimensions' => $this->getImageDimensions($file),
                            'uploaded_at' => now()->toISOString(),
                        ]
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Review media upload failed', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to process media files: ' . $e->getMessage(), 500);
        }
    }

    protected function getImageDimensions($file): ?array
    {
        try {
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $imageSize = getimagesize($file->getPathname());
                if ($imageSize) {
                    return [
                        'width' => $imageSize[0],
                        'height' => $imageSize[1]
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silently fail - dimensions are optional
        }

        return null;
    }
}

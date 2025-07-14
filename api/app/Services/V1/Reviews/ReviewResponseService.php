<?php

namespace App\Services\V1\Reviews;

use App\Models\Review;
use App\Models\ReviewResponse;
use App\Models\Vendor;
use App\Resources\V1\ReviewResponseResource;
use App\Services\V1\Reviews\ReviewNotificationService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewResponseService
{
    use ApiResponses;

    protected ReviewNotificationService $notificationService;

    public function __construct(ReviewNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new vendor response to a review
     */
    public function createResponse($request, Review $review)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            $vendor = Vendor::where('user_id', $user->id)->first();
            if (!$vendor) {
                throw new \Exception('Vendor account not found.', 404);
            }

            if ($review->product->vendor_id !== $vendor->id) {
                throw new \Exception('You can only respond to reviews on your products.', 403);
            }

            $existingResponse = ReviewResponse::where('review_id', $review->id)
                ->where('vendor_id', $vendor->id)
                ->first();

            if ($existingResponse) {
                throw new \Exception('You have already responded to this review.', 409);
            }

            return DB::transaction(function () use ($data, $review, $vendor, $user) {
                $response = ReviewResponse::create([
                    'review_id' => $review->id,
                    'vendor_id' => $vendor->id,
                    'user_id' => $user->id,
                    'content' => $data['content'],
                    'is_approved' => true,
                    'approved_at' => now(),
                ]);

                $response->load([
                    'review.user',
                    'review.product',
                    'vendor',
                    'user'
                ]);

                $this->notificationService->sendReviewResponse($response);

                Log::info('Review response created', [
                    'response_id' => $response->id,
                    'review_id' => $review->id,
                    'vendor_id' => $vendor->id,
                    'user_id' => $user->id
                ]);

                return $this->ok('Response submitted successfully.', new ReviewResponseResource($response), [], 201);
            });

        } catch (\Exception $e) {
            Log::error('Failed to create review response', [
                'review_id' => $review->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateResponse($request, ReviewResponse $response)
    {
        try {
            $user = $request->user();
            $data = $request->validated();

            if (!$response->canBeEditedBy($user)) {
                throw new \Exception('You can only edit your responses within 24 hours of creation.', 403);
            }

            $response->update([
                'content' => $data['content'],
            ]);

            Log::info('Review response updated', [
                'response_id' => $response->id,
                'user_id' => $user->id
            ]);

            return $this->ok('Response updated successfully.', [
                'id' => $response->id,
                'content' => $response->content,
                'updated_at' => $response->updated_at->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update review response', [
                'response_id' => $response->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function deleteResponse(Request $request, ReviewResponse $response)
    {
        try {
            $user = $request->user();

            if (!$response->canBeEditedBy($user)) {
                throw new \Exception('You can only delete your responses within 24 hours of creation.', 403);
            }

            $responseId = $response->id;
            $response->delete();

            Log::info('Review response deleted', [
                'response_id' => $responseId,
                'user_id' => $user->id
            ]);

            return $this->ok('Response deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete review response', [
                'response_id' => $response->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getVendorResponses(Request $request)
    {
        try {
            $user = $request->user();

            $vendor = Vendor::where('user_id', $user->id)->first();
            if (!$vendor) {
                throw new \Exception('Vendor account not found.', 404);
            }

            $perPage = min($request->input('per_page', 15), 50);
            $sortBy = $request->input('sort_by', 'newest');
            $productId = $request->input('product_id');
            $rating = $request->input('rating', []);

            $query = ReviewResponse::with([
                'review.user:id,name',
                'review.product:id,name,vendor_id',
                'vendor:id,name',
                'user:id,name'
            ])
                ->where('vendor_id', $vendor->id);

            if ($productId) {
                $query->whereHas('review.product', function($q) use ($productId) {
                    $q->where('id', $productId);
                });
            }

            if (!empty($rating)) {
                $query->whereHas('review', function($q) use ($rating) {
                    $q->whereIn('rating', $rating);
                });
            }

            $query = $this->applySorting($query, $sortBy);

            $responses = $query->paginate($perPage);

            $responses->getCollection()->each(function ($response) use ($user) {
                $response->can_edit = $response->canBeEditedBy($user);
                $response->can_delete = $response->canBeEditedBy($user);
            });

            return $this->ok('Your responses retrieved successfully.', $responses->toArray());

        } catch (\Exception $e) {
            Log::error('Failed to retrieve vendor responses', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to retrieve responses.', 500);
        }
    }

    public function getResponse(Request $request, ReviewResponse $response)
    {
        try {
            $user = $request->user();

            if ($response->user_id !== $user->id && !$user->hasRole(['super admin', 'admin'])) {
                throw new \Exception('You can only view your own responses.', 403);
            }

            $response->load([
                'review.user:id,name',
                'review.product:id,name',
                'vendor:id,name',
                'user:id,name'
            ]);

            $response->can_edit = $response->canBeEditedBy($user);
            $response->can_delete = $response->canBeEditedBy($user);

            return $this->ok('Response retrieved successfully.', new ReviewResponseResource($response));

        } catch (\Exception $e) {
            Log::error('Failed to retrieve response', [
                'response_id' => $response->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    protected function applySorting($query, string $sortBy)
    {
        switch ($sortBy) {
            case 'oldest':
                return $query->oldest();

            case 'rating_high':
                return $query->join('reviews', 'review_responses.review_id', '=', 'reviews.id')
                    ->orderBy('reviews.rating', 'desc')
                    ->orderBy('review_responses.created_at', 'desc')
                    ->select('review_responses.*');

            case 'rating_low':
                return $query->join('reviews', 'review_responses.review_id', '=', 'reviews.id')
                    ->orderBy('reviews.rating', 'asc')
                    ->orderBy('review_responses.created_at', 'desc')
                    ->select('review_responses.*');

            case 'newest':
            default:
                return $query->latest();
        }
    }

    public function getUnansweredReviews(Request $request)
    {
        try {
            $user = $request->user();

            $vendor = Vendor::where('user_id', $user->id)->first();
            if (!$vendor) {
                throw new \Exception('Vendor account not found.', 404);
            }

            $perPage = min($request->input('per_page', 15), 50);

            $reviews = Review::with([
                'user:id,name,email',
                'product:id,name',
                'media'
            ])
                ->whereHas('product', function($query) use ($vendor) {
                    $query->where('vendor_id', $vendor->id);
                })
                ->where('is_approved', true)
                ->whereDoesntHave('response')
                ->latest()
                ->paginate($perPage);

            return $this->ok('Unanswered reviews retrieved successfully.', $reviews->toArray());

        } catch (\Exception $e) {
            Log::error('Failed to retrieve unanswered reviews', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to retrieve unanswered reviews.', 500);
        }
    }
}

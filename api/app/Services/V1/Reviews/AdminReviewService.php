<?php

namespace App\Services\V1\Reviews;

use App\Models\Review;
use App\Models\Product;
use App\Models\ReviewReport;
use App\Resources\V1\ReviewResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AdminReviewService
{
    use ApiResponses;

    public function __construct()
    {

    }

    public function getAllReviews($request)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_reviews')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $perPage = min($request->input('per_page', 20), 100);
            $sortBy = $request->input('sort_by', 'newest');

            $query = Review::with([
                'user:id,name,email',
                'product:id,name,average_rating,total_reviews',
                'media',
                'response.vendor:id,name',
                'reports' => function($q) {
                    $q->where('status', 'pending');
                }
            ]);

            $this->applyAdminFilters($query, $request);
            $query = $this->applyAdminSorting($query, $sortBy);
            $reviews = $query->paginate($perPage);
            $stats = $this->getReviewStatistics();

            $responseData = $reviews->toArray();

            $responseData['meta'] = array_merge($responseData['meta'] ?? [], [
                'stats' => $stats
            ]);

            return $this->ok('Reviews retrieved successfully.', $responseData);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve admin reviews', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'filters' => $request->all()
            ]);

            throw new \Exception('Failed to retrieve reviews.', 500);
        }
    }

    public function getReviewDetails($request, Review $review)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_reviews')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $review->load([
                'user:id,name,email',
                'product:id,name,average_rating,total_reviews',
                'media',
                'response.vendor:id,name',
                'reports.reporter:id,name,email',
                'helpfulnessVotes.user:id,name'
            ]);

            $userStats = $this->getUserReviewStats($review->user_id);
            $moderationHistory = $this->getModerationHistory($review->id);

            $reviewData = new ReviewResource($review);
            $reviewArray = $reviewData->toArray($request);

            $reviewArray['user']['total_reviews'] = $userStats['total_reviews'];
            $reviewArray['user']['average_rating'] = $userStats['average_rating'];
            $reviewArray['user']['verification_rate'] = $userStats['verification_rate'];

            $reviewArray['reports'] = $review->reports->map(function($report) {
                return [
                    'id' => $report->id,
                    'reason' => $report->reason,
                    'reason_label' => $report->reason_label,
                    'details' => $report->details,
                    'status' => $report->status,
                    'reported_by' => [
                        'id' => $report->reporter->id,
                        'name' => $report->reporter->name,
                        'email' => $report->reporter->email,
                    ],
                    'created_at' => $report->created_at->toISOString(),
                ];
            });

            $reviewArray['moderation_history'] = $moderationHistory;
            $reviewArray['helpfulness_votes'] = $review->helpfulnessVotes->map(function($vote) {
                return [
                    'user' => [
                        'id' => $vote->user->id,
                        'name' => $vote->user->name,
                    ],
                    'is_helpful' => $vote->is_helpful,
                    'created_at' => $vote->created_at->toISOString(),
                ];
            });

            return $this->ok('Review details retrieved successfully.', $reviewArray);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve review details', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            throw new \Exception('Failed to retrieve review details.', 500);
        }
    }

    public function moderateReview($request, Review $review)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_reviews')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();
            $action = $data['action'];
            $reason = $data['reason'] ?? null;

            return DB::transaction(function () use ($review, $action, $reason, $user) {
                $originalStatus = [
                    'is_approved' => $review->is_approved,
                    'is_featured' => $review->is_featured,
                ];

                switch ($action) {
                    case 'approve':
                        $review->update([
                            'is_approved' => true,
                            'approved_at' => now(),
                        ]);
                        $message = 'Review approved successfully.';
                        break;

                    case 'reject':
                        $review->update([
                            'is_approved' => false,
                            'approved_at' => null,
                        ]);
                        $message = 'Review rejected successfully.';
                        break;

                    case 'feature':
                        $review->update([
                            'is_featured' => true,
                            'is_approved' => true,
                            'approved_at' => $review->approved_at ?? now(),
                        ]);
                        $message = 'Review featured successfully.';
                        break;

                    case 'unfeature':
                        $review->update(['is_featured' => false]);
                        $message = 'Review unfeatured successfully.';
                        break;

                    default:
                        throw new \Exception('Invalid moderation action.', 422);
                }

                $this->logModerationAction($review, $action, $user, $reason, $originalStatus);

                if ($originalStatus['is_approved'] !== $review->is_approved) {
                    $review->product->recalculateReviewStats();
                }

                Log::info('Review moderated', [
                    'review_id' => $review->id,
                    'action' => $action,
                    'moderator_id' => $user->id,
                    'reason' => $reason
                ]);

                return $this->ok($message, [
                    'id' => $review->id,
                    'action' => $action,
                    'is_approved' => $review->is_approved,
                    'is_featured' => $review->is_featured,
                    'moderated_by' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    'moderated_at' => now()->toISOString(),
                    'reason' => $reason,
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Failed to moderate review', [
                'review_id' => $review->id,
                'action' => $request->input('action'),
                'moderator_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getReportedReviews($request)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_reviews')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $perPage = min($request->input('per_page', 20), 100);
            $status = $request->input('status', 'pending');
            $reason = $request->input('reason');
            $sortBy = $request->input('sort_by', 'priority');

            $query = Review::with([
                'user:id,name,email',
                'product:id,name',
                'reports' => function($q) use ($status, $reason) {
                    if ($status) {
                        $q->where('status', $status);
                    }
                    if ($reason) {
                        $q->where('reason', $reason);
                    }
                    $q->with('reporter:id,name,email');
                },
                'media'
            ])
                ->whereHas('reports', function($q) use ($status, $reason) {
                    if ($status) {
                        $q->where('status', $status);
                    }
                    if ($reason) {
                        $q->where('reason', $reason);
                    }
                })
                ->withCount(['reports as reports_count' => function($q) use ($status) {
                    if ($status) {
                        $q->where('status', $status);
                    }
                }]);

            switch ($sortBy) {
                case 'reports_count':
                    $query->orderBy('reports_count', 'desc');
                    break;
                case 'priority':
                    $query->orderByRaw('reports_count DESC, created_at ASC');
                    break;
                case 'oldest':
                    $query->oldest();
                    break;
                case 'newest':
                default:
                    $query->latest();
                    break;
            }

            $reviews = $query->paginate($perPage);

            $transformedData = $reviews->getCollection()->map(function($review) {
                return [
                    'review' => [
                        'id' => $review->id,
                        'rating' => $review->rating,
                        'title' => $review->title,
                        'content' => substr($review->content, 0, 200) . '...',
                        'is_approved' => $review->is_approved,
                        'user' => [
                            'id' => $review->user->id,
                            'name' => $review->user->name,
                            'email' => $review->user->email,
                        ],
                        'product' => [
                            'id' => $review->product->id,
                            'name' => $review->product->name,
                        ],
                        'created_at' => $review->created_at->toISOString(),
                    ],
                    'reports' => $review->reports->map(function($report) {
                        return [
                            'id' => $report->id,
                            'reason' => $report->reason,
                            'reason_label' => $report->reason_label,
                            'details' => $report->details,
                            'status' => $report->status,
                            'priority' => $this->calculateReportPriority($report),
                            'reported_by' => [
                                'id' => $report->reporter->id,
                                'name' => $report->reporter->name,
                                'email' => $report->reporter->email,
                            ],
                            'created_at' => $report->created_at->toISOString(),
                        ];
                    }),
                    'reports_count' => $review->reports_count,
                    'last_reported_at' => $review->reports->max('created_at')?->toISOString(),
                ];
            });

            $reviews->setCollection($transformedData);

            return $this->ok('Reported reviews retrieved successfully.', $reviews->toArray());

        } catch (\Exception $e) {
            Log::error('Failed to retrieve reported reviews', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'filters' => $request->all()
            ]);

            throw new \Exception('Failed to retrieve reported reviews.', 500);
        }
    }

    public function handleReport($request, ReviewReport $report)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_reviews')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        $request->validate([
            'action' => 'required|string|in:resolve,dismiss',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $action = $request->input('action');
            $notes = $request->input('notes');

            $report->markAsReviewed($user, $action === 'resolve' ? 'resolved' : 'dismissed', $notes);

            Log::info('Review report handled', [
                'report_id' => $report->id,
                'review_id' => $report->review_id,
                'action' => $action,
                'admin_id' => $user->id,
                'notes' => $notes
            ]);

            $message = $action === 'resolve'
                ? 'Review report resolved successfully.'
                : 'Review report dismissed successfully.';

            return $this->ok($message, [
                'report_id' => $report->id,
                'review_id' => $report->review_id,
                'action' => $action,
                'status' => $report->status,
                'admin_notes' => $report->admin_notes,
                'reviewed_by' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
                'reviewed_at' => $report->reviewed_at->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle review report', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            throw new \Exception('Failed to handle report.', 500);
        }
    }

    public function bulkModerate($request)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_reviews')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        $request->validate([
            'review_ids' => 'required|array|min:1|max:100',
            'review_ids.*' => 'required|integer|exists:reviews,id',
            'action' => 'required|string|in:approve,reject,feature,unfeature',
            'reason' => 'nullable|string|max:500|required_if:action,reject',
        ]);

        try {
            $reviewIds = $request->input('review_ids');
            $action = $request->input('action');
            $reason = $request->input('reason');

            $results = [];
            $successful = 0;
            $failed = 0;

            return DB::transaction(function () use ($reviewIds, $action, $reason, $user, &$results, &$successful, &$failed) {
                $reviews = Review::whereIn('id', $reviewIds)->get();

                foreach ($reviews as $review) {
                    try {
                        $originalStatus = [
                            'is_approved' => $review->is_approved,
                            'is_featured' => $review->is_featured,
                        ];

                        switch ($action) {
                            case 'approve':
                                $review->update([
                                    'is_approved' => true,
                                    'approved_at' => now(),
                                ]);
                                break;

                            case 'reject':
                                $review->update([
                                    'is_approved' => false,
                                    'approved_at' => null,
                                ]);
                                break;

                            case 'feature':
                                $review->update([
                                    'is_featured' => true,
                                    'is_approved' => true,
                                    'approved_at' => $review->approved_at ?? now(),
                                ]);
                                break;

                            case 'unfeature':
                                $review->update(['is_featured' => false]);
                                break;
                        }

                        $this->logModerationAction($review, $action, $user, $reason, $originalStatus);

                        if ($originalStatus['is_approved'] !== $review->is_approved) {
                            $review->product->recalculateReviewStats();
                        }

                        $results[] = [
                            'review_id' => $review->id,
                            'status' => 'success',
                            'message' => ucfirst($action) . 'd successfully',
                        ];
                        $successful++;

                    } catch (\Exception $e) {
                        $results[] = [
                            'review_id' => $review->id,
                            'status' => 'failed',
                            'message' => $e->getMessage(),
                        ];
                        $failed++;
                    }
                }

                Log::info('Bulk review moderation completed', [
                    'action' => $action,
                    'total_reviews' => count($reviewIds),
                    'successful' => $successful,
                    'failed' => $failed,
                    'moderator_id' => $user->id
                ]);

                return $this->ok('Bulk moderation completed successfully.', [
                    'action' => $action,
                    'total_reviews' => count($reviewIds),
                    'successful' => $successful,
                    'failed' => $failed,
                    'results' => $results,
                    'moderated_by' => [
                        'id' => $user->id,
                        'name' => $user->name,
                    ],
                    'moderated_at' => now()->toISOString(),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Failed to bulk moderate reviews', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'review_ids' => $request->input('review_ids', [])
            ]);

            throw new \Exception('Failed to perform bulk moderation.', 500);
        }
    }

    public function getAnalytics($request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_analytics')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $period = $request->input('period', 'month');
            $productId = $request->input('product_id');
            $includeTrends = $request->boolean('include_trends', false);

            $dateRange = $this->getDateRange($period);

            $analytics = [
                'summary' => $this->getReviewStatistics($productId),
                'period_stats' => $this->getPeriodStatistics($dateRange, $productId),
                'rating_distribution' => $this->getRatingDistribution($productId),
                'top_products' => $this->getTopReviewedProducts(10),
                'moderation_stats' => $this->getModerationStatistics($dateRange),
            ];

            if ($includeTrends) {
                $analytics['trends'] = $this->getReviewTrends($period, $productId);
            }

            return $this->ok('Review analytics retrieved successfully.', $analytics);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve review analytics', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'period' => $request->input('period'),
                'product_id' => $request->input('product_id')
            ]);

            throw new \Exception('Failed to retrieve analytics.', 500);
        }
    }

    public function deleteReview($request, Review $review)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_reviews')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            return DB::transaction(function () use ($review, $user) {
                $productId = $review->product_id;

                foreach ($review->media as $media) {
                    Storage::delete($media->media_path);
                    if ($media->thumbnail_url) {
                        $thumbnailPath = str_replace('/storage/', '', parse_url($media->thumbnail_url, PHP_URL_PATH));
                        Storage::delete($thumbnailPath);
                    }
                }

                Log::info('Review permanently deleted by admin', [
                    'review_id' => $review->id,
                    'product_id' => $productId,
                    'deleted_by' => $user->id,
                    'review_user_id' => $review->user_id,
                    'review_rating' => $review->rating
                ]);

                $review->delete();

                $product = Product::find($productId);
                if ($product) {
                    $product->recalculateReviewStats();
                }

                return $this->ok('Review deleted permanently.');
            });

        } catch (\Exception $e) {
            Log::error('Failed to delete review', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            throw new \Exception('Failed to delete review.', 500);
        }
    }

    protected function applyAdminFilters($query, $request): void
    {
        if ($productId = $request->input('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($rating = $request->input('rating')) {
            $query->whereIn('rating', (array) $rating);
        }

        if ($status = $request->input('status')) {
            switch ($status) {
                case 'approved':
                    $query->where('is_approved', true);
                    break;
                case 'pending':
                    $query->where('is_approved', false);
                    break;
                case 'rejected':
                    $query->where('is_approved', false)->whereNotNull('approved_at');
                    break;
            }
        }

        if ($request->boolean('is_featured')) {
            $query->where('is_featured', true);
        }

        if ($request->boolean('is_verified')) {
            $query->where('is_verified_purchase', true);
        }

        if ($request->boolean('has_reports')) {
            $query->whereHas('reports');
        }
    }

    protected function applyAdminSorting($query, string $sortBy)
    {
        switch ($sortBy) {
            case 'oldest':
                return $query->oldest();
            case 'rating_high':
                return $query->orderBy('rating', 'desc')->latest();
            case 'rating_low':
                return $query->orderBy('rating', 'asc')->latest();
            case 'reports_count':
                return $query->withCount('reports')->orderBy('reports_count', 'desc');
            case 'newest':
            default:
                return $query->latest();
        }
    }

    protected function getReviewStatistics($productId = null): array
    {
        $query = Review::query();

        if ($productId) {
            $query->where('product_id', $productId);
        }

        return [
            'total_reviews' => $query->count(),
            'pending_approval' => $query->where('is_approved', false)->count(),
            'reported_reviews' => $query->whereHas('reports', function($q) {
                $q->where('status', 'pending');
            })->count(),
            'featured_reviews' => $query->where('is_featured', true)->count(),
            'average_rating' => round($query->avg('rating'), 2),
            'verification_rate' => round($query->where('is_verified_purchase', true)->count() / max($query->count(), 1) * 100, 1),
        ];
    }

    protected function getPeriodStatistics($dateRange, $productId = null): array
    {
        $query = Review::whereBetween('created_at', $dateRange);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $reportQuery = ReviewReport::whereBetween('created_at', $dateRange);

        return [
            'new_reviews' => $query->count(),
            'approved_reviews' => $query->where('is_approved', true)->count(),
            'rejected_reviews' => $query->where('is_approved', false)->whereNotNull('approved_at')->count(),
            'reports_received' => $reportQuery->count(),
            'reports_resolved' => $reportQuery->whereIn('status', ['resolved', 'dismissed'])->count(),
        ];
    }

    protected function getRatingDistribution($productId = null): array
    {
        $query = Review::where('is_approved', true);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        $distribution = $query->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        return array_merge([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], $distribution);
    }

    protected function getTopReviewedProducts(int $limit = 10): array
    {
        return Product::withCount('reviews')
            ->with('reviews:product_id,rating')
            ->having('reviews_count', '>', 0)
            ->orderBy('reviews_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($product) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'review_count' => $product->reviews_count,
                    'average_rating' => round($product->reviews->avg('rating'), 2),
                ];
            })
            ->toArray();
    }

    protected function getModerationStatistics($dateRange): array
    {
        $totalReviews = Review::whereBetween('created_at', $dateRange)->count();
        $approvedReviews = Review::whereBetween('created_at', $dateRange)
            ->where('is_approved', true)->count();

        return [
            'approval_rate' => $totalReviews > 0 ? round($approvedReviews / $totalReviews * 100, 1) : 0,
            'average_response_time_hours' => $this->getAverageResponseTime($dateRange),
            'moderator_activity' => $this->getModeratorActivity($dateRange),
        ];
    }

    protected function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match($period) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            'week' => [$now->startOfWeek(), $now->endOfWeek()],
            'month' => [$now->startOfMonth(), $now->endOfMonth()],
            'quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'year' => [$now->startOfYear(), $now->endOfYear()],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
    }

    protected function getUserReviewStats(int $userId): array
    {
        $userReviews = Review::where('user_id', $userId)->where('is_approved', true);

        return [
            'total_reviews' => $userReviews->count(),
            'average_rating' => round($userReviews->avg('rating'), 2),
            'verification_rate' => round($userReviews->where('is_verified_purchase', true)->count() / max($userReviews->count(), 1) * 100, 1),
        ];
    }

    protected function getModerationHistory(int $reviewId): array
    {
        return [];
    }

    protected function logModerationAction($review, $action, $user, $reason, $originalStatus): void
    {
        Log::info('Review moderation action logged', [
            'review_id' => $review->id,
            'action' => $action,
            'moderator_id' => $user->id,
            'moderator_name' => $user->name,
            'reason' => $reason,
            'original_status' => $originalStatus,
            'new_status' => [
                'is_approved' => $review->is_approved,
                'is_featured' => $review->is_featured,
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    protected function calculateReportPriority($report): string
    {
        $highPriorityReasons = ['spam', 'inappropriate_language'];

        if (in_array($report->reason, $highPriorityReasons)) {
            return 'high';
        }

        return 'medium';
    }

    protected function getAverageResponseTime($dateRange): float
    {
        return 4.7; // Mock value
    }

    protected function getModeratorActivity($dateRange): array
    {
        return []; // Mock empty array
    }

    protected function getReviewTrends(string $period, $productId = null): array
    {
        return []; // Mock empty array for now
    }
}

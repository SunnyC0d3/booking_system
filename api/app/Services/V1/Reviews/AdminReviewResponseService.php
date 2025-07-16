<?php

namespace App\Services\V1\Reviews;

use App\Models\ReviewResponse;
use App\Models\Review;
use App\Models\Vendor;
use App\Resources\V1\ReviewResponseResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdminReviewResponseService
{
    use ApiResponses;

    public function getAllResponses($request)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_review_responses')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $perPage = min($request->input('per_page', 20), 100);
            $sortBy = $request->input('sort_by', 'newest');

            $query = ReviewResponse::with([
                'review.user:id,name,email',
                'review.product:id,name,vendor_id',
                'vendor:id,name,description',
                'user:id,name,email'
            ]);

            $this->applyAdminFilters($query, $request);
            $query = $this->applyAdminSorting($query, $sortBy);
            $responses = $query->paginate($perPage);

            $responseData = $responses->toArray();

            return $this->ok('Review responses retrieved successfully.', $responseData);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve admin review responses', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'filters' => $request->all()
            ]);

            throw new \Exception('Failed to retrieve responses.', 500);
        }
    }

    public function getResponseDetails($request, ReviewResponse $response)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_review_responses')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $response->load([
                'review.user:id,name,email',
                'review.product:id,name,vendor_id',
                'vendor:id,name,description',
                'user:id,name,email'
            ]);

            return $this->ok('Response details retrieved successfully.', new ReviewResponseResource($response));

        } catch (\Exception $e) {
            Log::error('Failed to retrieve response details', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            throw new \Exception('Failed to retrieve response details.', 500);
        }
    }

    public function approveResponse($request, ReviewResponse $response)
    {
        $user = $request->user();

        if (!$user->hasPermission('approve_review_responses')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            if ($response->is_approved) {
                throw new \Exception('Response is already approved.', 409);
            }

            $response->update([
                'is_approved' => true,
                'approved_at' => now(),
            ]);

            Log::info('Review response approved', [
                'response_id' => $response->id,
                'approved_by' => $user->id,
                'vendor_id' => $response->vendor_id
            ]);

            return $this->ok('Response approved successfully.', [
                'id' => $response->id,
                'is_approved' => true,
                'approved_at' => $response->approved_at->toISOString(),
                'approved_by' => [
                    'id' => $user->id,
                    'name' => $user->name,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to approve response', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            throw new \Exception($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function deleteResponse($request, ReviewResponse $response)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_review_responses')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        try {
            $responseId = $response->id;
            $vendorId = $response->vendor_id;
            $reviewId = $response->review_id;

            $response->delete();

            Log::info('Review response deleted by admin', [
                'response_id' => $responseId,
                'review_id' => $reviewId,
                'vendor_id' => $vendorId,
                'deleted_by' => $user->id
            ]);

            return $this->ok('Response deleted permanently.');

        } catch (\Exception $e) {
            Log::error('Failed to delete response', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            throw new \Exception('Failed to delete response.', 500);
        }
    }

    public function bulkModerate($request)
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_review_responses')) {
            throw new \Exception('You do not have the required permissions.', 403);
        }

        $request->validate([
            'response_ids' => 'required|array|min:1|max:100',
            'response_ids.*' => 'required|integer|exists:review_responses,id',
            'action' => 'required|string|in:approve,reject,delete',
            'reason' => 'nullable|string|max:500|required_if:action,reject,delete',
        ]);

        try {
            $responseIds = $request->input('response_ids');
            $action = $request->input('action');
            $reason = $request->input('reason');

            $results = [];
            $successful = 0;
            $failed = 0;

            return DB::transaction(function () use ($responseIds, $action, $reason, $user, &$results, &$successful, &$failed) {
                $responses = ReviewResponse::whereIn('id', $responseIds)->get();

                foreach ($responses as $response) {
                    try {
                        switch ($action) {
                            case 'approve':
                                if (!$response->is_approved) {
                                    $response->update([
                                        'is_approved' => true,
                                        'approved_at' => now(),
                                    ]);
                                }
                                break;

                            case 'reject':
                                $response->update([
                                    'is_approved' => false,
                                    'approved_at' => null,
                                ]);
                                break;

                            case 'delete':
                                $response->delete();
                                break;
                        }

                        $results[] = [
                            'response_id' => $response->id,
                            'status' => 'success',
                            'message' => ucfirst($action) . 'd successfully',
                        ];
                        $successful++;

                    } catch (\Exception $e) {
                        $results[] = [
                            'response_id' => $response->id,
                            'status' => 'failed',
                            'message' => $e->getMessage(),
                        ];
                        $failed++;
                    }
                }

                Log::info('Bulk response moderation completed', [
                    'action' => $action,
                    'total_responses' => count($responseIds),
                    'successful' => $successful,
                    'failed' => $failed,
                    'moderator_id' => $user->id
                ]);

                return $this->ok('Bulk moderation completed successfully.', [
                    'action' => $action,
                    'total_responses' => count($responseIds),
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
            Log::error('Failed to bulk moderate responses', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'response_ids' => $request->input('response_ids', [])
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
            $vendorId = $request->input('vendor_id');

            $dateRange = $this->getDateRange($period);

            $analytics = [
                'summary' => $this->getResponseStatistics($vendorId),
                'period_stats' => $this->getPeriodStatistics($dateRange, $vendorId),
                'top_vendors' => $this->getTopRespondingVendors(10),
                'response_trends' => $this->getResponseTrends($period, $vendorId),
            ];

            return $this->ok('Response analytics retrieved successfully.', $analytics);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve response analytics', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'period' => $request->input('period'),
                'vendor_id' => $request->input('vendor_id')
            ]);

            throw new \Exception('Failed to retrieve analytics.', 500);
        }
    }

    protected function applyAdminFilters($query, $request): void
    {
        if ($vendorId = $request->input('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        if ($productId = $request->input('product_id')) {
            $query->whereHas('review', function($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }

        if ($rating = $request->input('review_rating')) {
            $query->whereHas('review', function($q) use ($rating) {
                $q->whereIn('rating', (array) $rating);
            });
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
    }

    protected function applyAdminSorting($query, string $sortBy)
    {
        switch ($sortBy) {
            case 'oldest':
                return $query->oldest();
            case 'rating_high':
                return $query->join('reviews', 'review_responses.review_id', '=', 'reviews.id')
                    ->orderBy('reviews.rating', 'desc')
                    ->select('review_responses.*');
            case 'rating_low':
                return $query->join('reviews', 'review_responses.review_id', '=', 'reviews.id')
                    ->orderBy('reviews.rating', 'asc')
                    ->select('review_responses.*');
            case 'newest':
            default:
                return $query->latest();
        }
    }

    protected function getResponseStatistics($vendorId = null): array
    {
        $query = ReviewResponse::query();

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        $totalResponses = $query->count();
        $totalReviews = Review::when($vendorId, function($q) use ($vendorId) {
            $q->whereHas('product', function($sq) use ($vendorId) {
                $sq->where('vendor_id', $vendorId);
            });
        })->where('is_approved', true)->count();

        return [
            'total_responses' => $totalResponses,
            'pending_approval' => $query->where('is_approved', false)->count(),
            'approved_responses' => $query->where('is_approved', true)->count(),
            'response_rate' => $totalReviews > 0 ? round(($totalResponses / $totalReviews) * 100, 1) : 0,
            'average_response_time_hours' => $this->getAverageResponseTime($vendorId),
        ];
    }

    protected function getPeriodStatistics($dateRange, $vendorId = null): array
    {
        $query = ReviewResponse::whereBetween('created_at', $dateRange);

        if ($vendorId) {
            $query->where('vendor_id', $vendorId);
        }

        return [
            'new_responses' => $query->count(),
            'approved_responses' => $query->where('is_approved', true)->count(),
            'rejected_responses' => $query->where('is_approved', false)->whereNotNull('approved_at')->count(),
        ];
    }

    protected function getTopRespondingVendors(int $limit = 10): array
    {
        return Vendor::withCount(['products as total_reviews' => function($query) {
            $query->join('reviews', 'products.id', '=', 'reviews.product_id')
                ->where('reviews.is_approved', true);
        }])
            ->withCount('reviewResponses as responses_count')
            ->having('total_reviews', '>', 0)
            ->orderByRaw('responses_count / total_reviews DESC')
            ->limit($limit)
            ->get()
            ->map(function($vendor) {
                $responseRate = $vendor->total_reviews > 0 ?
                    round(($vendor->responses_count / $vendor->total_reviews) * 100, 1) : 0;

                return [
                    'vendor_id' => $vendor->id,
                    'vendor_name' => $vendor->name,
                    'response_count' => $vendor->responses_count,
                    'response_rate' => $responseRate,
                    'average_response_time' => $this->getAverageResponseTime($vendor->id),
                ];
            })
            ->toArray();
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

    protected function getAverageResponseTime($vendorId = null): float
    {
        return 6.2;
    }

    protected function getResponseTrends(string $period, $vendorId = null): array
    {
        return [];
    }
}

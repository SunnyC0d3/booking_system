<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Services\V1\Reviews\AdminReviewService;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\AdminReviewModerationRequest;
use App\Requests\V1\FilterReviewsRequest;
use Illuminate\Http\Request;
use Exception;

class ReviewController extends Controller
{
    use ApiResponses;

    private AdminReviewService $adminReviewService;

    public function __construct(AdminReviewService $adminReviewService)
    {
        $this->adminReviewService = $adminReviewService;
    }

    /**
     * Get all reviews with admin filtering capabilities
     *
     * Retrieve all reviews in the system with advanced admin filtering and sorting.
     * Provides comprehensive review management including pending approvals, reported reviews,
     * and detailed analytics for administrative oversight.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @queryParam product_id integer optional Filter by specific product ID. Example: 15
     * @queryParam user_id integer optional Filter by specific user ID. Example: 45
     * @queryParam rating array optional Filter by specific ratings (1-5). Example: [1,2]
     * @queryParam status string optional Filter by approval status. Options: approved, pending, rejected. Example: pending
     * @queryParam is_featured boolean optional Filter by featured status. Example: true
     * @queryParam is_verified boolean optional Filter by verified purchase status. Example: true
     * @queryParam has_reports boolean optional Filter reviews that have been reported. Example: true
     * @queryParam sort_by string optional Sort reviews. Options: newest, oldest, rating_high, rating_low, reports_count. Example: reports_count
     * @queryParam per_page integer optional Number of reviews per page (1-100). Default: 20. Example: 50
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     *
     * @response 200 scenario="Reviews retrieved successfully" {
     *   "message": "Reviews retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 123,
     *         "user": {
     *           "id": 45,
     *           "name": "Sarah Johnson",
     *           "email": "sarah.johnson@example.com"
     *         },
     *         "product": {
     *           "id": 15,
     *           "name": "Premium Wireless Earbuds"
     *         },
     *         "rating": 5,
     *         "title": "Excellent sound quality!",
     *         "content": "These earbuds exceeded my expectations...",
     *         "is_verified_purchase": true,
     *         "is_featured": false,
     *         "is_approved": true,
     *         "helpful_votes": 12,
     *         "total_votes": 15,
     *         "reports_count": 0,
     *         "created_at": "2025-01-10T14:30:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 20,
     *     "total": 1543,
     *     "last_page": 78
     *   },
     *   "meta": {
     *     "stats": {
     *       "total_reviews": 1543,
     *       "pending_approval": 23,
     *       "reported_reviews": 8,
     *       "featured_reviews": 45,
     *       "average_rating": 4.2
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function index(FilterReviewsRequest $request)
    {
        try {
            return $this->adminReviewService->getAllReviews($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get detailed review information for admin
     *
     * Retrieve comprehensive admin view of a specific review including all reports,
     * moderation history, user details, and administrative actions available.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to retrieve. Example: 123
     *
     * @response 200 scenario="Review retrieved successfully" {
     *   "message": "Review details retrieved successfully.",
     *   "data": {
     *     "id": 123,
     *     "user": {
     *       "id": 45,
     *       "name": "Sarah Johnson",
     *       "email": "sarah.johnson@example.com",
     *       "total_reviews": 8,
     *       "average_rating": 4.3
     *     },
     *     "product": {
     *       "id": 15,
     *       "name": "Premium Wireless Earbuds",
     *       "average_rating": 4.5,
     *       "total_reviews": 127
     *     },
     *     "rating": 5,
     *     "title": "Excellent sound quality!",
     *     "content": "These earbuds exceeded my expectations...",
     *     "is_verified_purchase": true,
     *     "is_featured": false,
     *     "is_approved": true,
     *     "helpful_votes": 12,
     *     "total_votes": 15,
     *     "reports": [
     *       {
     *         "id": 67,
     *         "reason": "spam",
     *         "details": "Contains promotional links",
     *         "status": "pending",
     *         "reported_by": {
     *           "id": 89,
     *           "name": "John Doe"
     *         },
     *         "created_at": "2025-01-12T10:30:00.000000Z"
     *       }
     *     ],
     *     "moderation_history": [
     *       {
     *         "action": "featured",
     *         "moderator": "Admin User",
     *         "timestamp": "2025-01-11T15:45:00.000000Z",
     *         "reason": "High quality review with helpful details"
     *       }
     *     ],
     *     "created_at": "2025-01-10T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 404 scenario="Review not found" {
     *   "message": "No query results for model [App\\Models\\Review] 999"
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function show(Request $request, Review $review)
    {
        try {
            return $this->adminReviewService->getReviewDetails($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Moderate a review (approve, reject, feature, unfeature)
     *
     * Perform moderation actions on reviews including approval, rejection, featuring,
     * and unfeaturing. All actions are logged for audit trail and accountability.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to moderate. Example: 123
     *
     * @bodyParam action string required Moderation action to perform. Options: approve, reject, feature, unfeature. Example: approve
     * @bodyParam reason string optional Reason for the action (required for reject). Example: "Contains inappropriate language"
     *
     * @response 200 scenario="Review moderated successfully" {
     *   "message": "Review approved successfully.",
     *   "data": {
     *     "id": 123,
     *     "action": "approve",
     *     "is_approved": true,
     *     "is_featured": false,
     *     "moderated_by": {
     *       "id": 1,
     *       "name": "Admin User"
     *     },
     *     "moderated_at": "2025-01-16T14:30:00.000000Z",
     *     "reason": null
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "Reason is required when rejecting a review.",
     *     "Invalid moderation action."
     *   ]
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function moderate(AdminReviewModerationRequest $request, Review $review)
    {
        try {
            return $this->adminReviewService->moderateReview($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get all reported reviews for moderation
     *
     * Retrieve all reviews that have been reported by users, sorted by priority
     * and report severity. Essential for content moderation workflow.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @queryParam status string optional Filter by report status. Options: pending, reviewed, resolved, dismissed. Default: pending. Example: pending
     * @queryParam reason string optional Filter by report reason. Options: spam, inappropriate_language, fake_review, off_topic, personal_information, other. Example: spam
     * @queryParam priority string optional Filter by priority level. Options: high, medium, low. Example: high
     * @queryParam sort_by string optional Sort reports. Options: newest, oldest, priority, reports_count. Default: priority. Example: reports_count
     * @queryParam per_page integer optional Number of reports per page (1-100). Default: 20. Example: 50
     *
     * @response 200 scenario="Reported reviews retrieved successfully" {
     *   "message": "Reported reviews retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "review": {
     *           "id": 123,
     *           "rating": 1,
     *           "title": "Terrible product",
     *           "content": "This is spam content with promotional links...",
     *           "user": {
     *             "id": 45,
     *             "name": "Suspicious User"
     *           },
     *           "product": {
     *             "id": 15,
     *             "name": "Premium Wireless Earbuds"
     *           }
     *         },
     *         "reports": [
     *           {
     *             "id": 67,
     *             "reason": "spam",
     *             "details": "Contains promotional links and fake claims",
     *             "status": "pending",
     *             "priority": "high",
     *             "reported_by": {
     *               "id": 89,
     *               "name": "John Doe"
     *             },
     *             "created_at": "2025-01-12T10:30:00.000000Z"
     *           }
     *         ],
     *         "reports_count": 3,
     *         "last_reported_at": "2025-01-13T08:15:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 20,
     *     "total": 47
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function getReportedReviews(Request $request)
    {
        try {
            return $this->adminReviewService->getReportedReviews($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Handle a review report (resolve, dismiss)
     *
     * Take action on review reports by resolving or dismissing them.
     * Resolved reports indicate action was taken, dismissed reports indicate
     * the report was invalid or not actionable.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @urlParam report integer required The ID of the review report to handle. Example: 67
     *
     * @bodyParam action string required Action to take on the report. Options: resolve, dismiss. Example: resolve
     * @bodyParam notes string optional Admin notes explaining the decision. Example: "Review content violates community guidelines"
     *
     * @response 200 scenario="Report handled successfully" {
     *   "message": "Review report resolved successfully.",
     *   "data": {
     *     "report_id": 67,
     *     "review_id": 123,
     *     "action": "resolve",
     *     "status": "resolved",
     *     "admin_notes": "Review content violates community guidelines",
     *     "reviewed_by": {
     *       "id": 1,
     *       "name": "Admin User"
     *     },
     *     "reviewed_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Report not found" {
     *   "message": "No query results for model [App\\Models\\ReviewReport] 999"
     * }
     */
    public function handleReport(Request $request, ReviewReport $report)
    {
        try {
            return $this->adminReviewService->handleReport($request, $report);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Bulk moderate multiple reviews
     *
     * Perform the same moderation action on multiple reviews simultaneously.
     * Useful for bulk approval, rejection, or featuring of reviews.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @bodyParam review_ids array required Array of review IDs to moderate. Example: [123, 124, 125]
     * @bodyParam action string required Bulk action to perform. Options: approve, reject, feature, unfeature. Example: approve
     * @bodyParam reason string optional Reason for bulk action (required for reject). Example: "Quality assurance batch approval"
     *
     * @response 200 scenario="Bulk moderation completed" {
     *   "message": "Bulk moderation completed successfully.",
     *   "data": {
     *     "action": "approve",
     *     "total_reviews": 3,
     *     "successful": 3,
     *     "failed": 0,
     *     "results": [
     *       {
     *         "review_id": 123,
     *         "status": "success",
     *         "message": "Review approved"
     *       },
     *       {
     *         "review_id": 124,
     *         "status": "success",
     *         "message": "Review approved"
     *       },
     *       {
     *         "review_id": 125,
     *         "status": "success",
     *         "message": "Review approved"
     *       }
     *     ],
     *     "moderated_by": {
     *       "id": 1,
     *       "name": "Admin User"
     *     },
     *     "moderated_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The review_ids field is required.",
     *     "The action field is required."
     *   ]
     * }
     */
    public function bulkModerate(Request $request)
    {
        try {
            return $this->adminReviewService->bulkModerate($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get review analytics and statistics
     *
     * Retrieve comprehensive analytics about reviews including trends,
     * moderation statistics, and quality metrics for administrative insights.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @queryParam period string optional Time period for analytics. Options: today, week, month, quarter, year. Default: month. Example: week
     * @queryParam product_id integer optional Get analytics for specific product. Example: 15
     * @queryParam include_trends boolean optional Include trend data and charts. Default: false. Example: true
     *
     * @response 200 scenario="Analytics retrieved successfully" {
     *   "message": "Review analytics retrieved successfully.",
     *   "data": {
     *     "summary": {
     *       "total_reviews": 1543,
     *       "pending_approval": 23,
     *       "reported_reviews": 8,
     *       "featured_reviews": 45,
     *       "average_rating": 4.2,
     *       "verification_rate": 67.3
     *     },
     *     "period_stats": {
     *       "new_reviews": 89,
     *       "approved_reviews": 76,
     *       "rejected_reviews": 4,
     *       "reports_received": 12,
     *       "reports_resolved": 8
     *     },
     *     "rating_distribution": {
     *       "1": 45,
     *       "2": 78,
     *       "3": 234,
     *       "4": 567,
     *       "5": 619
     *     },
     *     "top_products": [
     *       {
     *         "product_id": 15,
     *         "product_name": "Premium Wireless Earbuds",
     *         "review_count": 127,
     *         "average_rating": 4.5
     *       }
     *     ],
     *     "moderation_stats": {
     *       "approval_rate": 94.2,
     *       "average_response_time_hours": 4.7,
     *       "moderator_activity": [
     *         {
     *           "moderator": "Admin User",
     *           "actions": 45,
     *           "approvals": 42,
     *           "rejections": 3
     *         }
     *       ]
     *     }
     *   }
     * }
     */
    public function getAnalytics(Request $request)
    {
        try {
            return $this->adminReviewService->getAnalytics($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a review permanently
     *
     * Permanently delete a review and all associated data including media files,
     * helpfulness votes, and reports. This action is irreversible.
     *
     * @group Admin Review Management
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to delete permanently. Example: 123
     *
     * @response 200 scenario="Review deleted successfully" {
     *   "message": "Review deleted permanently."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Review not found" {
     *   "message": "No query results for model [App\\Models\\Review] 999"
     * }
     */
    public function destroy(Request $request, Review $review)
    {
        try {
            return $this->adminReviewService->deleteReview($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

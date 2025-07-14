<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReviewResponse;
use App\Services\V1\Reviews\AdminReviewResponseService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Exception;

class ReviewResponseController extends Controller
{
    use ApiResponses;

    private AdminReviewResponseService $adminReviewResponseService;

    public function __construct(AdminReviewResponseService $adminReviewResponseService)
    {
        $this->adminReviewResponseService = $adminReviewResponseService;
    }

    /**
     * Get all review responses with admin filtering capabilities
     *
     * Retrieve all vendor responses in the system with advanced admin filtering and sorting.
     * Provides comprehensive response management including pending approvals, response analytics,
     * and detailed oversight for administrative purposes.
     *
     * @group Admin Review Response Management
     * @authenticated
     *
     * @queryParam vendor_id integer optional Filter by specific vendor ID. Example: 5
     * @queryParam product_id integer optional Filter by specific product ID. Example: 15
     * @queryParam review_rating array optional Filter by review ratings (1-5). Example: [4,5]
     * @queryParam status string optional Filter by approval status. Options: approved, pending, rejected. Example: pending
     * @queryParam sort_by string optional Sort responses. Options: newest, oldest, rating_high, rating_low. Example: newest
     * @queryParam per_page integer optional Number of responses per page (1-100). Default: 20. Example: 50
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     *
     * @response 200 scenario="Responses retrieved successfully" {
     *   "message": "Review responses retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 45,
     *         "review": {
     *           "id": 123,
     *           "rating": 4,
     *           "title": "Good product",
     *           "user": {
     *             "id": 67,
     *             "name": "John Doe",
     *             "email": "john@example.com"
     *           },
     *           "product": {
     *             "id": 15,
     *             "name": "Premium Wireless Earbuds"
     *           }
     *         },
     *         "vendor": {
     *           "id": 8,
     *           "name": "Tech Solutions Inc",
     *           "description": "Leading electronics vendor"
     *         },
     *         "user": {
     *           "id": 89,
     *           "name": "Vendor Manager",
     *           "email": "manager@techsolutions.com"
     *         },
     *         "content": "Thank you for your feedback! We're glad you're satisfied with the product.",
     *         "is_approved": true,
     *         "approved_at": "2025-01-15T10:30:00.000000Z",
     *         "created_at": "2025-01-14T16:45:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 20,
     *     "total": 156,
     *     "last_page": 8
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function adminIndex(Request $request)
    {
        try {
            return $this->adminReviewResponseService->getAllResponses($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get detailed review response information for admin
     *
     * Retrieve comprehensive admin view of a specific vendor response including
     * review context, vendor details, approval status, and administrative actions available.
     *
     * @group Admin Review Response Management
     * @authenticated
     *
     * @urlParam response integer required The ID of the response to retrieve. Example: 45
     *
     * @response 200 scenario="Response retrieved successfully" {
     *   "message": "Response details retrieved successfully.",
     *   "data": {
     *     "id": 45,
     *     "review": {
     *       "id": 123,
     *       "rating": 4,
     *       "title": "Good product",
     *       "content": "The product works well but could use some improvements in design.",
     *       "is_verified_purchase": true,
     *       "user": {
     *         "id": 67,
     *         "name": "John Doe",
     *         "email": "john@example.com"
     *       },
     *       "product": {
     *         "id": 15,
     *         "name": "Premium Wireless Earbuds",
     *         "vendor_id": 8
     *       }
     *     },
     *     "vendor": {
     *       "id": 8,
     *       "name": "Tech Solutions Inc",
     *       "description": "Leading electronics vendor"
     *     },
     *     "user": {
     *       "id": 89,
     *       "name": "Vendor Manager",
     *       "email": "manager@techsolutions.com"
     *     },
     *     "content": "Thank you for your detailed feedback! We appreciate your honest review and will take your design suggestions into consideration for future product iterations. If you have any specific concerns, please don't hesitate to contact our support team.",
     *     "is_approved": true,
     *     "approved_at": "2025-01-15T10:30:00.000000Z",
     *     "created_at": "2025-01-14T16:45:00.000000Z",
     *     "updated_at": "2025-01-14T17:20:00.000000Z"
     *   }
     * }
     *
     * @response 404 scenario="Response not found" {
     *   "message": "No query results for model [App\\Models\\ReviewResponse] 999"
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function adminShow(Request $request, ReviewResponse $response)
    {
        try {
            return $this->adminReviewResponseService->getResponseDetails($request, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Approve a vendor response
     *
     * Approve a pending vendor response, making it visible to customers.
     * Only responses that are currently unapproved can be approved through this endpoint.
     *
     * @group Admin Review Response Management
     * @authenticated
     *
     * @urlParam response integer required The ID of the response to approve. Example: 45
     *
     * @response 200 scenario="Response approved successfully" {
     *   "message": "Response approved successfully.",
     *   "data": {
     *     "id": 45,
     *     "is_approved": true,
     *     "approved_at": "2025-01-16T14:30:00.000000Z",
     *     "approved_by": {
     *       "id": 1,
     *       "name": "Admin User"
     *     }
     *   }
     * }
     *
     * @response 409 scenario="Already approved" {
     *   "message": "Response is already approved."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function approve(Request $request, ReviewResponse $response)
    {
        try {
            return $this->adminReviewResponseService->approveResponse($request, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a vendor response permanently
     *
     * Permanently delete a vendor response. This action is irreversible and will
     * remove the response from the review thread completely.
     *
     * @group Admin Review Response Management
     * @authenticated
     *
     * @urlParam response integer required The ID of the response to delete permanently. Example: 45
     *
     * @response 200 scenario="Response deleted successfully" {
     *   "message": "Response deleted permanently."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Response not found" {
     *   "message": "No query results for model [App\\Models\\ReviewResponse] 999"
     * }
     */
    public function adminDestroy(Request $request, ReviewResponse $response)
    {
        try {
            return $this->adminReviewResponseService->deleteResponse($request, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Bulk moderate multiple vendor responses
     *
     * Perform the same moderation action on multiple vendor responses simultaneously.
     * Useful for bulk approval, rejection, or deletion of responses.
     *
     * @group Admin Review Response Management
     * @authenticated
     *
     * @bodyParam response_ids array required Array of response IDs to moderate. Example: [45, 46, 47]
     * @bodyParam action string required Bulk action to perform. Options: approve, reject, delete. Example: approve
     * @bodyParam reason string optional Reason for bulk action (recommended for reject/delete). Example: "Quality assurance batch approval"
     *
     * @response 200 scenario="Bulk moderation completed" {
     *   "message": "Bulk moderation completed successfully.",
     *   "data": {
     *     "action": "approve",
     *     "total_responses": 3,
     *     "successful": 3,
     *     "failed": 0,
     *     "results": [
     *       {
     *         "response_id": 45,
     *         "status": "success",
     *         "message": "Approved successfully"
     *       },
     *       {
     *         "response_id": 46,
     *         "status": "success",
     *         "message": "Approved successfully"
     *       },
     *       {
     *         "response_id": 47,
     *         "status": "success",
     *         "message": "Approved successfully"
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
     *     "The response_ids field is required.",
     *     "The action field is required."
     *   ]
     * }
     */
    public function bulkModerate(Request $request)
    {
        try {
            return $this->adminReviewResponseService->bulkModerate($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get review response analytics and statistics
     *
     * Retrieve comprehensive analytics about vendor responses including response rates,
     * approval statistics, vendor performance metrics, and trends for administrative insights.
     *
     * @group Admin Review Response Management
     * @authenticated
     *
     * @queryParam period string optional Time period for analytics. Options: today, week, month, quarter, year. Default: month. Example: week
     * @queryParam vendor_id integer optional Get analytics for specific vendor. Example: 8
     * @queryParam include_trends boolean optional Include trend data and charts. Default: false. Example: true
     *
     * @response 200 scenario="Analytics retrieved successfully" {
     *   "message": "Response analytics retrieved successfully.",
     *   "data": {
     *     "summary": {
     *       "total_responses": 156,
     *       "pending_approval": 12,
     *       "approved_responses": 144,
     *       "response_rate": 73.2,
     *       "average_response_time_hours": 6.4
     *     },
     *     "period_stats": {
     *       "new_responses": 23,
     *       "approved_responses": 21,
     *       "rejected_responses": 1
     *     },
     *     "top_vendors": [
     *       {
     *         "vendor_id": 8,
     *         "vendor_name": "Tech Solutions Inc",
     *         "response_count": 45,
     *         "response_rate": 89.5,
     *         "average_response_time": 4.2
     *       },
     *       {
     *         "vendor_id": 12,
     *         "vendor_name": "Premium Electronics",
     *         "response_count": 32,
     *         "response_rate": 78.3,
     *         "average_response_time": 8.1
     *       }
     *     ],
     *     "response_trends": {
     *       "daily_responses": [
     *         {"date": "2025-01-10", "responses": 8},
     *         {"date": "2025-01-11", "responses": 12},
     *         {"date": "2025-01-12", "responses": 6}
     *       ]
     *     }
     *   }
     * }
     */
    public function getAnalytics(Request $request)
    {
        try {
            return $this->adminReviewResponseService->getAnalytics($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Orders\Returns;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;
use Illuminate\Http\Request;

class ReturnsController extends Controller
{
    use ApiResponses;

    private $returns;

    public function __construct(Returns $returns)
    {
        $this->returns = $returns;
    }

    /**
     * Retrieve a paginated list of all return requests
     *
     * Get a comprehensive list of all return requests in the system with detailed information
     * about customer reasons, order details, product information, and current status. This endpoint
     * is essential for customer service management, return processing workflows, and administrative
     * oversight of the returns process. Includes complete audit trail and customer communication history.
     *
     * @group Return Management
     * @authenticated
     *
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of returns per page (max 100). Default: 20. Example: 25
     * @queryParam status string optional Filter by return status. Available: Requested, Under Review, Approved, Rejected, Return Shipped, Return Received, Completed, Cancelled, Pending. Example: Requested
     * @queryParam date_from string optional Filter returns from this date (YYYY-MM-DD format). Example: 2025-01-01
     * @queryParam date_to string optional Filter returns to this date (YYYY-MM-DD format). Example: 2025-01-31
     * @queryParam order_id integer optional Filter returns by specific order ID. Example: 8
     * @queryParam user_id integer optional Filter returns by specific customer ID. Example: 3
     * @queryParam product_id integer optional Filter returns by specific product ID. Example: 12
     * @queryParam reason string optional Filter by return reason (partial text search). Example: damaged
     *
     * @response 200 scenario="Return requests retrieved successfully" {
     *     "message": "Order returns retrieved.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 1,
     *                 "reason": "Product damaged on arrival - speaker has crackling sound at any volume level. Packaging appeared intact but internal components seem faulty.",
     *                 "status": "Requested",
     *                 "created_at": "2025-01-15T10:30:00.000000Z",
     *                 "updated_at": "2025-01-15T10:30:00.000000Z",
     *                 "order_item": {
     *                     "id": 25,
     *                     "quantity": 2,
     *                     "price": 2999,
     *                     "price_formatted": "£29.99",
     *                     "line_total": 5998,
     *                     "line_total_formatted": "£59.98",
     *                     "created_at": "2025-01-10T16:30:00.000000Z",
     *                     "product": {
     *                         "id": 12,
     *                         "name": "Wireless Headphones",
     *                         "description": "Premium quality wireless headphones with active noise cancellation",
     *                         "price": 2999,
     *                         "price_formatted": "£29.99",
     *                         "featured_image": "https://yourapi.com/storage/products/headphones-featured.jpg"
     *                     },
     *                     "order": {
     *                         "id": 8,
     *                         "total_amount": 5998,
     *                         "total_amount_formatted": "£59.98",
     *                         "created_at": "2025-01-10T16:30:00.000000Z",
     *                         "updated_at": "2025-01-12T11:15:00.000000Z",
     *                         "user": {
     *                             "id": 3,
     *                             "name": "Sarah Johnson",
     *                             "email": "sarah@example.com",
     *                             "email_verified_at": "2025-01-05T12:00:00.000000Z"
     *                         }
     *                     }
     *                 }
     *             },
     *             {
     *                 "id": 2,
     *                 "reason": "Product does not match description - advertised as waterproof but water damage occurred during first use",
     *                 "status": "Under Review",
     *                 "created_at": "2025-01-14T14:20:00.000000Z",
     *                 "updated_at": "2025-01-15T09:45:00.000000Z",
     *                 "order_item": {
     *                     "id": 31,
     *                     "quantity": 1,
     *                     "price": 4999,
     *                     "price_formatted": "£49.99",
     *                     "line_total": 4999,
     *                     "line_total_formatted": "£49.99",
     *                     "product": {
     *                         "id": 18,
     *                         "name": "Bluetooth Portable Speaker",
     *                         "description": "Compact waterproof speaker with 12-hour battery",
     *                         "price": 4999,
     *                         "price_formatted": "£49.99"
     *                     },
     *                     "order": {
     *                         "id": 11,
     *                         "total_amount": 4999,
     *                         "total_amount_formatted": "£49.99",
     *                         "user": {
     *                             "id": 7,
     *                             "name": "Michael Chen",
     *                             "email": "michael@example.com",
     *                             "email_verified_at": "2025-01-08T14:30:00.000000Z"
     *                         }
     *                     }
     *                 }
     *             },
     *             {
     *                 "id": 3,
     *                 "reason": "Changed mind about purchase - no longer needed due to receiving similar item as gift",
     *                 "status": "Approved",
     *                 "created_at": "2025-01-13T16:15:00.000000Z",
     *                 "updated_at": "2025-01-14T11:20:00.000000Z",
     *                 "order_item": {
     *                     "id": 28,
     *                     "quantity": 1,
     *                     "price": 7999,
     *                     "price_formatted": "£79.99",
     *                     "product": {
     *                         "id": 23,
     *                         "name": "Premium Bluetooth Speaker",
     *                         "price": 7999,
     *                         "price_formatted": "£79.99"
     *                     },
     *                     "order": {
     *                         "id": 9,
     *                         "total_amount": 7999,
     *                         "total_amount_formatted": "£79.99",
     *                         "user": {
     *                             "id": 15,
     *                             "name": "Emma Wilson",
     *                             "email": "emma@example.com"
     *                         }
     *                     }
     *                 }
     *             },
     *             {
     *                 "id": 4,
     *                 "reason": "Wrong size ordered by mistake - need larger size but currently out of stock",
     *                 "status": "Rejected",
     *                 "created_at": "2025-01-12T11:30:00.000000Z",
     *                 "updated_at": "2025-01-13T14:45:00.000000Z",
     *                 "order_item": {
     *                     "id": 22,
     *                     "quantity": 1,
     *                     "price": 3999,
     *                     "price_formatted": "£39.99",
     *                     "product": {
     *                         "id": 34,
     *                         "name": "Wireless Mouse",
     *                         "price": 3999,
     *                         "price_formatted": "£39.99"
     *                     },
     *                     "order": {
     *                         "id": 7,
     *                         "total_amount": 3999,
     *                         "total_amount_formatted": "£39.99",
     *                         "user": {
     *                             "id": 12,
     *                             "name": "David Rodriguez",
     *                             "email": "david@example.com"
     *                         }
     *                     }
     *                 }
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 20,
     *         "total": 89,
     *         "last_page": 5,
     *         "from": 1,
     *         "to": 20,
     *         "path": "https://yourapi.com/api/v1/admin/returns",
     *         "first_page_url": "https://yourapi.com/api/v1/admin/returns?page=1",
     *         "last_page_url": "https://yourapi.com/api/v1/admin/returns?page=5",
     *         "next_page_url": "https://yourapi.com/api/v1/admin/returns?page=2",
     *         "prev_page_url": null
     *     }
     * }
     *
     * @response 200 scenario="No return requests found" {
     *     "message": "Order returns retrieved.",
     *     "data": {
     *         "data": [],
     *         "current_page": 1,
     *         "per_page": 20,
     *         "total": 0,
     *         "last_page": 1,
     *         "from": null,
     *         "to": null
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *     "errors": [
     *         "The status field must be one of: Requested, Under Review, Approved, Rejected, Return Shipped, Return Received, Completed, Cancelled, Pending.",
     *         "The date from field must be a valid date in YYYY-MM-DD format.",
     *         "The order id field must be an integer.",
     *         "The user id field must be an integer."
     *     ]
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->returns->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Review and update the status of a return request
     *
     * Process a return request by changing its status through the review workflow. This endpoint
     * allows administrators to move returns through different stages: review, approve, or reject.
     * Status changes trigger appropriate notifications to customers and update the order processing
     * workflow. Only returns in appropriate states can be transitioned to prevent invalid workflows.
     *
     * @group Return Management
     * @authenticated
     *
     * @urlParam returnId integer required The ID of the return request to review. Example: 15
     * @urlParam action string required The action to perform on the return. Must be one of: review, approve, reject. Example: approve
     *
     * @response 200 scenario="Return approved successfully" {
     *     "message": "Return status updated to Approved.",
     *     "data": {
     *         "id": 15,
     *         "order_item_id": 25,
     *         "reason": "Product damaged on arrival - speaker has crackling sound at any volume level",
     *         "order_return_status_id": 3,
     *         "created_at": "2025-01-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-15T14:45:00.000000Z"
     *     }
     * }
     *
     * @response 200 scenario="Return moved to review" {
     *     "message": "Return status updated to Under Review.",
     *     "data": {
     *         "id": 12,
     *         "order_item_id": 18,
     *         "reason": "Product does not match description - advertised as waterproof but failed during first use",
     *         "order_return_status_id": 2,
     *         "created_at": "2025-01-14T14:20:00.000000Z",
     *         "updated_at": "2025-01-15T09:30:00.000000Z"
     *     }
     * }
     *
     * @response 200 scenario="Return rejected" {
     *     "message": "Return status updated to Rejected.",
     *     "data": {
     *         "id": 8,
     *         "order_item_id": 22,
     *         "reason": "Wrong size ordered by mistake",
     *         "order_return_status_id": 4,
     *         "created_at": "2025-01-12T11:30:00.000000Z",
     *         "updated_at": "2025-01-15T16:15:00.000000Z"
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Return request not found" {
     *     "message": "Return request not found."
     * }
     *
     * @response 422 scenario="Return already processed" {
     *     "message": "Return is already processed."
     * }
     *
     * @response 422 scenario="Invalid action provided" {
     *     "message": "Invalid action provided."
     * }
     *
     * @response 422 scenario="Invalid status transition" {
     *     "message": "Cannot change return status from Completed to Under Review. Invalid status transition."
     * }
     *
     * @response 400 scenario="Return window expired" {
     *     "message": "Return request cannot be approved as the return window has expired."
     * }
     *
     * @response 409 scenario="Return already has refund" {
     *     "message": "Cannot modify return status as a refund has already been processed for this item."
     * }
     *
     * @response 422 scenario="Order not eligible for returns" {
     *     "message": "Order is not in a state that allows returns (must be delivered or completed)."
     * }
     *
     * @response 500 scenario="Status update failed" {
     *     "message": "An error occurred while updating the return status."
     * }
     */
    public function reviewReturn(Request $request, int $returnId, string $action)
    {
        try {
            return $this->returns->reviewReturn($request, $returnId, $action);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Requests\V1\StoreReturnRequest;
use App\Services\V1\Returns\Returns;
use App\Traits\V1\ApiResponses;
use Exception;

class ReturnsController extends Controller
{
    use ApiResponses;

    private $returns;

    public function __construct(Returns $returns)
    {
        $this->returns = $returns;
    }

    /**
     * Create a return request for an order item
     *
     * Allows authenticated users to request a return for items from their paid orders.
     * Users can only return items from orders they own and that have been successfully paid.
     * Each order item can only have one return request to prevent duplicate submissions.
     * This is the first step in the return process - the request will need admin approval before processing.
     *
     * @group Order Returns
     * @authenticated
     *
     * @bodyParam order_item_id integer required The ID of the specific order item to return. Must belong to a paid order owned by the authenticated user. Example: 89
     * @bodyParam reason string required Detailed reason for the return request. Must be between 10 and 1000 characters to provide sufficient context for review. Example: The wireless headphones arrived with a defective left speaker that produces crackling sounds at any volume level. The product does not match the quality described.
     *
     * @response 200 scenario="Return request created successfully" {
     *   "message": "Orders return created.",
     *   "data": {
     *     "id": 15,
     *     "reason": "The wireless headphones arrived with a defective left speaker that produces crackling sounds at any volume level. The product does not match the quality described.",
     *     "status": "Requested",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Unauthorized - not user's order item" {
     *   "message": "You are not authorized to return this item."
     * }
     *
     * @response 409 scenario="Return already exists for this item" {
     *   "message": "A return request has already been created for this item."
     * }
     *
     * @response 422 scenario="Order not paid" {
     *   "message": "Order has not been paid for."
     * }
     *
     * @response 422 scenario="Order too old for returns" {
     *   "message": "Return window has expired for this order."
     * }
     *
     * @response 404 scenario="Order item not found" {
     *   "message": "Order item not found."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The order item id field is required.",
     *     "The reason field is required.",
     *     "The reason must be at least 10 characters.",
     *     "The reason may not be greater than 1000 characters.",
     *     "The selected order item id is invalid."
     *   ]
     * }
     *
     * @response 401 scenario="User not authenticated" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 400 scenario="Item not eligible for return" {
     *   "message": "This item is not eligible for return due to its category or condition."
     * }
     */
    public function return(StoreReturnRequest $request)
    {
        try {
            return $this->returns->createReturn($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

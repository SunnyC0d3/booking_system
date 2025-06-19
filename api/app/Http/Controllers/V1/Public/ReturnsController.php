<?php

namespace App\Http\Controllers\V1\Public;

use App\Requests\V1\StoreReturnRequest;
use App\Services\V1\Orders\Returns;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;

class ReturnsController extends Controller
{
    use ApiResponses;

    private $returns;

    public function __construct(Returns $returns)
    {
        $this->returns = $returns;
    }

    /**
     * Create a new return request for an order item.
     *
     * @group Returns
     * @authenticated
     *
     * @bodyParam order_item_id integer required The ID of the order item to return. Example: 25
     * @bodyParam reason string required The reason for the return request. Must be between 10 and 500 characters. Example: The product arrived damaged and doesn't match the description.
     *
     * @response 200 {
     *     "message": "Orders return created.",
     *     "data": {
     *         "id": 15,
     *         "reason": "The product arrived damaged and doesn't match the description.",
     *         "status": "Requested",
     *         "created_at": "2025-01-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-15T10:30:00.000000Z"
     *     }
     * }
     *
     * @response 403 {
     *     "message": "You are not authorized to return this item."
     * }
     *
     * @response 409 {
     *     "message": "A return request has already been created for this item."
     * }
     *
     * @response 422 {
     *     "message": "Order has not been paid for."
     * }
     *
     * @response 404 {
     *     "message": "Order item not found."
     * }
     *
     * @response 422 {
     *     "message": "The order item id field is required.",
     *     "errors": {
     *         "order_item_id": [
     *             "The order item id field is required."
     *         ],
     *         "reason": [
     *             "The reason field is required."
     *         ]
     *     }
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

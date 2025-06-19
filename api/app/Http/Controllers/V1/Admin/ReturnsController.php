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
     * Retrieve a paginated list of all return requests.
     *
     * @group Returns
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order returns retrieved.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 1,
     *                 "reason": "Product damaged on arrival",
     *                 "status": "Requested",
     *                 "created_at": "2025-01-15T10:30:00.000000Z",
     *                 "updated_at": "2025-01-15T10:30:00.000000Z",
     *                 "order_item": {
     *                     "id": 25,
     *                     "quantity": 2,
     *                     "price": 2999,
     *                     "product": {
     *                         "id": 12,
     *                         "name": "Wireless Headphones",
     *                         "price": 2999
     *                     },
     *                     "order": {
     *                         "id": 8,
     *                         "total_amount": 5998,
     *                         "user": {
     *                             "id": 3,
     *                             "email": "customer@example.com"
     *                         }
     *                     }
     *                 }
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 20,
     *         "total": 45
     *     }
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Review and update the status of a return request.
     *
     * @group Returns
     * @authenticated
     *
     * @urlParam returnId integer required The ID of the return request. Example: 15
     * @urlParam action string required The action to perform on the return. Must be one of: review, approve, reject. Example: approve
     *
     * @response 200 {
     *     "message": "Return status updated to Approved.",
     *     "data": {
     *         "id": 15,
     *         "order_item_id": 25,
     *         "reason": "Product damaged on arrival",
     *         "order_return_status_id": 3,
     *         "created_at": "2025-01-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-15T14:45:00.000000Z"
     *     }
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 {
     *     "message": "Return is already processed."
     * }
     *
     * @response 422 {
     *     "message": "Invalid action provided."
     * }
     *
     * @response 404 {
     *     "message": "Return request not found."
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

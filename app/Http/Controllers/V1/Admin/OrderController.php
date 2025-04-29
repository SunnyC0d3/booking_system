<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Orders\Order;
use App\Requests\V1\StoreOrderRequest;
use App\Requests\V1\UpdateOrderRequest;
use App\Requests\V1\IndexOrderRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Models\Order as DB;
use App\Models\OrderItem;
use \Exception;

class OrderController extends Controller
{
    use ApiResponses;

    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Retrieve paginated list of orders.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *   "message": "Orders retrieved successfully.",
     *   "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function index(IndexOrderRequest $request)
    {
        try {
            return $this->order->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new order.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order created successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            return $this->order->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific order.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order retrieved successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function show(Request $request, DB $order)
    {
        try {
            return $this->order->find($request, $order);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing order.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order updated successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function update(UpdateOrderRequest $request, DB $order)
    {
        try {
            return $this->order->update($request, $order);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Soft delete a order.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order deleted (soft)."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function destroy(Request $request, DB $order)
    {
        try {
            return $this->order->delete($request, $order);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Restore a soft deleted order.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order restored successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function restore(Request $request, int $id)
    {
        try {
            return $this->order->restore($request, $id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Permanently delete a order.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order permanently deleted."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function forceDelete(Request $request, int $id)
    {
        try {
            return $this->order->forceDelete($request, $id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

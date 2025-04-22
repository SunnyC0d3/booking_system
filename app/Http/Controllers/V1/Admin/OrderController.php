<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Orders\Order;
use App\Requests\V1\StoreOrderRequest;
use App\Requests\V1\UpdateOrderRequest;
use App\Requests\V1\StoreOrderItemRequest;
use App\Requests\V1\UpdateOrderItemRequest;
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

    public function index(Request $request)
    {
        try {
            return $this->order->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreOrderRequest $request)
    {
        try {
            return $this->order->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function show(Request $request, DB $order)
    {
        try {
            return $this->order->find($request, $order);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateOrderRequest $request, DB $order)
    {
        try {
            return $this->order->update($request, $order);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(Request $request, DB $order)
    {
        try {
            return $this->order->delete($request, $order);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function restore(Request $request, DB $order)
    {
        try {
            return $this->order->delete($request, $order);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function forceDelete(Request $request, DB $order)
    {
        try {
            return $this->order->delete($request, $order);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateStatus(Request $request, Order $order)
    {
        try {
            return $this->order->updateStatus($request, $order);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function addItem(StoreOrderItemRequest $request, DB $order)
    {
        try {
            return $this->order->addItem($request, $order);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateItem(UpdateOrderItemRequest $request, DB $order, OrderItem $item)
    {
        try {
            return $this->order->updateItem($request, $order, $item);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function removeItem(Request $request, DB $order, OrderItem $item)
    {
        try {
            return $this->order->removeItem($request, $order, $item);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

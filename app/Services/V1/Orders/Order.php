<?php

namespace App\Services\V1\Orders;

use App\Resources\V1\OrderResource;
use Illuminate\Http\Request;
use App\Models\Order as OrderDB;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\DB;

class Order
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_orders')) {
            $orders = OrderDB::with(['user', 'orderItems.product', 'orderItems.productVariant', 'status'])
                ->when($request->input('status_id'), fn($q) => $q->where('status_id', $request->status_id))
                ->when($request->input('user_id'), fn($q) => $q->where('user_id', $request->user_id))
                ->latest()
                ->paginate(15);

            return $this->ok('Orders retrieved successfully.', $orders);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('view_orders')) {
            $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'status']);
            return $this->ok(new OrderResource($order));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_orders')) {
            $data = $request->validated();

            DB::transaction(function () use ($data, &$order) {
                $order = OrderDB::create($data);

                $total = 0;

                foreach ($data['order_items'] as $item) {
                    $price = $item['price'];
                    $quantity = $item['quantity'];
                    $total += $price * $quantity;

                    $order->orderItems()->create([
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'] ?? null,
                        'quantity' => $quantity,
                        'price' => $price,
                    ]);
                }

                $order->update(['total_amount' => $total]);
            });

            $order->load('orderItems');

            return $this->ok('Order created successfully.', new OrderResource($order));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_orders')) {
            $data = $request->validated();

            DB::transaction(function () use ($data, $order) {
                $order->update($data);

                if (!empty($data['order_items'])) {
                    $order->orderItems()->delete();

                    $total = 0;

                    foreach ($data['order_items'] as $item) {
                        $price = $item['price'];
                        $quantity = $item['quantity'];
                        $total += $price * $quantity;

                        $order->orderItems()->create([
                            'product_id' => $item['product_id'],
                            'product_variant_id' => $item['product_variant_id'] ?? null,
                            'quantity' => $quantity,
                            'price' => $price,
                        ]);
                    }

                    $order->update(['total_amount' => $total]);
                }
            });

            $order->load('orderItems');

            return response()->json('Order updated successfully.', new OrderResource($order));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_orders')) {
            $order->delete();
            return $this->ok('Order deleted (soft).');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function restore(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('restore_orders')) {
            if (!$order->trashed()) {
                return $this->error('Order is not deleted.', 400);
            }

            $order->restore();

            return $this->success('Order restored successfully.', $order);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function forceDelete(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('force_delete_orders')) {
            if (!$order->trashed()) {
                return $this->error('Order must be soft deleted before force deleting.', 400);
            }

            $order->forceDelete();

            return $this->ok('Order permanently deleted.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

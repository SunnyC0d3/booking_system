<?php

namespace App\Services\V1\Orders;

use Illuminate\Http\Request;
use App\Models\Order as OrderDB;
use App\Models\OrderItem as OrderItemDB;
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
            $orders = \App\Models\Order::with(['user', 'orderItems.product', 'orderItems.productVariant', 'status'])
                ->when($request->input('status_id'), fn($q) => $q->where('status_id', $request->status_id))
                ->when($request->input('user_id'), fn($q) => $q->where('user_id', $request->user_id))
                ->latest()
                ->paginate(15);

            return response()->json(['data' => $orders]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('view_orders')) {
            return response()->json([
                'data' => $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'status']),
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_orders')) {
            DB::transaction(function () use ($request, &$order) {
                $order = Order::create([
                    'user_id' => $request->user_id,
                    'status_id' => $request->status_id,
                    'total_amount' => 0, // temp
                ]);

                $total = 0;
                foreach ($request->order_items as $item) {
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

            return response()->json(['message' => 'Order created successfully.', 'data' => $order->load('orderItems')], 201);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_orders')) {
            DB::transaction(function () use ($request, $order) {
                $order->update([
                    'user_id' => $request->user_id,
                    'status_id' => $request->status_id,
                ]);

                if ($request->has('order_items')) {
                    $order->orderItems()->delete(); // reset items

                    $total = 0;
                    foreach ($request->order_items as $item) {
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

            return response()->json(['message' => 'Order updated successfully.', 'data' => $order->load('orderItems')]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_orders')) {
            $order->delete();
            return response()->json(['message' => 'Order deleted (soft).']);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function restore(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('restore_orders')) {
            if (!$order->trashed()) {
                return $this->error('Order is not deleted.', [], 400);
            }

            $order->restore();

            return $this->success('Order restored successfully.', $order->fresh(['items', 'shipment', 'refund', 'return']));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function forceDelete(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('force_delete_orders')) {
            if (!$order->trashed()) {
                return $this->error('Order must be soft deleted before force deleting.', [], 400);
            }

            $order->forceDelete();

            return $this->success('Order permanently deleted.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function updateStatus(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('update_status_orders')) {
            $request->validate([
                'status_id' => 'required|exists:order_statuses,id',
            ]);

            $order->update(['status_id' => $request->status_id]);

            return response()->json(['message' => 'Order status updated.', 'data' => $order]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function addItem(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('create_order_items')) {
            $item = $order->orderItems()->create($request->validated());

            return $this->ok('Item added successfully.', $item);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function updateItem(Request $request, OrderDB $order, OrderItemDB $item)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_order_items')) {
            if ($item->order_id !== $order->id) {
                return $this->error('Item does not belong to this order.', 403);
            }

            $item->update($request->validated());

            return $this->ok('Item updated successfully.', $item);

        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function removeItem(Request $request, OrderDB $order, OrderItemDB $item)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_order_items')) {
            if ($item->order_id !== $order->id) {
                return $this->error('Item does not belong to this order.', 403);
            }

            $item->delete();

            return $this->ok('Item removed successfully.');

        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

<?php

namespace App\Services\V1\Orders;

use App\Resources\V1\OrderResource;
use Illuminate\Http\Request;
use App\Models\Order as OrderDB;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $data = $request->validated();

            $orders = OrderDB::with(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status'])
                ->when(!empty($data['status_id']), fn($query) => $query->where('status_id', $data['status_id']))
                ->when(!empty($data['user_id']), fn($query) => $query->where('user_id', $data['user_id']))
                ->latest()
                ->paginate(15);

            return $this->ok('Orders retrieved successfully.', OrderResource::collection($orders));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('view_orders')) {
            $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status']);
            return $this->ok('Orders retrieved successfully.', new OrderResource($order));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_orders')) {
            $data = $request->validated();

            DB::transaction(function () use ($data, &$order) {
                $order = OrderDB::create([
                    'user_id' => $data['user_id'],
                    'status_id' => $data['status_id'],
                    'total_amount' => 0,
                ]);

                if (!empty($data['order_items'])) {
                    $totalInPennies = 0;

                    foreach ($data['order_items'] as $item) {
                        $priceInPennies = isset($item['price_pennies'])
                            ? (int) $item['price_pennies']
                            : (int) round($item['price'] * 100);

                        $quantity = $item['quantity'];
                        $lineTotal = $priceInPennies * $quantity;
                        $totalInPennies += $lineTotal;

                        $orderItem = $order->orderItems()->create([
                            'product_id' => $item['product_id'],
                            'product_variant_id' => $item['product_variant_id'] ?? null,
                            'quantity' => $quantity,
                            'price' => $priceInPennies,
                        ]);

                        Log::info('Order item created', [
                            'order_item_id' => $orderItem->id,
                            'product_id' => $item['product_id'],
                            'price_pennies' => $priceInPennies,
                            'price_pounds' => $priceInPennies / 100,
                            'quantity' => $quantity,
                            'line_total_pennies' => $lineTotal,
                            'line_total_pounds' => $lineTotal / 100
                        ]);
                    }

                    $order->setTotalAmountFromPennies($totalInPennies);
                    $order->save();

                    Log::info('Order total calculated', [
                        'order_id' => $order->id,
                        'total_pennies' => $totalInPennies,
                        'total_pounds' => $totalInPennies / 100
                    ]);
                }
            });

            $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status']);

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
                $order->update([
                    'user_id' => $data['user_id'],
                    'status_id' => $data['status_id'],
                    'total_amount' => 0,
                ]);

                if (!empty($data['order_items'])) {
                    $order->orderItems()->delete();

                    $totalInPennies = 0;

                    foreach ($data['order_items'] as $item) {
                        $priceInPennies = isset($item['price_pennies'])
                            ? (int) $item['price_pennies']
                            : (int) round($item['price'] * 100);

                        $quantity = $item['quantity'];
                        $lineTotal = $priceInPennies * $quantity;
                        $totalInPennies += $lineTotal;

                        $order->orderItems()->create([
                            'product_id' => $item['product_id'],
                            'product_variant_id' => $item['product_variant_id'] ?? null,
                            'quantity' => $quantity,
                            'price' => $priceInPennies,
                        ]);
                    }

                    $order->setTotalAmountFromPennies($totalInPennies);
                    $order->save();

                    Log::info('Order updated with new total', [
                        'order_id' => $order->id,
                        'total_pennies' => $totalInPennies,
                        'total_pounds' => $totalInPennies / 100
                    ]);
                }
            });

            $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status']);

            return $this->ok('Order updated successfully.', new OrderResource($order));
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

    public function restore(Request $request, int $id)
    {
        $user = $request->user();

        if ($user->hasPermission('restore_orders')) {
            $order = OrderDB::withTrashed()->findOrFail($id);

            if (!$order->trashed()) {
                return $this->error('Order is not deleted.', 400);
            }

            $order->restore();

            $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status']);

            return $this->success('Order restored successfully.', new OrderResource($order));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function forceDelete(Request $request, int $id)
    {
        $user = $request->user();

        if ($user->hasPermission('force_delete_orders')) {
            $order = OrderDB::withTrashed()->findOrFail($id);

            if (!$order->trashed()) {
                return $this->error('Order must be soft deleted before force deleting.', 400);
            }

            $order->forceDelete();

            return $this->ok('Order permanently deleted.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

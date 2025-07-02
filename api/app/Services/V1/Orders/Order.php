<?php

namespace App\Services\V1\Orders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Resources\V1\OrderResource;
use Illuminate\Http\Request;
use App\Models\Order as OrderDB;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\V1\Inventory\InventoryAlertService;

class Order
{
    use ApiResponses;

    private $inventoryService;

    public function __construct(InventoryAlertService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function all(Request $request)
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->hasPermission('view_all_orders')) {
            $orders = OrderDB::with(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status'])
                ->when(!empty($data['status_id']), fn($query) => $query->where('status_id', $data['status_id']))
                ->when(!empty($data['user_id']), fn($query) => $query->where('user_id', $data['user_id']))
                ->latest()
                ->paginate(15);

            return OrderResource::collection($orders)->additional([
                'message' => 'Orders retrieved successfully.',
                'status' => 200
            ]);
        }

        if ($user->hasPermission('view_own_orders')) {
            $orders = OrderDB::with(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status'])
                ->where('user_id', $user->id)
                ->when(!empty($data['status_id']), fn($query) => $query->where('status_id', $data['status_id']))
                ->latest()
                ->paginate(15);

            return OrderResource::collection($orders)->additional([
                'message' => 'Your orders retrieved successfully.',
                'status' => 200
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('view_all_orders')) {
            $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status']);
            return $this->ok('Order retrieved successfully.', new OrderResource($order));
        }

        if ($user->hasPermission('view_own_orders')) {
            if ($order->user_id !== $user->id) {
                return $this->error('You can only view your own orders.', 403);
            }

            $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status']);
            return $this->ok('Order retrieved successfully.', new OrderResource($order));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->hasPermission('create_orders_for_users')) {
            $userId = $data['user_id'];
        }
        elseif ($user->hasPermission('create_own_orders')) {
            $userId = $user->id;

            if (isset($data['user_id']) && $data['user_id'] != $user->id) {
                return $this->error('You can only create orders for yourself.', 403);
            }
        } else {
            return $this->error('You do not have the required permissions.', 403);
        }

        DB::transaction(function () use ($data, $userId, &$order) {
            $order = OrderDB::create([
                'user_id' => $userId,
                'status_id' => $data['status_id'],
                'total_amount' => 0,
            ]);

            if (!empty($data['order_items'])) {
                $totalInPennies = 0;
                $orderItems = [];

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

                    $orderItems[] = $item;

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

                $this->updateInventoryAfterOrder($orderItems);

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

    public function update(Request $request, OrderDB $order)
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->hasPermission('edit_own_orders')) {
            if ($order->user_id !== $user->id) {
                return $this->error('You can only edit your own orders.', 403);
            }

            if ($order->status_id !== 1) {
                return $this->error('You can only edit pending orders.', 403);
            }
        } else {
            return $this->error('You do not have the required permissions.', 403);
        }

        DB::transaction(function () use ($data, $order, $user) {
            $userId = $user->hasPermission('edit_all_orders')
                ? $data['user_id']
                : $order->user_id;

            $order->update([
                'user_id' => $userId,
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

    public function delete(Request $request, OrderDB $order)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_own_orders')) {
            if ($order->user_id !== $user->id) {
                return $this->error('You can only delete your own orders.', 403);
            }

            if ($order->status_id !== 1) {
                return $this->error('You can only delete pending orders.', 403);
            }
        } else {
            return $this->error('You do not have the required permissions.', 403);
        }

        $order->delete();
        return $this->ok('Order deleted (soft).');
    }

    public function restore(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('restore_orders')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $order = OrderDB::withTrashed()->findOrFail($id);

        if (!$order->trashed()) {
            return $this->error('Order is not deleted.', 400);
        }

        $order->restore();

        $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status']);

        return $this->ok('Order restored successfully.', new OrderResource($order));
    }

    public function forceDelete(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('force_delete_orders')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $order = OrderDB::withTrashed()->findOrFail($id);

        if (!$order->trashed()) {
            return $this->error('Order must be soft deleted before force deleting.', 400);
        }

        $order->forceDelete();

        return $this->ok('Order permanently deleted.');
    }

    protected function updateInventoryAfterOrder($orderItems)
    {
        foreach ($orderItems as $item) {
            $product = Product::find($item['product_id']);

            if ($product) {
                $product->decrement('quantity', $item['quantity']);
                $this->inventoryService->checkProductStock($product->fresh());
            }

            if (!empty($item['product_variant_id'])) {
                $variant = ProductVariant::find($item['product_variant_id']);
                if ($variant) {
                    $variant->decrement('quantity', $item['quantity']);
                    $this->inventoryService->checkVariantStock($variant->fresh());
                }
            }
        }
    }
}

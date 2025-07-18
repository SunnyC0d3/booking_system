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
    private CheckoutService $checkoutService;

    public function __construct(InventoryAlertService $inventoryService, CheckoutService $checkoutService)
    {
        $this->inventoryService = $inventoryService;
        $this->checkoutService = $checkoutService;
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

        try {
            if (isset($data['from_cart']) && $data['from_cart']) {
                return $this->createFromCart($userId, $data);
            }

            return $this->createManualOrder($userId, $data);

        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            return $this->error('Failed to create order: ' . $e->getMessage(), 500);
        }
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
            $productId = is_array($item) ? $item['product_id'] : $item->product_id;
            $variantId = is_array($item) ? ($item['product_variant_id'] ?? null) : $item->product_variant_id;
            $quantity = is_array($item) ? $item['quantity'] : $item->quantity;

            $product = Product::find($productId);

            if ($product) {
                $product->decrement('quantity', $quantity);
                $this->inventoryService->checkProductStock($product->fresh());
            }

            if ($variantId) {
                $variant = ProductVariant::find($variantId);
                if ($variant) {
                    $variant->decrement('quantity', $quantity);
                    $this->inventoryService->checkVariantStock($variant->fresh());
                }
            }
        }
    }

    protected function createManualOrder(int $userId, array $data): \Illuminate\Http\JsonResponse
    {
        $order = null;

        DB::transaction(function () use ($data, $userId, &$order) {
            $order = OrderDB::create([
                'user_id' => $userId,
                'status_id' => $data['status_id'],
                'shipping_method_id' => $data['shipping_method_id'] ?? null,
                'shipping_address_id' => $data['shipping_address_id'] ?? null,
                'shipping_cost' => isset($data['shipping_cost']) ? (int) round($data['shipping_cost'] * 100) : 0,
                'shipping_notes' => $data['shipping_notes'] ?? null,
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
                }

                $totalWithShipping = $totalInPennies + $order->shipping_cost;
                $order->setTotalAmountFromPennies($totalWithShipping);
                $order->save();

                $this->updateInventoryAfterOrder($orderItems);
            }
        });

        $order->load(['user', 'orderItems.product', 'orderItems.productVariant', 'orderItems.orderReturn.status', 'status', 'shippingMethod', 'shippingAddress']);

        return $this->ok('Order created successfully.', new OrderResource($order));
    }

    protected function createFromCart(int $userId, array $data): \Illuminate\Http\JsonResponse
    {
        $cart = Cart::where('user_id', $userId)->with('cartItems.product')->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty.', 400);
        }

        $checkoutData = [
            'shipping_method_id' => $data['shipping_method_id'] ?? null,
            'shipping_address_id' => $data['shipping_address_id'] ?? null,
            'shipping_notes' => $data['shipping_notes'] ?? null,
        ];

        $order = $this->checkoutService->createOrderFromCart($cart, $checkoutData);

        $this->updateInventoryAfterOrder($order->orderItems->toArray());

        $cart->cartItems()->delete();

        return $this->ok('Order created successfully from cart.', new OrderResource($order));
    }
}

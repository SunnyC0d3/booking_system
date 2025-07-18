<?php

namespace App\Services\V1\Orders;

use App\Models\Cart;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\ShippingMethod;
use App\Services\V1\Shipping\ShippingCalculator;
use App\Constants\OrderStatuses;
use App\Constants\FulfillmentStatuses;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class CheckoutService
{
    protected ShippingCalculator $shippingCalculator;

    public function __construct(ShippingCalculator $shippingCalculator)
    {
        $this->shippingCalculator = $shippingCalculator;
    }

    public function createOrderFromCart(Cart $cart, array $checkoutData): Order
    {
        return DB::transaction(function () use ($cart, $checkoutData) {
            // Validate shipping information
            $this->validateShippingData($cart, $checkoutData);

            // Calculate shipping cost
            $shippingCost = $this->calculateShippingCost(
                $cart,
                $checkoutData['shipping_method_id'],
                $checkoutData['shipping_address_id']
            );

            // Create the order
            $order = $this->createOrder($cart, $checkoutData, $shippingCost);

            // Create order items
            $this->createOrderItems($cart, $order);

            // Update final total with shipping
            $this->updateOrderTotal($order, $shippingCost);

            Log::info('Order created with shipping integration', [
                'order_id' => $order->id,
                'user_id' => $cart->user_id,
                'shipping_cost' => $shippingCost,
                'total_amount' => $order->total_amount,
                'shipping_method_id' => $checkoutData['shipping_method_id'],
            ]);

            return $order->load(['orderItems.product', 'shippingMethod', 'shippingAddress']);
        });
    }

    protected function validateShippingData(Cart $cart, array $checkoutData): void
    {
        // Check if cart requires shipping
        if (!$this->cartRequiresShipping($cart)) {
            return; // Skip shipping validation for virtual products
        }

        if (empty($checkoutData['shipping_method_id'])) {
            throw new Exception('Shipping method is required for this order.');
        }

        if (empty($checkoutData['shipping_address_id'])) {
            throw new Exception('Shipping address is required for this order.');
        }

        // Validate shipping method exists and is active
        $shippingMethod = ShippingMethod::where('id', $checkoutData['shipping_method_id'])
            ->where('is_active', true)
            ->first();

        if (!$shippingMethod) {
            throw new Exception('Selected shipping method is not available.');
        }

        // Validate shipping address exists and belongs to user
        $shippingAddress = ShippingAddress::where('id', $checkoutData['shipping_address_id'])
            ->where('user_id', $cart->user_id)
            ->first();

        if (!$shippingAddress) {
            throw new Exception('Selected shipping address is not valid.');
        }

        // Validate shipping method is available for the address
        $isValid = $this->shippingCalculator->validateShippingMethod(
            $checkoutData['shipping_method_id'],
            $shippingAddress,
            $this->calculateCartWeight($cart),
            $cart->getTotalAmountInPennies()
        );

        if (!$isValid) {
            throw new Exception('Selected shipping method is not available for this address.');
        }
    }

    protected function calculateShippingCost(Cart $cart, int $shippingMethodId, int $shippingAddressId): int
    {
        if (!$this->cartRequiresShipping($cart)) {
            return 0;
        }

        $shippingAddress = ShippingAddress::findOrFail($shippingAddressId);
        $weight = $this->calculateCartWeight($cart);
        $total = $cart->getTotalAmountInPennies();

        $cost = $this->shippingCalculator->getShippingCostForMethod(
            $shippingMethodId,
            $shippingAddress,
            $weight,
            $total
        );

        Log::info('Shipping cost calculated', [
            'shipping_method_id' => $shippingMethodId,
            'weight' => $weight,
            'total' => $total,
            'shipping_cost' => $cost,
        ]);

        return $cost;
    }

    protected function createOrder(Cart $cart, array $checkoutData, int $shippingCost): Order
    {
        $statusId = OrderStatus::where('name', OrderStatuses::PENDING_PAYMENT)->value('id');

        $orderData = [
            'user_id' => $cart->user_id,
            'status_id' => $statusId,
            'total_amount' => 0, // Will be updated after items are added
            'shipping_cost' => $shippingCost,
            'fulfillment_status' => FulfillmentStatuses::UNFULFILLED,
        ];

        // Add shipping information if cart requires shipping
        if ($this->cartRequiresShipping($cart)) {
            $orderData['shipping_method_id'] = $checkoutData['shipping_method_id'];
            $orderData['shipping_address_id'] = $checkoutData['shipping_address_id'];
            $orderData['shipping_notes'] = $checkoutData['shipping_notes'] ?? null;
        }

        return Order::create($orderData);
    }

    protected function createOrderItems(Cart $cart, Order $order): void
    {
        foreach ($cart->cartItems as $cartItem) {
            $order->orderItems()->create([
                'product_id' => $cartItem->product_id,
                'product_variant_id' => $cartItem->product_variant_id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->price_snapshot,
            ]);
        }
    }

    protected function updateOrderTotal(Order $order, int $shippingCost): void
    {
        $itemsTotal = $order->orderItems()->sum(DB::raw('price * quantity'));
        $totalAmount = $itemsTotal + $shippingCost;

        $order->update(['total_amount' => $totalAmount]);

        Log::info('Order total updated', [
            'order_id' => $order->id,
            'items_total' => $itemsTotal,
            'shipping_cost' => $shippingCost,
            'total_amount' => $totalAmount,
        ]);
    }

    protected function cartRequiresShipping(Cart $cart): bool
    {
        return $cart->cartItems()
            ->whereHas('product', function ($query) {
                $query->where('requires_shipping', true)
                    ->where('is_virtual', false);
            })
            ->exists();
    }

    protected function calculateCartWeight(Cart $cart): float
    {
        return $cart->cartItems()
            ->with('product')
            ->get()
            ->sum(function ($item) {
                if (!$item->product->requiresShipping()) {
                    return 0;
                }
                return $item->product->getWeightInKg() * $item->quantity;
            });
    }

    public function getCheckoutSummary(Cart $cart, int $shippingMethodId, int $shippingAddressId): array
    {
        $itemsTotal = $cart->getTotalAmountInPennies();
        $shippingCost = $this->calculateShippingCost($cart, $shippingMethodId, $shippingAddressId);
        $totalAmount = $itemsTotal + $shippingCost;

        return [
            'items_total' => $itemsTotal,
            'items_total_formatted' => '£' . number_format($itemsTotal / 100, 2),
            'shipping_cost' => $shippingCost,
            'shipping_cost_formatted' => '£' . number_format($shippingCost / 100, 2),
            'total_amount' => $totalAmount,
            'total_amount_formatted' => '£' . number_format($totalAmount / 100, 2),
            'requires_shipping' => $this->cartRequiresShipping($cart),
        ];
    }
}

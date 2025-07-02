<?php

namespace App\Services\V1\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Resources\V1\CartResource;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartService
{
    use ApiResponses;

    public function getOrCreateCart(Request $request): Cart
    {
        $user = $request->user();

        if ($user) {
            return $user->getOrCreateCart();
        }

        $sessionId = $request->session()->getId();

        $cart = Cart::where('session_id', $sessionId)
            ->active()
            ->first();

        if (!$cart) {
            $cart = Cart::create([
                'session_id' => $sessionId,
                'expires_at' => now()->addDays(7),
            ]);
        }

        return $cart;
    }

    public function getCart(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->load(['cartItems.product.productStatus', 'cartItems.productVariant.productAttribute']);

        return $this->ok('Cart retrieved successfully.', new CartResource($cart));
    }

    public function addToCart(Request $request)
    {
        $data = $request->validated();

        try {
            return DB::transaction(function () use ($request, $data) {
                $cart = $this->getOrCreateCart($request);

                $product = Product::with(['productStatus', 'variants'])->findOrFail($data['product_id']);

                if ($product->productStatus->name !== 'Active') {
                    return $this->error('Product is not available for purchase.', 400);
                }

                $productVariant = null;
                if (isset($data['product_variant_id'])) {
                    $productVariant = ProductVariant::where('product_id', $product->id)
                        ->findOrFail($data['product_variant_id']);
                }

                $currentPrice = $product->price;
                if ($productVariant && $productVariant->additional_price) {
                    $currentPrice += $productVariant->additional_price;
                }

                $availableStock = $productVariant ? $productVariant->quantity : $product->quantity;

                $existingItem = CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $product->id)
                    ->where('product_variant_id', $data['product_variant_id'] ?? null)
                    ->first();

                $requestedQuantity = $data['quantity'];
                $totalQuantity = $existingItem ? $existingItem->quantity + $requestedQuantity : $requestedQuantity;

                if ($totalQuantity > $availableStock) {
                    return $this->error("Insufficient stock. Only {$availableStock} items available.", 400);
                }

                if ($existingItem) {
                    $existingItem->update([
                        'quantity' => $totalQuantity,
                        'price_snapshot' => $currentPrice,
                    ]);

                    Log::info('Cart item quantity updated', [
                        'cart_id' => $cart->id,
                        'cart_item_id' => $existingItem->id,
                        'product_id' => $product->id,
                        'new_quantity' => $totalQuantity,
                    ]);
                } else {
                    $existingItem = CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $product->id,
                        'product_variant_id' => $data['product_variant_id'] ?? null,
                        'quantity' => $requestedQuantity,
                        'price_snapshot' => $currentPrice,
                    ]);

                    Log::info('New cart item added', [
                        'cart_id' => $cart->id,
                        'cart_item_id' => $existingItem->id,
                        'product_id' => $product->id,
                        'quantity' => $requestedQuantity,
                    ]);
                }

                $cart->extendExpiry();

                $cart->load(['cartItems.product.productStatus', 'cartItems.productVariant.productAttribute']);

                return $this->ok('Item added to cart successfully.', new CartResource($cart));
            });
        } catch (\Exception $e) {
            Log::error('Failed to add item to cart', [
                'error' => $e->getMessage(),
                'product_id' => $data['product_id'] ?? null,
                'variant_id' => $data['product_variant_id'] ?? null,
            ]);

            return $this->error('Failed to add item to cart.', 500);
        }
    }

    public function updateCartItem(Request $request, int $cartItemId)
    {
        $data = $request->validated();

        try {
            return DB::transaction(function () use ($request, $cartItemId, $data) {
                $cart = $this->getOrCreateCart($request);

                $cartItem = CartItem::where('cart_id', $cart->id)
                    ->with(['product.productStatus', 'productVariant'])
                    ->findOrFail($cartItemId);

                $availableStock = $cartItem->getAvailableStock();

                if ($data['quantity'] > $availableStock) {
                    return $this->error("Insufficient stock. Only {$availableStock} items available.", 400);
                }

                if ($data['quantity'] <= 0) {
                    $cartItem->delete();

                    Log::info('Cart item removed due to zero quantity', [
                        'cart_id' => $cart->id,
                        'cart_item_id' => $cartItemId,
                    ]);
                } else {
                    $cartItem->update([
                        'quantity' => $data['quantity'],
                        'price_snapshot' => $cartItem->getCurrentProductPrice(),
                    ]);

                    Log::info('Cart item quantity updated', [
                        'cart_id' => $cart->id,
                        'cart_item_id' => $cartItemId,
                        'new_quantity' => $data['quantity'],
                    ]);
                }

                $cart->extendExpiry();

                $cart->load(['cartItems.product.productStatus', 'cartItems.productVariant.productAttribute']);

                return $this->ok('Cart updated successfully.', new CartResource($cart));
            });
        } catch (\Exception $e) {
            Log::error('Failed to update cart item', [
                'error' => $e->getMessage(),
                'cart_item_id' => $cartItemId,
            ]);

            return $this->error('Failed to update cart item.', 500);
        }
    }

    public function removeFromCart(Request $request, int $cartItemId)
    {
        try {
            $cart = $this->getOrCreateCart($request);

            $cartItem = CartItem::where('cart_id', $cart->id)->findOrFail($cartItemId);
            $cartItem->delete();

            Log::info('Cart item removed', [
                'cart_id' => $cart->id,
                'cart_item_id' => $cartItemId,
            ]);

            $cart->load(['cartItems.product.productStatus', 'cartItems.productVariant.productAttribute']);

            return $this->ok('Item removed from cart successfully.', new CartResource($cart));
        } catch (\Exception $e) {
            Log::error('Failed to remove cart item', [
                'error' => $e->getMessage(),
                'cart_item_id' => $cartItemId,
            ]);

            return $this->error('Failed to remove item from cart.', 500);
        }
    }

    public function clearCart(Request $request)
    {
        try {
            $cart = $this->getOrCreateCart($request);
            $cart->clear();

            Log::info('Cart cleared', ['cart_id' => $cart->id]);

            return $this->ok('Cart cleared successfully.', new CartResource($cart));
        } catch (\Exception $e) {
            Log::error('Failed to clear cart', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to clear cart.', 500);
        }
    }

    public function syncCartPrices(Request $request)
    {
        try {
            $cart = $this->getOrCreateCart($request);

            $updatedItems = 0;
            foreach ($cart->cartItems as $item) {
                if ($item->hasPriceChanged()) {
                    $item->updatePriceSnapshot();
                    $updatedItems++;
                }
            }

            Log::info('Cart prices synchronized', [
                'cart_id' => $cart->id,
                'updated_items' => $updatedItems,
            ]);

            $cart->load(['cartItems.product.productStatus', 'cartItems.productVariant.productAttribute']);

            return $this->ok("Cart prices synchronized. {$updatedItems} items updated.", new CartResource($cart));
        } catch (\Exception $e) {
            Log::error('Failed to sync cart prices', [
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to synchronize cart prices.', 500);
        }
    }

    public function mergeGuestCart(string $sessionId, int $userId): void
    {
        try {
            DB::transaction(function () use ($sessionId, $userId) {
                $guestCart = Cart::where('session_id', $sessionId)->active()->first();

                if (!$guestCart || $guestCart->isEmpty()) {
                    return;
                }

                $userCart = Cart::where('user_id', $userId)->active()->first();

                if (!$userCart) {
                    $guestCart->update([
                        'user_id' => $userId,
                        'session_id' => null,
                        'expires_at' => now()->addDays(30),
                    ]);

                    Log::info('Guest cart assigned to user', [
                        'cart_id' => $guestCart->id,
                        'user_id' => $userId,
                    ]);

                    return;
                }

                foreach ($guestCart->cartItems as $guestItem) {
                    $existingItem = CartItem::where('cart_id', $userCart->id)
                        ->where('product_id', $guestItem->product_id)
                        ->where('product_variant_id', $guestItem->product_variant_id)
                        ->first();

                    if ($existingItem) {
                        $availableStock = $guestItem->getAvailableStock();
                        $newQuantity = min($existingItem->quantity + $guestItem->quantity, $availableStock);

                        $existingItem->update([
                            'quantity' => $newQuantity,
                            'price_snapshot' => $guestItem->getCurrentProductPrice(),
                        ]);
                    } else {
                        $guestItem->update(['cart_id' => $userCart->id]);
                    }
                }

                $guestCart->delete();

                Log::info('Guest cart merged with user cart', [
                    'user_cart_id' => $userCart->id,
                    'guest_cart_id' => $guestCart->id,
                    'user_id' => $userId,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Failed to merge guest cart', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);
        }
    }
}

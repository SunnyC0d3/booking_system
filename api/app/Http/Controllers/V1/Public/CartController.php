<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Requests\V1\AddToCartRequest;
use App\Requests\V1\UpdateCartItemRequest;
use App\Services\V1\Cart\CartService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Exception;

class CartController extends Controller
{
    use ApiResponses;

    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Retrieve user's shopping cart
     *
     * Get the current cart contents for the authenticated user or guest session.
     * Returns cart items with product details, pricing information, availability status,
     * and total calculations. Supports both authenticated users and guest sessions.
     *
     * @group Shopping Cart
     * @unauthenticated
     *
     * @response 200 scenario="Cart retrieved successfully" {
     *   "message": "Cart retrieved successfully.",
     *   "data": {
     *     "id": 12,
     *     "user_id": 123,
     *     "total_amount": 15998,
     *     "total_amount_formatted": "£159.98",
     *     "total_items_count": 3,
     *     "expires_at": "2025-02-15T10:30:00.000000Z",
     *     "is_expired": false,
     *     "is_empty": false,
     *     "items": [
     *       {
     *         "id": 45,
     *         "product_id": 15,
     *         "product_variant_id": 24,
     *         "quantity": 2,
     *         "price_snapshot": 7999,
     *         "price_formatted": "£79.99",
     *         "line_total": 15998,
     *         "line_total_formatted": "£159.98",
     *         "current_price": 7999,
     *         "has_price_changed": false,
     *         "is_available": true,
     *         "available_stock": 20,
     *         "product": {
     *           "id": 15,
     *           "name": "Wireless Bluetooth Headphones",
     *           "description": "Premium quality wireless headphones",
     *           "price": 7499,
     *           "price_formatted": "£74.99",
     *           "featured_image": "https://yourapi.com/storage/products/headphones.jpg",
     *           "status": "Active"
     *         },
     *         "product_variant": {
     *           "id": 24,
     *           "value": "White",
     *           "additional_price": 500,
     *           "additional_price_formatted": "£5.00",
     *           "quantity": 20,
     *           "product_attribute": {
     *             "id": 1,
     *             "name": "Color"
     *           }
     *         },
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:30:00.000000Z"
     *       }
     *     ],
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:35:00.000000Z"
     *   }
     * }
     *
     * @response 200 scenario="Empty cart" {
     *   "message": "Cart retrieved successfully.",
     *   "data": {
     *     "id": 13,
     *     "user_id": 123,
     *     "total_amount": 0,
     *     "total_amount_formatted": "£0.00",
     *     "total_items_count": 0,
     *     "expires_at": "2025-02-15T10:30:00.000000Z",
     *     "is_expired": false,
     *     "is_empty": true,
     *     "items": [],
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->cartService->getCart($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Add item to shopping cart
     *
     * Add a product (with optional variant) to the user's shopping cart. If the item already exists,
     * the quantities will be combined. Validates product availability, stock levels, and pricing.
     * Supports both authenticated users and guest sessions.
     *
     * @group Shopping Cart
     * @unauthenticated
     *
     * @bodyParam product_id integer required The ID of the product to add to cart. Example: 15
     * @bodyParam product_variant_id integer optional The ID of the product variant (for products with variants like color, size). Example: 24
     * @bodyParam quantity integer required The quantity to add to cart (1-999). Example: 2
     *
     * @response 200 scenario="Item added successfully" {
     *   "message": "Item added to cart successfully.",
     *   "data": {
     *     "id": 12,
     *     "user_id": 123,
     *     "total_amount": 15998,
     *     "total_amount_formatted": "£159.98",
     *     "total_items_count": 2,
     *     "expires_at": "2025-02-15T10:30:00.000000Z",
     *     "is_expired": false,
     *     "is_empty": false,
     *     "items": [
     *       {
     *         "id": 45,
     *         "product_id": 15,
     *         "product_variant_id": 24,
     *         "quantity": 2,
     *         "price_snapshot": 7999,
     *         "price_formatted": "£79.99",
     *         "line_total": 15998,
     *         "line_total_formatted": "£159.98",
     *         "current_price": 7999,
     *         "has_price_changed": false,
     *         "is_available": true,
     *         "available_stock": 18,
     *         "product": {
     *           "id": 15,
     *           "name": "Wireless Bluetooth Headphones",
     *           "status": "Active"
     *         },
     *         "product_variant": {
     *           "id": 24,
     *           "value": "White",
     *           "additional_price": 500,
     *           "quantity": 18
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 400 scenario="Product not available" {
     *   "message": "Product is not available for purchase."
     * }
     *
     * @response 400 scenario="Insufficient stock" {
     *   "message": "Insufficient stock. Only 5 items available."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "Please select a product to add to cart.",
     *     "Quantity must be at least 1.",
     *     "The selected product variant does not belong to the specified product."
     *   ]
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "The selected product is not available."
     * }
     */
    public function store(AddToCartRequest $request)
    {
        try {
            return $this->cartService->addToCart($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update cart item quantity
     *
     * Update the quantity of a specific item in the cart. Setting quantity to 0 will remove
     * the item from the cart. Validates stock availability and updates pricing to current
     * product price if it has changed since adding to cart.
     *
     * @group Shopping Cart
     * @unauthenticated
     *
     * @urlParam cartItem integer required The ID of the cart item to update. Example: 45
     *
     * @bodyParam quantity integer required The new quantity for the cart item (0 to remove). Example: 3
     *
     * @response 200 scenario="Cart item updated successfully" {
     *   "message": "Cart updated successfully.",
     *   "data": {
     *     "id": 12,
     *     "user_id": 123,
     *     "total_amount": 23997,
     *     "total_amount_formatted": "£239.97",
     *     "total_items_count": 3,
     *     "expires_at": "2025-02-15T10:30:00.000000Z",
     *     "is_expired": false,
     *     "is_empty": false,
     *     "items": [
     *       {
     *         "id": 45,
     *         "product_id": 15,
     *         "product_variant_id": 24,
     *         "quantity": 3,
     *         "price_snapshot": 7999,
     *         "price_formatted": "£79.99",
     *         "line_total": 23997,
     *         "line_total_formatted": "£239.97",
     *         "current_price": 7999,
     *         "has_price_changed": false,
     *         "is_available": true,
     *         "available_stock": 17
     *       }
     *     ]
     *   }
     * }
     *
     * @response 200 scenario="Item removed (quantity set to 0)" {
     *   "message": "Cart updated successfully.",
     *   "data": {
     *     "id": 12,
     *     "total_amount": 0,
     *     "total_amount_formatted": "£0.00",
     *     "total_items_count": 0,
     *     "is_empty": true,
     *     "items": []
     *   }
     * }
     *
     * @response 400 scenario="Insufficient stock" {
     *   "message": "Insufficient stock. Only 2 items available."
     * }
     *
     * @response 404 scenario="Cart item not found" {
     *   "message": "No query results for model [App\\Models\\CartItem] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "Please specify the quantity.",
     *     "Cannot have more than 999 items of the same product."
     *   ]
     * }
     */
    public function update(UpdateCartItemRequest $request, int $cartItem)
    {
        try {
            return $this->cartService->updateCartItem($request, $cartItem);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Remove item from cart
     *
     * Remove a specific item completely from the shopping cart. This action cannot be undone.
     * The cart totals and item count will be automatically recalculated.
     *
     * @group Shopping Cart
     * @unauthenticated
     *
     * @urlParam cartItem integer required The ID of the cart item to remove. Example: 45
     *
     * @response 200 scenario="Item removed successfully" {
     *   "message": "Item removed from cart successfully.",
     *   "data": {
     *     "id": 12,
     *     "user_id": 123,
     *     "total_amount": 7999,
     *     "total_amount_formatted": "£79.99",
     *     "total_items_count": 1,
     *     "expires_at": "2025-02-15T10:30:00.000000Z",
     *     "is_expired": false,
     *     "is_empty": false,
     *     "items": [
     *       {
     *         "id": 46,
     *         "product_id": 22,
     *         "quantity": 1,
     *         "line_total": 7999,
     *         "line_total_formatted": "£79.99"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 404 scenario="Cart item not found" {
     *   "message": "No query results for model [App\\Models\\CartItem] 999"
     * }
     */
    public function destroy(Request $request, int $cartItem)
    {
        try {
            return $this->cartService->removeFromCart($request, $cartItem);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Clear entire cart
     *
     * Remove all items from the shopping cart. This action cannot be undone and will result
     * in an empty cart. Useful for starting over or clearing expired/unavailable items.
     *
     * @group Shopping Cart
     * @unauthenticated
     *
     * @response 200 scenario="Cart cleared successfully" {
     *   "message": "Cart cleared successfully.",
     *   "data": {
     *     "id": 12,
     *     "user_id": 123,
     *     "total_amount": 0,
     *     "total_amount_formatted": "£0.00",
     *     "total_items_count": 0,
     *     "expires_at": "2025-02-15T10:30:00.000000Z",
     *     "is_expired": false,
     *     "is_empty": true,
     *     "items": [],
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:45:00.000000Z"
     *   }
     * }
     */
    public function clear(Request $request)
    {
        try {
            return $this->cartService->clearCart($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Synchronize cart prices
     *
     * Update all cart item prices to match current product prices. This is useful when
     * product prices have changed since items were added to the cart. Returns information
     * about which items had price changes.
     *
     * @group Shopping Cart
     * @unauthenticated
     *
     * @response 200 scenario="Prices synchronized successfully" {
     *   "message": "Cart prices synchronized. 2 items updated.",
     *   "data": {
     *     "id": 12,
     *     "user_id": 123,
     *     "total_amount": 17998,
     *     "total_amount_formatted": "£179.98",
     *     "total_items_count": 2,
     *     "items": [
     *       {
     *         "id": 45,
     *         "product_id": 15,
     *         "quantity": 2,
     *         "price_snapshot": 8999,
     *         "price_formatted": "£89.99",
     *         "line_total": 17998,
     *         "line_total_formatted": "£179.98",
     *         "current_price": 8999,
     *         "has_price_changed": false,
     *         "price_change": 1000
     *       }
     *     ]
     *   }
     * }
     *
     * @response 200 scenario="No price changes" {
     *   "message": "Cart prices synchronized. 0 items updated.",
     *   "data": {
     *     "id": 12,
     *     "total_amount": 15998,
     *     "total_amount_formatted": "£159.98",
     *     "items": []
     *   }
     * }
     */
    public function syncPrices(Request $request)
    {
        try {
            return $this->cartService->syncCartPrices($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

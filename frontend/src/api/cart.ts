import { api } from './client';
import {
    Cart,
    CartItem,
    AddToCartRequest,
    UpdateCartItemRequest
} from '@/types/api';

/**
 * Cart API Client
 * Handles all cart-related API operations
 */
export class CartApi {
    // Get current cart
    async getCart(): Promise<Cart> {
        const response = await api.get<{ data: Cart }>('/cart');
        return response.data.data;
    }

    // Add item to cart
    async addToCart(data: AddToCartRequest): Promise<Cart> {
        const response = await api.post<{ data: Cart }>('/cart/items', data);
        return response.data.data;
    }

    // Update cart item quantity
    async updateCartItem(cartItemId: number, data: UpdateCartItemRequest): Promise<Cart> {
        const response = await api.post<{ data: Cart }>(`/cart/items/${cartItemId}`, data);
        return response.data.data;
    }

    // Remove item from cart
    async removeFromCart(cartItemId: number): Promise<Cart> {
        const response = await api.delete<{ data: Cart }>(`/cart/items/${cartItemId}`);
        return response.data.data;
    }

    // Clear entire cart
    async clearCart(): Promise<Cart> {
        const response = await api.delete<{ data: Cart }>('/cart/clear');
        return response.data.data;
    }

    // Sync cart prices (update to current product prices)
    async syncCartPrices(): Promise<Cart> {
        const response = await api.post<{ data: Cart }>('/cart/sync-prices');
        return response.data.data;
    }

    // Quick add to cart (with default quantity of 1)
    async quickAddToCart(productId: number, variantId?: number): Promise<Cart> {
        return this.addToCart({
            product_id: productId,
            product_variant_id: variantId || null,
            quantity: 1,
        });
    }

    // Bulk add multiple items to cart
    async bulkAddToCart(items: AddToCartRequest[]): Promise<Cart> {
        // Since the API doesn't have bulk add, we'll add items sequentially
        let cart: Cart | null = null;

        for (const item of items) {
            cart = await this.addToCart(item);
        }

        return cart as Cart;
    }

    // Get cart item count (utility method)
    async getCartItemCount(): Promise<number> {
        try {
            const cart = await this.getCart();
            return cart.total_items_count;
        } catch (error) {
            return 0;
        }
    }

    // Get cart total (utility method)
    async getCartTotal(): Promise<{ amount: number; formatted: string }> {
        try {
            const cart = await this.getCart();
            return {
                amount: cart.total_amount,
                formatted: cart.total_amount_formatted,
            };
        } catch (error) {
            return { amount: 0, formatted: '£0.00' };
        }
    }

    // Check if item is in cart
    async isItemInCart(productId: number, variantId?: number): Promise<boolean> {
        try {
            const cart = await this.getCart();
            return cart.items?.some(item =>
                item.product_id === productId &&
                item.product_variant_id === (variantId || null)
            ) || false;
        } catch (error) {
            return false;
        }
    }
}

// Export singleton instance
export const cartApi = new CartApi();
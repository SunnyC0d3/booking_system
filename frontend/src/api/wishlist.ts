import { api } from '@/api/client';
import { Wishlist, AddToWishlistRequest } from '@/types/api';

export const wishlistApi = {
    // Get user's wishlist
    getWishlist: async (): Promise<Wishlist> => {
        const response = await api.get('/wishlist');
        return response.data.data;
    },

    // Add item to wishlist
    addToWishlist: async (item: AddToWishlistRequest): Promise<Wishlist> => {
        const response = await api.post('/wishlist/items', item);
        return response.data.data;
    },

    // Remove item from wishlist
    removeFromWishlist: async (wishlistItemId: number): Promise<Wishlist> => {
        const response = await api.delete(`/wishlist/items/${wishlistItemId}`);
        return response.data.data;
    },

    // Clear entire wishlist
    clearWishlist: async (): Promise<Wishlist> => {
        const response = await api.delete('/wishlist');
        return response.data.data;
    },

    // Move item from wishlist to cart
    moveToCart: async (wishlistItemId: number, quantity: number = 1): Promise<void> => {
        await api.post(`/wishlist/items/${wishlistItemId}/move-to-cart`, { quantity });
    },

    // Bulk add items to wishlist
    bulkAddToWishlist: async (items: AddToWishlistRequest[]): Promise<Wishlist> => {
        const response = await api.post('/wishlist/bulk-add', { items });
        return response.data.data;
    },

    // Share wishlist (generate shareable link)
    shareWishlist: async (): Promise<{ share_url: string; expires_at: string }> => {
        const response = await api.post('/wishlist/share');
        return response.data.data;
    },

    // Get shared wishlist (public)
    getSharedWishlist: async (shareToken: string): Promise<Wishlist> => {
        const response = await api.get(`/wishlist/shared/${shareToken}`);
        return response.data.data;
    },
};
import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import { toast } from 'sonner';
import { wishlistApi } from '@/api/wishlist';
import { Wishlist, WishlistItem, AddToWishlistRequest } from '@/types/api';

interface WishlistState {
    wishlist: Wishlist | null;
    isLoading: boolean;
    error: string | null;
    isOpen: boolean; // For wishlist sidebar/modal
    optimisticItems: WishlistItem[]; // For optimistic updates
}

interface WishlistActions {
    // Wishlist operations
    fetchWishlist: () => Promise<void>;
    addToWishlist: (item: AddToWishlistRequest) => Promise<void>;
    removeFromWishlist: (wishlistItemId: number) => Promise<void>;
    clearWishlist: () => Promise<void>;
    moveToCart: (wishlistItemId: number, quantity?: number) => Promise<void>;

    // Optimistic updates
    optimisticAddToWishlist: (item: AddToWishlistRequest & { product: any }) => void;
    optimisticRemoveItem: (wishlistItemId: number) => void;

    // UI state
    openWishlist: () => void;
    closeWishlist: () => void;
    toggleWishlist: () => void;

    // Utility
    getItemCount: () => number;
    isItemInWishlist: (productId: number, variantId?: number) => boolean;
    getWishlistItem: (productId: number, variantId?: number) => WishlistItem | undefined;

    // State management
    setLoading: (loading: boolean) => void;
    setError: (error: string | null) => void;
    resetWishlist: () => void;
}

const initialState: WishlistState = {
    wishlist: null,
    isLoading: false,
    error: null,
    isOpen: false,
    optimisticItems: [],
};

export const useWishlistStore = create<WishlistState & WishlistActions>()(
    persist(
        immer((set, get) => ({
            ...initialState,

            // Wishlist operations
            fetchWishlist: async () => {
                set((draft) => {
                    draft.isLoading = true;
                    draft.error = null;
                });

                try {
                    const wishlist = await wishlistApi.getWishlist();
                    set((draft) => {
                        draft.wishlist = wishlist;
                        draft.optimisticItems = [];
                        draft.isLoading = false;
                    });
                } catch (error: any) {
                    set((draft) => {
                        draft.error = error.message || 'Failed to fetch wishlist';
                        draft.isLoading = false;
                    });
                }
            },

            addToWishlist: async (item: AddToWishlistRequest) => {
                // Check if item already exists
                if (get().isItemInWishlist(item.product_id, item.product_variant_id)) {
                    toast.info('Item is already in your wishlist');
                    return;
                }

                // Optimistic update first
                get().optimisticAddToWishlist({ ...item, product: null });

                try {
                    const wishlist = await wishlistApi.addToWishlist(item);
                    set((draft) => {
                        draft.wishlist = wishlist;
                        draft.optimisticItems = [];
                        draft.error = null;
                    });

                    toast.success('Item added to wishlist!');
                } catch (error: any) {
                    // Revert optimistic update
                    set((draft) => {
                        draft.optimisticItems = [];
                        draft.error = error.message || 'Failed to add item to wishlist';
                    });

                    toast.error(error.message || 'Failed to add item to wishlist');
                }
            },

            removeFromWishlist: async (wishlistItemId: number) => {
                // Optimistic update
                get().optimisticRemoveItem(wishlistItemId);

                try {
                    const wishlist = await wishlistApi.removeFromWishlist(wishlistItemId);
                    set((draft) => {
                        draft.wishlist = wishlist;
                        draft.optimisticItems = [];
                        draft.error = null;
                    });

                    toast.success('Item removed from wishlist');
                } catch (error: any) {
                    // Revert and refetch
                    await get().fetchWishlist();
                    toast.error(error.message || 'Failed to remove item');
                }
            },

            clearWishlist: async () => {
                set((draft) => {
                    draft.isLoading = true;
                    draft.error = null;
                });

                try {
                    const wishlist = await wishlistApi.clearWishlist();
                    set((draft) => {
                        draft.wishlist = wishlist;
                        draft.optimisticItems = [];
                        draft.isLoading = false;
                    });

                    toast.success('Wishlist cleared');
                } catch (error: any) {
                    set((draft) => {
                        draft.error = error.message || 'Failed to clear wishlist';
                        draft.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to clear wishlist');
                }
            },

            moveToCart: async (wishlistItemId: number, quantity = 1) => {
                try {
                    await wishlistApi.moveToCart(wishlistItemId, quantity);

                    // Remove from wishlist after successful move
                    await get().removeFromWishlist(wishlistItemId);

                    toast.success('Item moved to cart!');
                } catch (error: any) {
                    toast.error(error.message || 'Failed to move item to cart');
                }
            },

            // Optimistic updates
            optimisticAddToWishlist: (item) => {
                set((draft) => {
                    const optimisticItem: WishlistItem = {
                        id: Date.now(), // Temporary ID
                        wishlist_id: draft.wishlist?.id || 0,
                        product_id: item.product_id,
                        product_variant_id: item.product_variant_id,
                        product: item.product,
                        product_variant: null,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    };

                    draft.optimisticItems.push(optimisticItem);
                });
            },

            optimisticRemoveItem: (wishlistItemId) => {
                set((draft) => {
                    // Remove from actual wishlist
                    if (draft.wishlist?.items) {
                        draft.wishlist.items = draft.wishlist.items.filter(i => i.id !== wishlistItemId);
                    }

                    // Remove from optimistic items
                    draft.optimisticItems = draft.optimisticItems.filter(i => i.id !== wishlistItemId);
                });
            },

            // UI state
            openWishlist: () => {
                set((draft) => {
                    draft.isOpen = true;
                });
            },

            closeWishlist: () => {
                set((draft) => {
                    draft.isOpen = false;
                });
            },

            toggleWishlist: () => {
                set((draft) => {
                    draft.isOpen = !draft.isOpen;
                });
            },

            // Utility functions
            getItemCount: () => {
                const state = get();
                const wishlistItems = state.wishlist?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...wishlistItems, ...optimisticItems];

                return allItems.length;
            },

            isItemInWishlist: (productId, variantId) => {
                const state = get();
                const wishlistItems = state.wishlist?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...wishlistItems, ...optimisticItems];

                return allItems.some(item =>
                    item.product_id === productId &&
                    item.product_variant_id === variantId
                );
            },

            getWishlistItem: (productId, variantId) => {
                const state = get();
                const wishlistItems = state.wishlist?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...wishlistItems, ...optimisticItems];

                return allItems.find(item =>
                    item.product_id === productId &&
                    item.product_variant_id === variantId
                );
            },

            // State management
            setLoading: (loading) => {
                set((draft) => {
                    draft.isLoading = loading;
                });
            },

            setError: (error) => {
                set((draft) => {
                    draft.error = error;
                });
            },

            resetWishlist: () => {
                set(() => initialState);
            },
        })),
        {
            name: 'wishlist-storage',
            storage: createJSONStorage(() => localStorage),
            partialize: (state) => ({
                wishlist: state.wishlist,
                // Don't persist optimistic items or UI state
            }),
        }
    )
);
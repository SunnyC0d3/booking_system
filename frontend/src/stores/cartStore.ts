import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import { toast } from 'sonner';
import { cartApi } from '@/api/cart';
import { Cart, CartItem, AddToCartRequest } from '@/types/api';

interface CartState {
    cart: Cart | null;
    isLoading: boolean;
    error: string | null;
    isOpen: boolean; // For cart sidebar
    optimisticItems: CartItem[]; // For optimistic updates
}

interface CartActions {
    // Cart operations
    fetchCart: () => Promise<void>;
    addToCart: (item: AddToCartRequest) => Promise<void>;
    updateCartItem: (cartItemId: number, quantity: number) => Promise<void>;
    removeFromCart: (cartItemId: number) => Promise<void>;
    clearCart: () => Promise<void>;
    syncCartPrices: () => Promise<void>;

    // Optimistic updates
    optimisticAddToCart: (item: AddToCartRequest & { product?: any }) => void;
    optimisticUpdateItem: (cartItemId: number, quantity: number) => void;
    optimisticRemoveItem: (cartItemId: number) => void;

    // UI state
    openCart: () => void;
    closeCart: () => void;
    toggleCart: () => void;

    // Utility
    getItemCount: () => number;
    getCartTotal: () => number;
    isItemInCart: (productId: number, variantId?: number | null) => boolean;
    getCartItem: (productId: number, variantId?: number | null) => CartItem | undefined;

    // State management
    setLoading: (loading: boolean) => void;
    setError: (error: string | null) => void;
    resetCart: () => void;
}

const initialState: CartState = {
    cart: null,
    isLoading: false,
    error: null,
    isOpen: false,
    optimisticItems: [],
};

export const useCartStore = create<CartState & CartActions>()(
    persist(
        immer((set, get) => ({
            ...initialState,

            // Cart operations
            fetchCart: async () => {
                set((draft) => {
                    draft.isLoading = true;
                    draft.error = null;
                });

                try {
                    const cart = await cartApi.getCart();
                    set((draft) => {
                        draft.cart = cart;
                        draft.optimisticItems = [];
                        draft.isLoading = false;
                    });
                } catch (error: any) {
                    set((draft) => {
                        draft.error = error.message || 'Failed to fetch cart';
                        draft.isLoading = false;
                    });
                }
            },

            addToCart: async (item: AddToCartRequest) => {
                // Optimistic update first
                get().optimisticAddToCart({ ...item, product: undefined });

                try {
                    const cart = await cartApi.addToCart(item);
                    set((draft) => {
                        draft.cart = cart;
                        draft.optimisticItems = [];
                        draft.error = null;
                    });

                    toast.success('Item added to cart!');
                } catch (error: any) {
                    // Revert optimistic update
                    set((draft) => {
                        draft.optimisticItems = [];
                        draft.error = error.message || 'Failed to add item to cart';
                    });

                    toast.error(error.message || 'Failed to add item to cart');
                }
            },

            updateCartItem: async (cartItemId: number, quantity: number) => {
                // Optimistic update
                get().optimisticUpdateItem(cartItemId, quantity);

                try {
                    const cart = await cartApi.updateCartItem(cartItemId, { quantity });
                    set((draft) => {
                        draft.cart = cart;
                        draft.optimisticItems = [];
                        draft.error = null;
                    });
                } catch (error: any) {
                    // Revert and refetch
                    await get().fetchCart();
                    toast.error(error.message || 'Failed to update item');
                }
            },

            removeFromCart: async (cartItemId: number) => {
                // Optimistic update
                get().optimisticRemoveItem(cartItemId);

                try {
                    const cart = await cartApi.removeFromCart(cartItemId);
                    set((draft) => {
                        draft.cart = cart;
                        draft.optimisticItems = [];
                        draft.error = null;
                    });

                    toast.success('Item removed from cart');
                } catch (error: any) {
                    // Revert and refetch
                    await get().fetchCart();
                    toast.error(error.message || 'Failed to remove item');
                }
            },

            clearCart: async () => {
                set((draft) => {
                    draft.isLoading = true;
                    draft.error = null;
                });

                try {
                    const cart = await cartApi.clearCart();
                    set((draft) => {
                        draft.cart = cart;
                        draft.optimisticItems = [];
                        draft.isLoading = false;
                    });

                    toast.success('Cart cleared');
                } catch (error: any) {
                    set((draft) => {
                        draft.error = error.message || 'Failed to clear cart';
                        draft.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to clear cart');
                }
            },

            syncCartPrices: async () => {
                try {
                    const cart = await cartApi.syncCartPrices();
                    set((draft) => {
                        draft.cart = cart;
                        draft.error = null;
                    });

                    toast.success('Cart prices updated');
                } catch (error: any) {
                    toast.error(error.message || 'Failed to sync prices');
                }
            },

            // Optimistic updates
            optimisticAddToCart: (item) => {
                set((draft) => {
                    // Create base optimistic item
                    const baseItem: CartItem = {
                        id: Date.now(), // Temporary ID
                        product_id: item.product_id,
                        product_variant_id: item.product_variant_id || null,
                        quantity: item.quantity,
                        price_snapshot: item.product?.price || 0,
                        price_formatted: item.product?.price_formatted || '£0.00',
                        line_total: (item.product?.price || 0) * item.quantity,
                        line_total_formatted: `£${((item.product?.price || 0) * item.quantity).toFixed(2)}`,
                        current_price: item.product?.price || 0,
                        has_price_changed: false,
                        is_available: true,
                        available_stock: 999, // Assume available for optimistic update
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    };

                    // Only add product if it exists
                    if (item.product) {
                        baseItem.product = {
                            id: item.product.id,
                            name: item.product.name,
                            description: item.product.description,
                            price: item.product.price,
                            price_formatted: item.product.price_formatted,
                            featured_image: item.product.featured_image,
                            status: item.product.status,
                        };
                    }

                    // Only add product_variant if variant_id exists
                    if (item.product_variant_id) {
                        baseItem.product_variant = {
                            id: item.product_variant_id,
                            value: 'Default',
                            quantity: item.quantity,
                        };

                        // Only add optional properties if they have meaningful values
                        // Leave them undefined to satisfy exactOptionalPropertyTypes
                    }

                    draft.optimisticItems.push(baseItem);
                });
            },

            optimisticUpdateItem: (cartItemId, quantity) => {
                set((draft) => {
                    // Update in actual cart if exists
                    if (draft.cart?.items) {
                        const item = draft.cart.items.find(i => i.id === cartItemId);
                        if (item) {
                            item.quantity = quantity;
                            item.line_total = item.price_snapshot * quantity;
                            item.line_total_formatted = `£${(item.price_snapshot * quantity).toFixed(2)}`;
                        }
                    }

                    // Update in optimistic items
                    const optimisticItem = draft.optimisticItems.find(i => i.id === cartItemId);
                    if (optimisticItem) {
                        optimisticItem.quantity = quantity;
                        optimisticItem.line_total = optimisticItem.price_snapshot * quantity;
                        optimisticItem.line_total_formatted = `£${(optimisticItem.price_snapshot * quantity).toFixed(2)}`;
                    }
                });
            },

            optimisticRemoveItem: (cartItemId) => {
                set((draft) => {
                    // Remove from actual cart
                    if (draft.cart?.items) {
                        draft.cart.items = draft.cart.items.filter(i => i.id !== cartItemId);
                    }

                    // Remove from optimistic items
                    draft.optimisticItems = draft.optimisticItems.filter(i => i.id !== cartItemId);
                });
            },

            // UI state
            openCart: () => {
                set((draft) => {
                    draft.isOpen = true;
                });
            },

            closeCart: () => {
                set((draft) => {
                    draft.isOpen = false;
                });
            },

            toggleCart: () => {
                set((draft) => {
                    draft.isOpen = !draft.isOpen;
                });
            },

            // Utility functions
            getItemCount: () => {
                const state = get();
                const cartItems = state.cart?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...cartItems, ...optimisticItems];

                return allItems.reduce((total, item) => total + item.quantity, 0);
            },

            getCartTotal: () => {
                const state = get();
                const cartItems = state.cart?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...cartItems, ...optimisticItems];

                return allItems.reduce((total, item) => total + item.line_total, 0);
            },

            isItemInCart: (productId, variantId = null) => {
                const state = get();
                const cartItems = state.cart?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...cartItems, ...optimisticItems];

                return allItems.some(item =>
                    item.product_id === productId &&
                    item.product_variant_id === variantId
                );
            },

            getCartItem: (productId, variantId = null) => {
                const state = get();
                const cartItems = state.cart?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...cartItems, ...optimisticItems];

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

            resetCart: () => {
                set(() => initialState);
            },
        })),
        {
            name: 'cart-storage',
            storage: createJSONStorage(() => localStorage),
            partialize: (state) => ({
                cart: state.cart,
                // Don't persist optimistic items or UI state
            }),
        }
    )
);

// Enhanced selectors with better type safety and memoization
export const useCartItems = () => useCartStore((state) => {
    const cartItems = state.cart?.items || [];
    const optimisticItems = state.optimisticItems;
    return [...cartItems, ...optimisticItems];
});

export const useCartTotal = () => useCartStore((state) => {
    return state.getCartTotal();
});

export const useCartItemCount = () => useCartStore((state) => {
    return state.getItemCount();
});

// Additional utility selectors
export const useCartSummary = () => useCartStore((state) => ({
    total: state.getCartTotal(),
    itemCount: state.getItemCount(),
    isEmpty: state.cart?.is_empty ?? true,
    isLoading: state.isLoading,
    error: state.error,
}));

export const useCartUI = () => useCartStore((state) => ({
    isOpen: state.isOpen,
    openCart: state.openCart,
    closeCart: state.closeCart,
    toggleCart: state.toggleCart,
}));

export const useCartActions = () => useCartStore((state) => ({
    addToCart: state.addToCart,
    updateCartItem: state.updateCartItem,
    removeFromCart: state.removeFromCart,
    clearCart: state.clearCart,
    syncCartPrices: state.syncCartPrices,
    fetchCart: state.fetchCart,
}));

// Hook for checking if a specific product is in cart
export const useIsInCart = (productId: number, variantId?: number | null) =>
    useCartStore((state) => state.isItemInCart(productId, variantId));

// Hook for getting a specific cart item
export const useCartItem = (productId: number, variantId?: number | null) =>
    useCartStore((state) => state.getCartItem(productId, variantId));
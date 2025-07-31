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
    optimisticAddToCart: (item: AddToCartRequest & { product: any }) => void;
    optimisticUpdateItem: (cartItemId: number, quantity: number) => void;
    optimisticRemoveItem: (cartItemId: number) => void;

    // UI state
    openCart: () => void;
    closeCart: () => void;
    toggleCart: () => void;

    // Utility
    getItemCount: () => number;
    getCartTotal: () => number;
    isItemInCart: (productId: number, variantId?: number) => boolean;
    getCartItem: (productId: number, variantId?: number) => CartItem | undefined;

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
                get().optimisticAddToCart({ ...item, product: null });

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
                    const optimisticItem: CartItem = {
                        id: Date.now(), // Temporary ID
                        cart_id: draft.cart?.id || 0,
                        product_id: item.product_id,
                        product_variant_id: item.product_variant_id,
                        quantity: item.quantity,
                        unit_price: item.product?.price || 0,
                        total_price: (item.product?.price || 0) * item.quantity,
                        product: item.product,
                        product_variant: null,
                        created_at: new Date().toISOString(),
                        updated_at: new Date().toISOString(),
                    };

                    draft.optimisticItems.push(optimisticItem);
                });
            },

            optimisticUpdateItem: (cartItemId, quantity) => {
                set((draft) => {
                    // Update in actual cart if exists
                    if (draft.cart?.items) {
                        const item = draft.cart.items.find(i => i.id === cartItemId);
                        if (item) {
                            item.quantity = quantity;
                            item.total_price = item.unit_price * quantity;
                        }
                    }

                    // Update in optimistic items
                    const optimisticItem = draft.optimisticItems.find(i => i.id === cartItemId);
                    if (optimisticItem) {
                        optimisticItem.quantity = quantity;
                        optimisticItem.total_price = optimisticItem.unit_price * quantity;
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

                return allItems.reduce((total, item) => total + item.total_price, 0);
            },

            isItemInCart: (productId, variantId) => {
                const state = get();
                const cartItems = state.cart?.items || [];
                const optimisticItems = state.optimisticItems;
                const allItems = [...cartItems, ...optimisticItems];

                return allItems.some(item =>
                    item.product_id === productId &&
                    item.product_variant_id === variantId
                );
            },

            getCartItem: (productId, variantId) => {
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

export const useCartItems = () => useCartStore((state) => state.cart?.items || []);
export const useCartTotal = () => useCartStore((state) => state.getCartTotal());
export const useCartItemCount = () => useCartStore((state) => state.getItemCount());
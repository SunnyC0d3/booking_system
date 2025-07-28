import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import { toast } from 'sonner';
import { cartApi } from '@/api/cart';
import { Cart, CartItem, AddToCartRequest, UpdateCartItemRequest } from '@/types/api';

// Cart State Interface
export interface CartState {
    cart: Cart | null;
    isLoading: boolean;
    error: string | null;
    isOpen: boolean; // For cart sidebar/drawer
    lastUpdated: number | null;
}

// Cart Actions Interface
export interface CartActions {
    // Cart Management
    fetchCart: () => Promise<void>;
    addToCart: (data: AddToCartRequest) => Promise<void>;
    updateCartItem: (cartItemId: number, quantity: number) => Promise<void>;
    removeFromCart: (cartItemId: number) => Promise<void>;
    clearCart: () => Promise<void>;
    syncCartPrices: () => Promise<void>;

    // Quick Actions
    quickAddToCart: (productId: number, variantId?: number) => Promise<void>;
    incrementItem: (cartItemId: number) => Promise<void>;
    decrementItem: (cartItemId: number) => Promise<void>;

    // UI State
    openCart: () => void;
    closeCart: () => void;
    toggleCart: () => void;

    // Utility
    setLoading: (loading: boolean) => void;
    setError: (error: string | null) => void;
    clearError: () => void;
    getItemQuantity: (productId: number, variantId?: number) => number;
    getCartTotal: () => { amount: number; formatted: string };
    getCartItemCount: () => number;
    isItemInCart: (productId: number, variantId?: number) => boolean;
}

// Initial state
const initialState: CartState = {
    cart: null,
    isLoading: false,
    error: null,
    isOpen: false,
    lastUpdated: null,
};

// Create the cart store
export const useCartStore = create<CartState & CartActions>()(
    persist(
        immer((set, get) => ({
            ...initialState,

            // Fetch cart from API
            fetchCart: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const cart = await cartApi.getCart();

                    set((state) => {
                        state.cart = cart;
                        state.isLoading = false;
                        state.lastUpdated = Date.now();
                    });

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to fetch cart';
                        state.isLoading = false;
                    });
                    console.error('Failed to fetch cart:', error);
                }
            },

            // Add item to cart
            addToCart: async (data: AddToCartRequest) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const cart = await cartApi.addToCart(data);

                    set((state) => {
                        state.cart = cart;
                        state.isLoading = false;
                        state.lastUpdated = Date.now();
                    });

                    toast.success('Added to cart successfully!');

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to add to cart';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to add to cart');
                    throw error;
                }
            },

            // Update cart item quantity
            updateCartItem: async (cartItemId: number, quantity: number) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const cart = await cartApi.updateCartItem(cartItemId, { quantity });

                    set((state) => {
                        state.cart = cart;
                        state.isLoading = false;
                        state.lastUpdated = Date.now();
                    });

                    toast.success('Cart updated successfully!');

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to update cart';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to update cart');
                    throw error;
                }
            },

            // Remove item from cart
            removeFromCart: async (cartItemId: number) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const cart = await cartApi.removeFromCart(cartItemId);

                    set((state) => {
                        state.cart = cart;
                        state.isLoading = false;
                        state.lastUpdated = Date.now();
                    });

                    toast.success('Item removed from cart');

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to remove item';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to remove item');
                    throw error;
                }
            },

            // Clear entire cart
            clearCart: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const cart = await cartApi.clearCart();

                    set((state) => {
                        state.cart = cart;
                        state.isLoading = false;
                        state.lastUpdated = Date.now();
                    });

                    toast.success('Cart cleared successfully');

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to clear cart';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to clear cart');
                    throw error;
                }
            },

            // Sync cart prices
            syncCartPrices: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const cart = await cartApi.syncCartPrices();

                    set((state) => {
                        state.cart = cart;
                        state.isLoading = false;
                        state.lastUpdated = Date.now();
                    });

                    toast.success('Cart prices updated');

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to sync prices';
                        state.isLoading = false;
                    });

                    toast.error(error.message || 'Failed to sync prices');
                    throw error;
                }
            },

            // Quick add to cart
            quickAddToCart: async (productId: number, variantId?: number) => {
                await get().addToCart({
                    product_id: productId,
                    product_variant_id: variantId || null,
                    quantity: 1,
                });
            },

            // Increment item quantity
            incrementItem: async (cartItemId: number) => {
                const { cart } = get();
                if (!cart) return;

                const item = cart.items?.find(item => item.id === cartItemId);
                if (!item) return;

                await get().updateCartItem(cartItemId, item.quantity + 1);
            },

            // Decrement item quantity
            decrementItem: async (cartItemId: number) => {
                const { cart } = get();
                if (!cart) return;

                const item = cart.items?.find(item => item.id === cartItemId);
                if (!item) return;

                if (item.quantity <= 1) {
                    await get().removeFromCart(cartItemId);
                } else {
                    await get().updateCartItem(cartItemId, item.quantity - 1);
                }
            },

            // UI State Management
            openCart: () => {
                set((state) => {
                    state.isOpen = true;
                });
            },

            closeCart: () => {
                set((state) => {
                    state.isOpen = false;
                });
            },

            toggleCart: () => {
                set((state) => {
                    state.isOpen = !state.isOpen;
                });
            },

            // Utility Actions
            setLoading: (loading: boolean) => {
                set((state) => {
                    state.isLoading = loading;
                });
            },

            setError: (error: string | null) => {
                set((state) => {
                    state.error = error;
                });
            },

            clearError: () => {
                set((state) => {
                    state.error = null;
                });
            },

            // Get item quantity for a specific product/variant
            getItemQuantity: (productId: number, variantId?: number) => {
                const { cart } = get();
                if (!cart?.items) return 0;

                const item = cart.items.find(item =>
                    item.product_id === productId &&
                    item.product_variant_id === (variantId || null)
                );

                return item?.quantity || 0;
            },

            // Get cart total
            getCartTotal: () => {
                const { cart } = get();
                return {
                    amount: cart?.total_amount || 0,
                    formatted: cart?.total_amount_formatted || '£0.00',
                };
            },

            // Get cart item count
            getCartItemCount: () => {
                const { cart } = get();
                return cart?.total_items_count || 0;
            },

            // Check if item is in cart
            isItemInCart: (productId: number, variantId?: number) => {
                const { cart } = get();
                if (!cart?.items) return false;

                return cart.items.some(item =>
                    item.product_id === productId &&
                    item.product_variant_id === (variantId || null)
                );
            },
        })),
        {
            name: 'cart-storage',
            storage: createJSONStorage(() => localStorage),
            partialize: (state) => ({
                // Only persist minimal cart data and UI state
                isOpen: state.isOpen,
                lastUpdated: state.lastUpdated,
                // Don't persist cart data - always fetch fresh from API
            }),
        }
    )
);

// Export hook for easy usage
export const useCart = () => {
    const store = useCartStore();

    // Auto-fetch cart on first usage if not loaded recently
    React.useEffect(() => {
        const { cart, lastUpdated, fetchCart } = store;
        const fiveMinutesAgo = Date.now() - 5 * 60 * 1000;

        if (!cart || !lastUpdated || lastUpdated < fiveMinutesAgo) {
            fetchCart();
        }
    }, []);

    return store;
};

// Export individual selectors for performance
export const useCartItems = () => useCartStore(state => state.cart?.items || []);
export const useCartTotal = () => useCartStore(state => ({
    amount: state.cart?.total_amount || 0,
    formatted: state.cart?.total_amount_formatted || '£0.00',
}));
export const useCartItemCount = () => useCartStore(state => state.cart?.total_items_count || 0);
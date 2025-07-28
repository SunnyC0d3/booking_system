import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';
import { immer } from 'zustand/middleware/immer';
import { toast } from 'sonner';
import { productApi } from '@/api/products';
import {
    Product,
    ProductCategory,
    ProductListResponse,
    ProductSearchParams,
    ProductSearchResult,
    ProductState,
    ProductActions,
} from '@/types/product';

// Initial state
const initialState: ProductState = {
    products: [],
    categories: [],
    filters: null,
    searchResults: null,
    currentProduct: null,
    relatedProducts: [],
    isLoading: false,
    error: null,
    pagination: null,
};

// Create the product store
export const useProductStore = create<ProductState & ProductActions>()(
    persist(
        immer((set, get) => ({
            ...initialState,

            // Fetching Products
            fetchProducts: async (params?: ProductSearchParams) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const response = await productApi.getProducts(params);

                    set((state) => {
                        state.products = response.data;
                        state.filters = response.filters;
                        state.pagination = response.meta;
                        state.isLoading = false;
                    });

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to fetch products';
                        state.isLoading = false;
                    });
                    throw error;
                }
            },

            fetchProduct: async (slug: string) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const product = await productApi.getProduct(slug);

                    set((state) => {
                        state.currentProduct = product;
                        state.isLoading = false;
                    });

                    // Add to recently viewed
                    if (product.id) {
                        productApi.addToRecentlyViewed(product.id).catch(console.warn);
                    }

                    // Fetch related products
                    get().fetchRelatedProducts(product.id);

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to fetch product';
                        state.isLoading = false;
                        state.currentProduct = null;
                    });
                    throw error;
                }
            },

            fetchCategories: async () => {
                try {
                    const categories = await productApi.getCategories({
                        with_products_count: true
                    });

                    set((state) => {
                        state.categories = categories;
                    });

                } catch (error: any) {
                    console.warn('Failed to fetch categories:', error);
                }
            },

            fetchRelatedProducts: async (productId: number) => {
                try {
                    const relatedProducts = await productApi.getRelatedProducts(productId);

                    set((state) => {
                        state.relatedProducts = relatedProducts;
                    });

                } catch (error: any) {
                    console.warn('Failed to fetch related products:', error);
                }
            },

            searchProducts: async (query: string, params?: ProductSearchParams) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const results = await productApi.searchProducts(query, params);

                    set((state) => {
                        state.searchResults = results;
                        state.products = results.products.data;
                        state.pagination = results.products.meta;
                        state.filters = results.products.filters;
                        state.isLoading = false;
                    });

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Search failed';
                        state.isLoading = false;
                    });
                    throw error;
                }
            },

            // Filtering & Sorting
            applyFilters: async (filters: ProductSearchParams) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const response = await productApi.getProducts(filters);

                    set((state) => {
                        state.products = response.data;
                        state.filters = response.filters;
                        state.pagination = response.meta;
                        state.isLoading = false;
                    });

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to apply filters';
                        state.isLoading = false;
                    });
                    throw error;
                }
            },

            clearFilters: () => {
                set((state) => {
                    state.searchResults = null;
                });
                // Refetch products without filters
                get().fetchProducts();
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

            clearCache: () => {
                set((state) => {
                    state.products = [];
                    state.searchResults = null;
                    state.currentProduct = null;
                    state.relatedProducts = [];
                    state.pagination = null;
                });
            },
        })),
        {
            name: 'product-storage',
            storage: createJSONStorage(() => localStorage),
            partialize: (state) => ({
                categories: state.categories,
                // Don't persist products, search results, or current product
                // to ensure fresh data on each session
            }),
        }
    )
);

// Wishlist Store
interface WishlistState {
    items: Product[];
    isLoading: boolean;
    error: string | null;
}

interface WishlistActions {
    addToWishlist: (product: Product) => Promise<void>;
    removeFromWishlist: (productId: number) => Promise<void>;
    fetchWishlist: () => Promise<void>;
    isInWishlist: (productId: number) => boolean;
    clearWishlist: () => void;
}

export const useWishlistStore = create<WishlistState & WishlistActions>()(
    persist(
        immer((set, get) => ({
            items: [],
            isLoading: false,
            error: null,

            addToWishlist: async (product: Product) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await productApi.addToWishlist(product.id);

                    set((state) => {
                        // Check if already in wishlist
                        const existingIndex = state.items.findIndex(item => item.id === product.id);
                        if (existingIndex === -1) {
                            state.items.push(product);
                        }
                        state.isLoading = false;
                    });

                    toast.success(`${product.name} added to wishlist`);

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to add to wishlist';
                        state.isLoading = false;
                    });
                    toast.error('Failed to add to wishlist');
                    throw error;
                }
            },

            removeFromWishlist: async (productId: number) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await productApi.removeFromWishlist(productId);

                    set((state) => {
                        state.items = state.items.filter(item => item.id !== productId);
                        state.isLoading = false;
                    });

                    toast.success('Removed from wishlist');

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to remove from wishlist';
                        state.isLoading = false;
                    });
                    toast.error('Failed to remove from wishlist');
                    throw error;
                }
            },

            fetchWishlist: async () => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    const items = await productApi.getWishlist();

                    set((state) => {
                        state.items = items;
                        state.isLoading = false;
                    });

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to fetch wishlist';
                        state.isLoading = false;
                    });
                }
            },

            isInWishlist: (productId: number) => {
                return get().items.some(item => item.id === productId);
            },

            clearWishlist: () => {
                set((state) => {
                    state.items = [];
                    state.error = null;
                });
            },
        })),
        {
            name: 'wishlist-storage',
            storage: createJSONStorage(() => localStorage),
        }
    )
);

// Compare Store
interface CompareState {
    items: Product[];
    isLoading: boolean;
    error: string | null;
}

interface CompareActions {
    addToCompare: (product: Product) => Promise<void>;
    removeFromCompare: (productId: number) => Promise<void>;
    clearCompare: () => Promise<void>;
    isInCompare: (productId: number) => boolean;
    canAddToCompare: () => boolean;
}

export const useCompareStore = create<CompareState & CompareActions>()(
    persist(
        immer((set, get) => ({
            items: [],
            isLoading: false,
            error: null,

            addToCompare: async (product: Product) => {
                try {
                    // Check if we can add more items (max 4)
                    if (get().items.length >= 4) {
                        toast.error('You can compare up to 4 products at a time');
                        return;
                    }

                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await productApi.addToCompare(product.id);

                    set((state) => {
                        // Check if already in compare
                        const existingIndex = state.items.findIndex(item => item.id === product.id);
                        if (existingIndex === -1) {
                            state.items.push(product);
                        }
                        state.isLoading = false;
                    });

                    toast.success(`${product.name} added to compare`);

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to add to compare';
                        state.isLoading = false;
                    });
                    toast.error('Failed to add to compare');
                    throw error;
                }
            },

            removeFromCompare: async (productId: number) => {
                try {
                    set((state) => {
                        state.isLoading = true;
                        state.error = null;
                    });

                    await productApi.removeFromCompare(productId);

                    set((state) => {
                        state.items = state.items.filter(item => item.id !== productId);
                        state.isLoading = false;
                    });

                    toast.success('Removed from compare');

                } catch (error: any) {
                    set((state) => {
                        state.error = error.message || 'Failed to remove from compare';
                        state.isLoading = false;
                    });
                    toast.error('Failed to remove from compare');
                    throw error;
                }
            },

            clearCompare: async () => {
                try {
                    await productApi.clearCompareList();

                    set((state) => {
                        state.items = [];
                        state.error = null;
                    });

                    toast.success('Compare list cleared');

                } catch (error: any) {
                    toast.error('Failed to clear compare list');
                    throw error;
                }
            },

            isInCompare: (productId: number) => {
                return get().items.some(item => item.id === productId);
            },

            canAddToCompare: () => {
                return get().items.length < 4;
            },
        })),
        {
            name: 'compare-storage',
            storage: createJSONStorage(() => localStorage),
        }
    )
);

// Custom hooks for easier usage
export const useProducts = () => {
    return useProductStore();
};

export const useWishlist = () => {
    return useWishlistStore();
};

export const useCompare = () => {
    return useCompareStore();
};
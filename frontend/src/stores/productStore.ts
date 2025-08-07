import { create } from 'zustand';
import { immer } from 'zustand/middleware/immer';
import { Product, ProductFilters, ProductSort } from '@/types/api';
import { api } from '@/api/client';

interface ProductState {
    products: Product[];
    currentProduct: Product | null;
    filters: ProductFilters;
    sort: ProductSort;
    isLoading: boolean;
    error: string | null;
    hasMore: boolean;
    total: number;
    page: number;
    limit: number;
}

interface ProductActions {
    // Product listing
    fetchProducts: (filters?: Partial<ProductFilters>) => Promise<void>;
    loadMoreProducts: () => Promise<void>;

    // Single product
    fetchProduct: (id: number) => Promise<void>;

    // Filters and sorting
    setFilters: (filters: Partial<ProductFilters>) => void;
    setSort: (sort: ProductSort) => void;
    clearFilters: () => void;

    // State management
    setLoading: (loading: boolean) => void;
    setError: (error: string | null) => void;
    resetState: () => void;
}

interface CompareState {
    compareItems: Product[];
    isOpen: boolean;
}

interface CompareActions {
    addToCompare: (product: Product) => void;
    removeFromCompare: (productId: number) => void;
    clearCompare: () => void;
    toggleComparePanel: () => void;
    isInCompare: (productId: number) => boolean;
    canAddToCompare: () => boolean;
    getCompareCount: () => number;
}

// Fixed: Use proper empty object for optional properties instead of null
const initialState: ProductState = {
    products: [],
    currentProduct: null,
    filters: {
        // Don't set optional string properties to null - leave them undefined
        // category: undefined, // Don't even include optional properties
        // search: undefined,   // This satisfies exactOptionalPropertyTypes
        // Other optional properties are also left undefined by omission
    },
    sort: 'created_at',
    isLoading: false,
    error: null,
    hasMore: true,
    total: 0,
    page: 1,
    limit: 20,
};

export const useCompareStore = create<CompareState & CompareActions>()(
    // Fixed: Prefix unused parameter with underscore or add useful functionality
    immer((set, get) => ({
        compareItems: [],
        isOpen: false,

        addToCompare: (product) => {
            set((draft) => {
                // Maximum 4 items for comparison
                if (draft.compareItems.length < 4 && !draft.compareItems.find(p => p.id === product.id)) {
                    draft.compareItems.push(product);
                }
            });
        },

        removeFromCompare: (productId) => {
            set((draft) => {
                draft.compareItems = draft.compareItems.filter(p => p.id !== productId);
            });
        },

        clearCompare: () => {
            set((draft) => {
                draft.compareItems = [];
            });
        },

        toggleComparePanel: () => {
            set((draft) => {
                draft.isOpen = !draft.isOpen;
            });
        },

        // Enhanced utility functions using get parameter
        isInCompare: (productId) => {
            const state = get();
            return state.compareItems.some(product => product.id === productId);
        },

        canAddToCompare: () => {
            const state = get();
            return state.compareItems.length < 4;
        },

        getCompareCount: () => {
            const state = get();
            return state.compareItems.length;
        },
    }))
);

export const useProductStore = create<ProductState & ProductActions>()(
    immer((set, get) => ({
        ...initialState,

        fetchProducts: async (newFilters = {}) => {
            const state = get();
            const filters = { ...state.filters, ...newFilters };

            set((draft) => {
                draft.isLoading = true;
                draft.error = null;
                draft.page = 1;
                draft.filters = filters;
                draft.products = [];
            });

            try {
                const response = await api.get('/products', {
                    params: {
                        ...filters,
                        sort: state.sort,
                        page: 1,
                        limit: state.limit,
                    },
                });

                set((draft) => {
                    draft.products = response.data.data;
                    draft.total = response.data.meta?.pagination?.total || 0;
                    draft.hasMore = response.data.data.length === state.limit;
                    draft.isLoading = false;
                });
            } catch (error: any) {
                set((draft) => {
                    draft.error = error.message || 'Failed to fetch products';
                    draft.isLoading = false;
                });
            }
        },

        loadMoreProducts: async () => {
            const state = get();

            if (!state.hasMore || state.isLoading) return;

            set((draft) => {
                draft.isLoading = true;
                draft.page = state.page + 1;
            });

            try {
                const response = await api.get('/products', {
                    params: {
                        ...state.filters,
                        sort: state.sort,
                        page: state.page + 1,
                        limit: state.limit,
                    },
                });

                set((draft) => {
                    draft.products.push(...response.data.data);
                    draft.hasMore = response.data.data.length === state.limit;
                    draft.isLoading = false;
                });
            } catch (error: any) {
                set((draft) => {
                    draft.error = error.message || 'Failed to load more products';
                    draft.isLoading = false;
                    draft.page = state.page; // Revert page increment
                });
            }
        },

        fetchProduct: async (id: number) => {
            set((draft) => {
                draft.isLoading = true;
                draft.error = null;
                draft.currentProduct = null;
            });

            try {
                const response = await api.get(`/products/${id}`);

                set((draft) => {
                    draft.currentProduct = response.data.data;
                    draft.isLoading = false;
                });
            } catch (error: any) {
                set((draft) => {
                    draft.error = error.message || 'Failed to fetch product';
                    draft.isLoading = false;
                });
            }
        },

        setFilters: (newFilters: Partial<ProductFilters>) => {
            set((draft) => {
                draft.filters = { ...draft.filters, ...newFilters };
            });

            // Automatically fetch products with new filters
            get().fetchProducts();
        },

        setSort: (sort: ProductSort) => {
            set((draft) => {
                draft.sort = sort;
            });

            // Automatically fetch products with new sort
            get().fetchProducts();
        },

        clearFilters: () => {
            set((draft) => {
                // Reset to clean initial state without null values
                draft.filters = {
                    // Only include properties that have actual values
                    // Optional properties left undefined
                };
            });

            get().fetchProducts();
        },

        setLoading: (loading: boolean) => {
            set((draft) => {
                draft.isLoading = loading;
            });
        },

        setError: (error: string | null) => {
            set((draft) => {
                draft.error = error;
            });
        },

        resetState: () => {
            set(() => initialState);
        },
    }))
);

// Enhanced selectors for better component integration
export const useProductFilters = () => useProductStore((state) => state.filters);
export const useProductSort = () => useProductStore((state) => state.sort);
export const useProductLoading = () => useProductStore((state) => state.isLoading);
export const useProductError = () => useProductStore((state) => state.error);
export const useProductPagination = () => useProductStore((state) => ({
    page: state.page,
    hasMore: state.hasMore,
    total: state.total,
    limit: state.limit,
}));

// Compare store selectors
export const useCompareItems = () => useCompareStore((state) => state.compareItems);
export const useCompareUI = () => useCompareStore((state) => ({
    isOpen: state.isOpen,
    count: state.compareItems.length,
    canAdd: state.compareItems.length < 4,
}));

// Utility hooks
export const useIsInCompare = (productId: number) =>
    useCompareStore((state) => state.isInCompare(productId));

export const useCanAddToCompare = () =>
    useCompareStore((state) => state.canAddToCompare());

export const useCompareActions = () => useCompareStore((state) => ({
    addToCompare: state.addToCompare,
    removeFromCompare: state.removeFromCompare,
    clearCompare: state.clearCompare,
    toggleComparePanel: state.toggleComparePanel,
}));

export const useProductActions = () => useProductStore((state) => ({
    fetchProducts: state.fetchProducts,
    loadMoreProducts: state.loadMoreProducts,
    fetchProduct: state.fetchProduct,
    setFilters: state.setFilters,
    setSort: state.setSort,
    clearFilters: state.clearFilters,
    resetState: state.resetState,
}));
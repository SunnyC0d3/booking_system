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
}

const initialState: ProductState = {
    products: [],
    currentProduct: null,
    filters: {
        category: null,
        price_min: null,
        price_max: null,
        in_stock: null,
        featured: null,
        search: null,
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
                draft.filters = initialState.filters;
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

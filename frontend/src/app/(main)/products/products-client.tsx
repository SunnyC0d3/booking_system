'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { useEffect, useState, useCallback } from 'react';
import {
    ProductSearchBar,
    ProductSort,
    MobileFiltersDialog
} from '@/components/product/search';
import { ProductGrid } from '@/components/product/ProductGrid';
import { Card, CardContent, Button } from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import { ProductSearchParams } from '@/types/product';
import type { Product as ApiProduct } from '@/types/api';
import type { Product as ProductType } from '@/types/product';

interface ProductsClientProps {
    initialSearchParams: ProductSearchParams;
}

export function ProductsClient({ initialSearchParams }: ProductsClientProps) {
    const router = useRouter();
    const {
        products,
        isLoading,
        error,
        filters,
        fetchProducts,
        clearFilters,
        setSort
    } = useProductStore();

    // Local state for UI
    const [selectedFilters, setSelectedFilters] = useState<ProductSearchParams>(initialSearchParams);
    const [isInitialized, setIsInitialized] = useState(false);

    // Initialize products on mount
    useEffect(() => {
        if (!isInitialized) {
            // Convert ProductSearchParams to the format expected by fetchProducts
            const apiFilters: any = {};

            if (selectedFilters.q) {
                apiFilters.search = selectedFilters.q;
            }
            if (selectedFilters.category) {
                apiFilters.category = Array.isArray(selectedFilters.category)
                    ? selectedFilters.category[0]
                    : selectedFilters.category;
            }
            if (selectedFilters.per_page) {
                apiFilters.per_page = selectedFilters.per_page;
            }
            if (selectedFilters.page) {
                apiFilters.page = selectedFilters.page;
            }
            if (selectedFilters.sort) {
                apiFilters.sort = selectedFilters.sort;
            }

            fetchProducts(apiFilters);
            setIsInitialized(true);
        }
    }, [fetchProducts, selectedFilters, isInitialized]);

    // Sync URL with filters
    const updateURL = useCallback((newParams: ProductSearchParams) => {
        const urlParams = new URLSearchParams();

        Object.entries(newParams).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                if (Array.isArray(value)) {
                    value.forEach(v => urlParams.append(key, String(v)));
                } else {
                    urlParams.set(key, String(value));
                }
            }
        });

        const newURL = urlParams.toString() ? `/products?${urlParams.toString()}` : '/products';
        router.push(newURL, { scroll: false });
    }, [router]);

    // Handle filter changes
    const handleFilterChange = useCallback((newFilters: ProductSearchParams) => {
        setSelectedFilters(newFilters);

        // Convert to API format and fetch
        const apiFilters: any = {};

        if (newFilters.q) {
            apiFilters.search = newFilters.q;
        }
        if (newFilters.category) {
            apiFilters.category = Array.isArray(newFilters.category)
                ? newFilters.category[0]
                : newFilters.category;
        }
        if (newFilters.per_page) {
            apiFilters.per_page = newFilters.per_page;
        }
        if (newFilters.page) {
            apiFilters.page = newFilters.page;
        }
        if (newFilters.sort) {
            apiFilters.sort = newFilters.sort;
        }

        fetchProducts(apiFilters);
        updateURL(newFilters);
    }, [fetchProducts, updateURL]);

    // Handle clear filters
    const handleClearFilters = useCallback(() => {
        const clearedFilters: ProductSearchParams = {};
        setSelectedFilters(clearedFilters);
        clearFilters();
        updateURL(clearedFilters);
    }, [clearFilters, updateURL]);

    // Handle sort change
    const handleSortChange = useCallback((sortValue: string) => {
        const validSorts = ['created_at', 'price_asc', 'price_desc', 'name_asc', 'name_desc', 'featured'];
        if (validSorts.includes(sortValue)) {
            setSort(sortValue as any); // Type assertion since setSort expects specific type
            const newParams = { ...selectedFilters, sort: sortValue, page: 1 };
            handleFilterChange(newParams);
        }
    }, [selectedFilters, setSort, handleFilterChange]);

    // Handle search
    const handleSearch = useCallback((query: string) => {
        const newFilters = { ...selectedFilters, q: query, page: 1 };
        handleFilterChange(newFilters);
    }, [selectedFilters, handleFilterChange]);

    // Transform API products to local ProductType for ProductGrid
    const transformedProducts: ProductType[] = React.useMemo(() => {
        return products.map((product: ApiProduct): ProductType => {
            const baseProduct: ProductType = {
                id: product.id,
                name: product.name,
                slug: `product-${product.id}`,
                description: product.description || '',
                sku: `SKU-${product.id}`,
                price: product.price,
                price_formatted: product.price_formatted,
                track_inventory: true,
                allow_backorder: false,
                status: 'active' as const,
                visibility: 'public' as const,
                featured: false,
                gallery: (product.gallery || []).map(media => ({
                    id: media.id,
                    url: media.url,
                    ...(media.alt_text && { alt_text: media.alt_text }),
                    sort_order: 0,
                    is_featured: false,
                })),
                categories: product.category ? [{
                    id: product.category.id,
                    name: product.category.name,
                    slug: `category-${product.category.id}`,
                    description: '',
                    products_count: 0,
                    sort_order: 0,
                    is_featured: false,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                }] : [],
                tags: (product.tags || []).map(tag => ({
                    id: tag.id,
                    name: tag.name,
                    slug: `tag-${tag.id}`,
                    description: '',
                    products_count: tag.products_count || 0,
                })),
                variants: (product.variants || []).map(variant => ({
                    id: variant.id,
                    product_id: product.id,
                    name: variant.product_attribute?.name || 'Default',
                    value: variant.value,
                    price_adjustment: variant.additional_price || 0,
                    price_adjustment_type: 'fixed' as const,
                    sku: '',
                    sort_order: 0,
                    is_default: false,
                    attribute: {
                        id: variant.product_attribute?.id || 0,
                        name: variant.product_attribute?.name || 'Default',
                        slug: `attribute-${variant.product_attribute?.id || 0}`,
                        type: 'dropdown' as const,
                        values: [],
                        is_required: false,
                        is_filterable: false,
                        sort_order: 0,
                    },
                })),
                attributes: [],
                reviews_count: 0,
                reviews_average: 0,
                created_at: product.created_at,
                updated_at: product.updated_at,
            };

            // Add optional properties conditionally
            if (product.description) {
                baseProduct.short_description = product.description;
            }

            if (product.featured_image) {
                baseProduct.featured_image = product.featured_image;
            }

            if (product.quantity) {
                baseProduct.inventory_quantity = product.quantity;
            }

            if (product.category) {
                baseProduct.category = {
                    id: product.category.id,
                    name: product.category.name,
                    slug: `category-${product.category.id}`,
                    description: '',
                    products_count: 0,
                    sort_order: 0,
                    is_featured: false,
                    created_at: new Date().toISOString(),
                    updated_at: new Date().toISOString(),
                };
            }

            return baseProduct;
        });
    }, [products]);

    return (
        <div className="space-y-6">
            {/* Search Header */}
            <div className="space-y-4">
                <ProductSearchBar
                    className="max-w-2xl"
                    onSearch={handleSearch}
                />

                {/* Mobile Actions */}
                <div className="flex items-center justify-between lg:hidden">
                    <Button variant="outline" size="sm">
                        Filters
                    </Button>
                    <ProductSort
                        onSortChange={handleSortChange}
                        selected={selectedFilters.sort}
                    />
                </div>
            </div>

            {/* Main Content */}
            <div className="flex flex-col lg:flex-row gap-8">
                {/* Sidebar Filters - Desktop */}
                <aside className="hidden lg:block lg:w-72 flex-shrink-0">
                    <div className="sticky top-24">
                        <ProductFiltersContainer
                            onClearFilters={handleClearFilters}
                        />
                    </div>
                </aside>

                {/* Product Grid */}
                <main className="flex-1 min-w-0">
                    <div className="space-y-6">
                        {/* Desktop Sort & Results Count */}
                        <div className="hidden lg:flex items-center justify-between">
                            <ResultsCount count={products.length} />
                            <ProductSort
                                onSortChange={handleSortChange}
                                selected={selectedFilters.sort}
                            />
                        </div>

                        {/* Products */}
                        {error ? (
                            <Card>
                                <CardContent className="p-8 text-center">
                                    <p className="text-muted-foreground">
                                        {error}
                                    </p>
                                    <Button
                                        onClick={() => handleClearFilters()}
                                        className="mt-4"
                                    >
                                        Try Again
                                    </Button>
                                </CardContent>
                            </Card>
                        ) : (
                            <ProductGrid
                                products={transformedProducts}
                                loading={isLoading}
                                emptyMessage="No products found. Try adjusting your filters."
                                columns={{ sm: 1, md: 2, lg: 3, xl: 4 }}
                            />
                        )}
                    </div>
                </main>
            </div>
        </div>
    );
}

// Helper components
function ProductFiltersContainer({
                                     onClearFilters
                                 }: {
    onClearFilters: () => void;
}) {
    return (
        <Card>
            <CardContent className="p-6">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="font-semibold">Filters</h3>
                    <Button variant="ghost" size="sm" onClick={onClearFilters}>
                        Clear All
                    </Button>
                </div>
                {/* Add filter components here when available */}
                <p className="text-sm text-muted-foreground">
                    Filter components will be displayed here
                </p>
            </CardContent>
        </Card>
    );
}

function ResultsCount({ count }: { count: number }) {
    return (
        <p className="text-sm text-muted-foreground">
            Showing {count} {count === 1 ? 'product' : 'products'}
        </p>
    );
}
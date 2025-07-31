'use client';

import * as React from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useEffect, useState, useCallback } from 'react';
import {
    ProductSearchBar,
    ProductFilters,
    ProductSort,
    MobileFiltersDialog
} from '@/components/product/search';
import { ProductGrid } from '@/components/product/ProductGrid';
import { Card, CardContent, Button } from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import { ProductSearchParams, ProductFilters as ProductFiltersType } from '@/types/product';
import { cn } from '@/lib/cn';

interface ProductsClientProps {
    initialSearchParams: ProductSearchParams;
}

export function ProductsClient({ initialSearchParams }: ProductsClientProps) {
    const router = useRouter();
    const searchParams = useSearchParams();
    const {
        products,
        isLoading,
        error,
        pagination,
        filters,
        fetchProducts,
        applyFilters,
        clearFilters,
        setSort
    } = useProductStore();

    // Local state for UI
    const [selectedFilters, setSelectedFilters] = useState<ProductSearchParams>(initialSearchParams);
    const [isInitialized, setIsInitialized] = useState(false);

    // Initialize products on mount
    useEffect(() => {
        if (!isInitialized) {
            fetchProducts(initialSearchParams);
            setIsInitialized(true);
        }
    }, [fetchProducts, initialSearchParams, isInitialized]);

    // Sync URL with filters
    const updateURL = useCallback((newParams: ProductSearchParams) => {
        const urlParams = new URLSearchParams();

        Object.entries(newParams).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                urlParams.set(key, String(value));
            }
        });

        const newURL = urlParams.toString() ? `/products?${urlParams.toString()}` : '/products';
        router.push(newURL, { scroll: false });
    }, [router]);

    // Handle filter changes
    const handleFilterChange = useCallback((newFilters: ProductSearchParams) => {
        setSelectedFilters(newFilters);
        applyFilters(newFilters);
        updateURL(newFilters);
    }, [applyFilters, updateURL]);

    // Handle clear filters
    const handleClearFilters = useCallback(() => {
        const clearedFilters = {};
        setSelectedFilters(clearedFilters);
        clearFilters();
        updateURL(clearedFilters);
    }, [clearFilters, updateURL]);

    // Handle sort change
    const handleSortChange = useCallback((sortValue: string) => {
        setSort(sortValue);
        const newParams = { ...selectedFilters, sort: sortValue, page: 1 };
        updateURL(newParams);
    }, [selectedFilters, setSort, updateURL]);

    // Handle search
    const handleSearch = useCallback((query: string) => {
        const newFilters = { ...selectedFilters, search: query, page: 1 };
        handleFilterChange(newFilters);
    }, [selectedFilters, handleFilterChange]);

    return (
        <div className="space-y-6">
            {/* Search Header */}
            <div className="space-y-4">
                <ProductSearchBar
                    className="max-w-2xl"
                    onSearch={handleSearch}
                    defaultValue={selectedFilters.search}
                />

                {/* Mobile Actions */}
                <div className="flex items-center justify-between lg:hidden">
                    <MobileFiltersDialog
                        filters={filters}
                        selectedFilters={selectedFilters}
                        onFilterChange={handleFilterChange}
                        onClearFilters={handleClearFilters}
                    />
                    <ProductSort
                        onSortChange={handleSortChange}
                        currentSort={selectedFilters.sort}
                    />
                </div>
            </div>

            {/* Main Content */}
            <div className="flex flex-col lg:flex-row gap-8">
                {/* Sidebar Filters - Desktop */}
                <aside className="hidden lg:block lg:w-72 flex-shrink-0">
                    <div className="sticky top-24">
                        <ProductFiltersContainer
                            filters={filters}
                            selectedFilters={selectedFilters}
                            onFilterChange={handleFilterChange}
                            onClearFilters={handleClearFilters}
                        />
                    </div>
                </aside>

                {/* Product Grid */}
                <main className="flex-1 min-w-0">
                    <div className="space-y-6">
                        {/* Desktop Sort & Results Count */}
                        <div className="hidden lg:flex items-center justify-between">
                            <ResultsCount pagination={pagination} />
                            <ProductSort
                                onSortChange={handleSortChange}
                                currentSort={selectedFilters.sort}
                            />
                        </div>

                        {/* Products */}
                        <ProductsContainer
                            products={products}
                            isLoading={isLoading}
                            error={error}
                            pagination={pagination}
                            onPageChange={(page) => {
                                const newParams = { ...selectedFilters, page };
                                updateURL(newParams);
                                fetchProducts(newParams);
                            }}
                        />
                    </div>
                </main>
            </div>
        </div>
    );
}
import * as React from 'react';
import { Suspense } from 'react';
import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import {
    ProductSearchBar,
    ProductFilters,
    ProductSort,
    MobileFiltersDialog
} from '@/components/product/search';
import { ProductGrid } from '@/components/product/ProductGrid';
import ProductCardSkeleton from '@/components/ui/loading/ProductCardSkeleton';
import { DashboardLayout } from '@/components/layout';
import { Card, CardContent, Button } from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import { ProductSearchParams } from '@/types/product';
import { cn } from '@/lib/cn';

export const metadata: Metadata = {
    title: 'Creative Products | Labels, Invitations & More',
    description: 'Browse our collection of custom labels, invitations, gift tags, stickers, and creative packaging solutions.',
};

interface ProductsPageProps {
    searchParams: Promise<ProductSearchParams>;
}

// Server Component for SEO and initial data
async function ProductsPage({ searchParams }: ProductsPageProps) {
    const params = await searchParams;

    return (
        <DashboardLayout
            title="Products"
            description="Discover our creative collection of labels, invitations, and custom designs"
            showBreadcrumbs
        >
            <div className="space-y-6">
                {/* Search Header */}
                <div className="space-y-4">
                    <ProductSearchBar className="max-w-2xl" />

                    {/* Mobile Actions */}
                    <div className="flex items-center justify-between lg:hidden">
                        <MobileFiltersDialog />
                        <ProductSort />
                    </div>
                </div>

                {/* Main Content */}
                <div className="flex flex-col lg:flex-row gap-8">
                    {/* Sidebar Filters - Desktop */}
                    <aside className="hidden lg:block lg:w-72 flex-shrink-0">
                        <div className="sticky top-24">
                            <Suspense fallback={<FiltersSkeleton />}>
                                <ProductFiltersContainer searchParams={params} />
                            </Suspense>
                        </div>
                    </aside>

                    {/* Product Grid */}
                    <main className="flex-1 min-w-0">
                        <div className="space-y-6">
                            {/* Desktop Sort & Results Count */}
                            <div className="hidden lg:flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    <Suspense fallback="Loading...">
                                        <ResultsCount searchParams={params} />
                                    </Suspense>
                                </div>
                                <ProductSort />
                            </div>

                            {/* Products */}
                            <Suspense fallback={<ProductGridSkeleton />}>
                                <ProductsContainer searchParams={params} />
                            </Suspense>
                        </div>
                    </main>
                </div>
            </div>
        </DashboardLayout>
    );
}

// Client Components for dynamic functionality
function ProductFiltersContainer({ searchParams }: { searchParams: ProductSearchParams }) {
    const { filters, fetchProducts, applyFilters, clearFilters } = useProductStore();
    const [selectedFilters, setSelectedFilters] = React.useState<ProductSearchParams>(searchParams);

    React.useEffect(() => {
        fetchProducts(searchParams);
    }, [fetchProducts, searchParams]);

    const handleFilterChange = (newFilters: ProductSearchParams) => {
        setSelectedFilters(newFilters);
        applyFilters(newFilters);
    };

    const handleClearFilters = () => {
        setSelectedFilters({});
        clearFilters();
    };

    return (
        <ProductFilters
            filters={filters}
            selectedFilters={selectedFilters}
            onFilterChange={handleFilterChange}
            onClearFilters={handleClearFilters}
        />
    );
}

function ProductsContainer({ searchParams }: { searchParams: ProductSearchParams }) {
    const { products, isLoading, error, pagination } = useProductStore();

    if (error) {
        return (
            <Card>
                <CardContent className="text-center py-12">
                    <p className="text-destructive mb-4">Failed to load products</p>
                    <Button variant="outline" onClick={() => window.location.reload()}>
                        Try Again
                    </Button>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <ProductGrid
                products={products}
                loading={isLoading}
                emptyMessage="No products match your search criteria. Try adjusting your filters."
            />

            {/* Pagination */}
            {pagination && pagination.last_page > 1 && (
                <div className="flex justify-center">
                    <Pagination pagination={pagination} />
                </div>
            )}
        </div>
    );
}

function ResultsCount({ searchParams }: { searchParams: ProductSearchParams }) {
    const { pagination } = useProductStore();

    if (!pagination) return null;

    return (
        <span>
            Showing {pagination.from}-{pagination.to} of {pagination.total} products
        </span>
    );
}

function Pagination({ pagination }: { pagination: any }) {
    const router = useRouter();
    const searchParams = useSearchParams();

    const handlePageChange = (page: number) => {
        const params = new URLSearchParams(searchParams);
        params.set('page', page.toString());
        router.push(`/products?${params.toString()}`);
    };

    return (
        <div className="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                onClick={() => handlePageChange(pagination.current_page - 1)}
                disabled={pagination.current_page <= 1}
            >
                Previous
            </Button>

            {/* Page numbers */}
            {Array.from({ length: Math.min(5, pagination.last_page) }, (_, i) => {
                const page = i + 1;
                return (
                    <Button
                        key={page}
                        variant={page === pagination.current_page ? "default" : "outline"}
                        size="sm"
                        onClick={() => handlePageChange(page)}
                    >
                        {page}
                    </Button>
                );
            })}

            <Button
                variant="outline"
                size="sm"
                onClick={() => handlePageChange(pagination.current_page + 1)}
                disabled={pagination.current_page >= pagination.last_page}
            >
                Next
            </Button>
        </div>
    );
}

// Loading Components
function FiltersSkeleton() {
    return (
        <Card>
            <CardContent className="p-6 space-y-6">
                {Array.from({ length: 4 }).map((_, i) => (
                    <div key={i} className="space-y-3">
                        <div className="h-4 bg-muted rounded w-24 loading-shimmer" />
                        <div className="space-y-2">
                            {Array.from({ length: 3 }).map((_, j) => (
                                <div key={j} className="h-3 bg-muted rounded loading-shimmer" />
                            ))}
                        </div>
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

function ProductGridSkeleton() {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {Array.from({ length: 12 }).map((_, i) => (
                <ProductCardSkeleton key={i} />
            ))}
        </div>
    );
}
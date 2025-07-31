import * as React from 'react';
import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Suspense } from 'react';
import ProductDetail from '@/components/product/detail/ProductDetail';
import { ProductGrid } from '@/components/product/ProductGrid';
import { DashboardLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import { Product } from '@/types/product';

interface ProductPageProps {
    params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: ProductPageProps): Promise<Metadata> {
    const { id } = await params;

    // In a real app, you'd fetch this from your API
    // For now, we'll use default metadata
    return {
        title: `Product | Creative Business`,
        description: 'View product details and specifications.',
    };
}

async function ProductPage({ params }: ProductPageProps) {
    const { id } = await params;

    return (
        <DashboardLayout showBreadcrumbs>
            <div className="space-y-12">
                <Suspense fallback={<ProductDetailSkeleton />}>
                    <ProductDetailContainer id={id} />
                </Suspense>

                <Suspense fallback={<RelatedProductsSkeleton />}>
                    <RelatedProductsContainer />
                </Suspense>
            </div>
        </DashboardLayout>
    );
}

function ProductDetailContainer({ id }: { id: string }) {
    const { currentProduct, fetchProduct, isLoading, error } = useProductStore();

    React.useEffect(() => {
        fetchProduct(id);
    }, [fetchProduct, id]);

    if (isLoading) {
        return <ProductDetailSkeleton />;
    }

    if (error || !currentProduct) {
        notFound();
    }

    return <ProductDetail product={currentProduct} />;
}

function RelatedProductsContainer() {
    const { relatedProducts, isLoading } = useProductStore();

    if (isLoading || relatedProducts.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>You Might Also Like</CardTitle>
            </CardHeader>
            <CardContent>
                <ProductGrid
                    products={relatedProducts}
                    columns={{ sm: 1, md: 2, lg: 3, xl: 4 }}
                    showFilters={false}
                    showSort={false}
                />
            </CardContent>
        </Card>
    );
}

// Loading Components
function ProductDetailSkeleton() {
    return (
        <div className="max-w-7xl mx-auto">
            <div className="grid lg:grid-cols-2 gap-8 lg:gap-12">
                {/* Image Skeleton */}
                <div className="space-y-4">
                    <div className="aspect-square bg-muted rounded-xl loading-shimmer" />
                    <div className="flex gap-2">
                        {Array.from({ length: 4 }).map((_, i) => (
                            <div key={i} className="w-16 h-16 bg-muted rounded-lg loading-shimmer" />
                        ))}
                    </div>
                </div>

                {/* Content Skeleton */}
                <div className="space-y-6">
                    <div className="space-y-4">
                        <div className="h-3 bg-muted rounded w-32 loading-shimmer" />
                        <div className="h-8 bg-muted rounded loading-shimmer" />
                        <div className="h-4 bg-muted rounded w-48 loading-shimmer" />
                        <div className="h-6 bg-muted rounded w-24 loading-shimmer" />
                    </div>

                    <div className="space-y-3">
                        <div className="h-4 bg-muted rounded loading-shimmer" />
                        <div className="h-4 bg-muted rounded w-3/4 loading-shimmer" />
                    </div>

                    <div className="space-y-4">
                        <div className="h-10 bg-muted rounded loading-shimmer" />
                        <div className="flex gap-3">
                            <div className="h-12 bg-muted rounded flex-1 loading-shimmer" />
                            <div className="h-12 w-12 bg-muted rounded loading-shimmer" />
                            <div className="h-12 w-12 bg-muted rounded loading-shimmer" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

function RelatedProductsSkeleton() {
    return (
        <Card>
            <CardHeader>
                <div className="h-6 bg-muted rounded w-48 loading-shimmer" />
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {Array.from({ length: 4 }).map((_, i) => (
                        <ProductCardSkeleton key={i} />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function ProductCardSkeleton() {
    return (
        <div className="space-y-3">
            <div className="aspect-square bg-muted rounded-lg loading-shimmer" />
            <div className="space-y-2">
                <div className="h-4 bg-muted rounded loading-shimmer" />
                <div className="h-4 bg-muted rounded w-3/4 loading-shimmer" />
                <div className="h-5 bg-muted rounded w-1/2 loading-shimmer" />
            </div>
        </div>
    );
}

export default ProductPage;
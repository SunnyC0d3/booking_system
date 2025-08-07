import * as React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Suspense } from 'react';
import ProductDetail from '@/components/product/detail/ProductDetail';
import { DashboardLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent, Badge } from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import type { Product as ApiProduct } from '@/types/api';

interface ProductPageProps {
    params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: ProductPageProps): Promise<Metadata> {
    const { id } = await params;

    return {
        title: `Product ${id} | Creative Business`,
        description: `View details and specifications for product ${id}.`,
    };
}

async function ProductPage({ params }: ProductPageProps) {
    const { id } = await params;

    return (
        <DashboardLayout
            title="Product Details"
            description="View product information and specifications"
        >
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
        // Convert string id to number if fetchProduct expects a number
        const productId = parseInt(id, 10);
        if (!isNaN(productId)) {
            fetchProduct(productId);
        }
    }, [fetchProduct, id]);

    if (isLoading) {
        return <ProductDetailSkeleton />;
    }

    if (error || !currentProduct) {
        notFound();
    }

    // ProductDetail now expects ApiProduct, so no transformation needed
    return <ProductDetail product={currentProduct} />;
}

function RelatedProductsContainer() {
    const { products, isLoading } = useProductStore();

    // Use first 4 products as related products since relatedProducts might not exist
    const relatedProducts = products.slice(0, 4);

    if (isLoading || relatedProducts.length === 0) {
        return null;
    }

    // ProductGrid expects ProductType, so we need to transform API products
    // For now, we'll create a simple grid component that works with API products
    return (
        <Card>
            <CardHeader>
                <CardTitle>You Might Also Like</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    {relatedProducts.map((product) => (
                        <RelatedProductCard key={product.id} product={product} />
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

// Simple product card component that works with API products
function RelatedProductCard({ product }: { product: ApiProduct }) {
    return (
        <div className="group space-y-3">
            <div className="relative aspect-square overflow-hidden rounded-lg bg-muted">
                {product.featured_image ? (
                    <Image
                        src={product.featured_image}
                        alt={product.name}
                        fill
                        className="object-cover group-hover:scale-105 transition-transform duration-300"
                    />
                ) : (
                    <div className="flex items-center justify-center w-full h-full">
                        <span className="text-muted-foreground text-sm">No image</span>
                    </div>
                )}

                {/* Stock badge */}
                {!product.is_in_stock && (
                    <div className="absolute top-2 left-2">
                        <Badge variant="secondary" className="bg-red-500 text-white">
                            Out of Stock
                        </Badge>
                    </div>
                )}

                {product.is_low_stock && product.is_in_stock && (
                    <div className="absolute top-2 left-2">
                        <Badge variant="secondary" className="bg-orange-500 text-white">
                            Low Stock
                        </Badge>
                    </div>
                )}
            </div>

            <div className="space-y-2">
                <Link
                    href={`/products/${product.id}`}
                    className="font-medium text-foreground group-hover:text-primary transition-colors line-clamp-2"
                >
                    {product.name}
                </Link>

                <div className="flex items-center justify-between">
                    <span className="font-bold text-primary">
                        {product.price_formatted}
                    </span>

                    {product.quantity > 0 && product.quantity <= 10 && (
                        <span className="text-xs text-muted-foreground">
                            {product.quantity} left
                        </span>
                    )}
                </div>

                {product.category && (
                    <span className="text-xs text-muted-foreground">
                        {product.category.name}
                    </span>
                )}
            </div>
        </div>
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
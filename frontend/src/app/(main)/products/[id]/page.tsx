import * as React from 'react';
import { Metadata } from 'next';
import { notFound } from 'next/navigation';
import { Suspense } from 'react';
import ProductDetail from '@/components/product/detail/ProductDetail';
import { ProductGrid } from '@/components/product/ProductGrid';
import { DashboardLayout } from '@/components/layout';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import type { Product as ApiProduct } from '@/types/api';
import type { Product as ProductType } from '@/types/product';

interface ProductPageProps {
    params: Promise<{ id: string }>;
}

export async function generateMetadata({ params }: ProductPageProps): Promise<Metadata> {
    const { id } = await params;

    // In a real app, you'd fetch the product data using the id to create specific metadata
    // const product = await fetchProduct(id);

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

    // Transform API product to match ProductDetail expected type
    const transformedProduct: ProductType = {
        id: currentProduct.id,
        name: currentProduct.name,
        slug: `product-${currentProduct.id}`,
        description: currentProduct.description || '',
        sku: `SKU-${currentProduct.id}`,
        price: currentProduct.price,
        price_formatted: currentProduct.price_formatted,
        track_inventory: true,
        allow_backorder: false,
        status: 'active' as const,
        visibility: 'public' as const,
        featured: false,
        gallery: (currentProduct.gallery || []).map(media => ({
            id: media.id,
            url: media.url,
            ...(media.alt_text && { alt_text: media.alt_text }),
            sort_order: 0,
            is_featured: false,
        })),
        categories: currentProduct.category ? [{
            id: currentProduct.category.id,
            name: currentProduct.category.name,
            slug: `category-${currentProduct.category.id}`,
            description: '',
            products_count: 0,
            sort_order: 0,
            is_featured: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        }] : [],
        tags: (currentProduct.tags || []).map(tag => ({
            id: tag.id,
            name: tag.name,
            slug: `tag-${tag.id}`,
            description: '',
            products_count: tag.products_count || 0,
        })),
        variants: (currentProduct.variants || []).map(variant => ({
            id: variant.id,
            product_id: currentProduct.id,
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
        created_at: currentProduct.created_at,
        updated_at: currentProduct.updated_at,
    };

    // Add optional properties only if they exist
    if (currentProduct.description) {
        transformedProduct.short_description = currentProduct.description;
    }

    if (currentProduct.featured_image) {
        transformedProduct.featured_image = currentProduct.featured_image;
    }

    if (currentProduct.quantity) {
        transformedProduct.inventory_quantity = currentProduct.quantity;
    }

    if (currentProduct.category) {
        transformedProduct.category = {
            id: currentProduct.category.id,
            name: currentProduct.category.name,
            slug: `category-${currentProduct.category.id}`,
            description: '',
            products_count: 0,
            sort_order: 0,
            is_featured: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
        };
    }

    return <ProductDetail product={transformedProduct} />;
}

function RelatedProductsContainer() {
    const { products, isLoading } = useProductStore();

    // Use first 4 products as related products since relatedProducts might not exist
    const relatedProducts = products.slice(0, 4);

    if (isLoading || relatedProducts.length === 0) {
        return null;
    }

    // Transform API products to match ProductGrid expected type
    const transformedProducts: ProductType[] = relatedProducts.map((product: ApiProduct): ProductType => {
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

    return (
        <Card>
            <CardHeader>
                <CardTitle>You Might Also Like</CardTitle>
            </CardHeader>
            <CardContent>
                <ProductGrid
                    products={transformedProducts}
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
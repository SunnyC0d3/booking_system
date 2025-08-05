import * as React from 'react';
import { Suspense } from 'react';
import { Metadata } from 'next';
import { DashboardLayout } from '@/components/layout';
import { ProductsClient } from './products-client';
import { ProductSearchParams } from '@/types/product';

export const metadata: Metadata = {
    title: 'Creative Products | Labels, Invitations & More',
    description: 'Browse our collection of custom labels, invitations, gift tags, stickers, and creative packaging solutions.',
};

interface ProductsPageProps {
    searchParams: Promise<ProductSearchParams>;
}

export default async function ProductsPage({ searchParams }: ProductsPageProps) {
    const params = await searchParams;

    return (
        <DashboardLayout
            title="Products"
            description="Discover our creative collection of labels, invitations, and custom designs"
        >
            <Suspense fallback={<ProductsPageSkeleton />}>
                <ProductsClient initialSearchParams={params} />
            </Suspense>
        </DashboardLayout>
    );
}

function ProductsPageSkeleton() {
    return (
        <div className="space-y-6">
            {/* Search Header Skeleton */}
            <div className="space-y-4">
                <div className="h-12 bg-muted rounded-lg max-w-2xl loading-shimmer" />
                <div className="flex items-center justify-between lg:hidden">
                    <div className="h-10 w-24 bg-muted rounded loading-shimmer" />
                    <div className="h-10 w-32 bg-muted rounded loading-shimmer" />
                </div>
            </div>

            {/* Main Content Skeleton */}
            <div className="flex flex-col lg:flex-row gap-8">
                {/* Sidebar Skeleton */}
                <aside className="hidden lg:block lg:w-72 flex-shrink-0">
                    <FiltersSkeleton />
                </aside>

                {/* Product Grid Skeleton */}
                <main className="flex-1 min-w-0">
                    <div className="space-y-6">
                        <div className="hidden lg:flex items-center justify-between">
                            <div className="h-4 w-32 bg-muted rounded loading-shimmer" />
                            <div className="h-10 w-40 bg-muted rounded loading-shimmer" />
                        </div>
                        <ProductGridSkeleton />
                    </div>
                </main>
            </div>
        </div>
    );
}

function FiltersSkeleton() {
    return (
        <div className="bg-card border rounded-lg p-6 space-y-6">
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
        </div>
    );
}

function ProductGridSkeleton() {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            {Array.from({ length: 12 }).map((_, i) => (
                <div key={i} className="bg-card border rounded-lg p-4 space-y-4">
                    <div className="aspect-square bg-muted rounded-lg loading-shimmer" />
                    <div className="space-y-2">
                        <div className="h-4 bg-muted rounded loading-shimmer" />
                        <div className="h-3 bg-muted rounded w-3/4 loading-shimmer" />
                        <div className="h-5 bg-muted rounded w-1/2 loading-shimmer" />
                    </div>
                </div>
            ))}
        </div>
    );
}
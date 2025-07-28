import * as React from 'react';
import { Card, CardContent } from '@/components/ui';
import { cn } from '@/lib/cn';

interface ProductCardSkeletonProps {
    layout?: 'grid' | 'list';
    className?: string;
}

export const ProductCardSkeleton: React.FC<ProductCardSkeletonProps> = ({
                                                                            layout = 'grid',
                                                                            className,
                                                                        }) => {
    if (layout === 'list') {
        return (
            <Card className={cn('group overflow-hidden', className)}>
                <div className="flex gap-4 p-4">
                    {/* Image Skeleton */}
                    <div className="relative w-24 h-24 bg-muted rounded-lg loading-shimmer" />

                    {/* Content Skeleton */}
                    <div className="flex-1 space-y-3">
                        {/* Title */}
                        <div className="h-5 bg-muted rounded loading-shimmer" />

                        {/* Description */}
                        <div className="space-y-2">
                            <div className="h-3 bg-muted rounded loading-shimmer" />
                            <div className="h-3 bg-muted rounded w-3/4 loading-shimmer" />
                        </div>

                        {/* Rating */}
                        <div className="flex items-center gap-2">
                            <div className="flex gap-1">
                                {Array.from({ length: 5 }).map((_, i) => (
                                    <div key={i} className="w-3 h-3 bg-muted rounded loading-shimmer" />
                                ))}
                            </div>
                            <div className="h-3 w-12 bg-muted rounded loading-shimmer" />
                        </div>

                        {/* Price */}
                        <div className="flex items-center gap-2">
                            <div className="h-6 w-16 bg-muted rounded loading-shimmer" />
                            <div className="h-4 w-12 bg-muted rounded loading-shimmer" />
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex flex-col gap-2">
                        <div className="w-8 h-8 bg-muted rounded loading-shimmer" />
                        <div className="w-8 h-8 bg-muted rounded loading-shimmer" />
                        <div className="w-8 h-8 bg-muted rounded loading-shimmer" />
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <Card className={cn('group overflow-hidden', className)}>
            <CardContent className="p-0">
                {/* Image Skeleton */}
                <div className="relative aspect-square overflow-hidden rounded-t-lg">
                    <div className="w-full h-full bg-muted loading-shimmer" />

                    {/* Badge Skeleton */}
                    <div className="absolute top-3 left-3 w-16 h-6 bg-muted rounded-full loading-shimmer" />

                    {/* Action Buttons Skeleton */}
                    <div className="absolute top-3 right-3 flex flex-col gap-2">
                        <div className="w-8 h-8 bg-muted rounded-full loading-shimmer" />
                        <div className="w-8 h-8 bg-muted rounded-full loading-shimmer" />
                        <div className="w-8 h-8 bg-muted rounded-full loading-shimmer" />
                    </div>
                </div>

                {/* Content Skeleton */}
                <div className="p-4 space-y-3">
                    {/* Category */}
                    <div className="h-3 w-20 bg-muted rounded loading-shimmer" />

                    {/* Title */}
                    <div className="space-y-2">
                        <div className="h-5 bg-muted rounded loading-shimmer" />
                        <div className="h-5 bg-muted rounded w-3/4 loading-shimmer" />
                    </div>

                    {/* Rating */}
                    <div className="flex items-center gap-2">
                        <div className="flex gap-1">
                            {Array.from({ length: 5 }).map((_, i) => (
                                <div key={i} className="w-3 h-3 bg-muted rounded loading-shimmer" />
                            ))}
                        </div>
                        <div className="h-3 w-12 bg-muted rounded loading-shimmer" />
                    </div>

                    {/* Price */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="h-6 w-16 bg-muted rounded loading-shimmer" />
                            <div className="h-4 w-12 bg-muted rounded loading-shimmer" />
                        </div>
                    </div>

                    {/* Add to Cart Button */}
                    <div className="h-9 bg-muted rounded loading-shimmer" />
                </div>
            </CardContent>
        </Card>
    );
};

export default ProductCardSkeleton;
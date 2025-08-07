import * as React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Grid, List, Package } from 'lucide-react';
import { Button, ProductCardSkeleton } from '@/components/ui';
import ProductCard from './ProductCard';
import { ProductGridProps } from '@/types/product';
import { cn } from '@/lib/cn';

export const ProductGrid: React.FC<ProductGridProps> = ({
                                                            products,
                                                            loading = false,
                                                            layout = 'grid',
                                                            columns = {
                                                                sm: 1,
                                                                md: 2,
                                                                lg: 3,
                                                                xl: 4,
                                                            },
                                                            emptyMessage = 'No products found',
                                                            className,
                                                        }) => {
    const [currentLayout, setCurrentLayout] = React.useState<'grid' | 'list'>(layout);

    // Generate grid classes based on columns prop
    const gridClasses = cn(
        'grid gap-6',
        currentLayout === 'grid' && [
            `grid-cols-${columns.sm || 1}`,
            `sm:grid-cols-${columns.sm || 1}`,
            `md:grid-cols-${columns.md || 2}`,
            `lg:grid-cols-${columns.lg || 3}`,
            `xl:grid-cols-${columns.xl || 4}`,
        ],
        currentLayout === 'list' && 'grid-cols-1'
    );

    // Loading skeleton
    if (loading) {
        return (
            <div className={cn('space-y-6', className)}>
                {/* Layout Toggle */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Button
                            variant={currentLayout === 'grid' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setCurrentLayout('grid')}
                            leftIcon={<Grid className="h-4 w-4" />}
                        >
                            Grid
                        </Button>
                        <Button
                            variant={currentLayout === 'list' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setCurrentLayout('list')}
                            leftIcon={<List className="h-4 w-4" />}
                        >
                            List
                        </Button>
                    </div>
                    <div className="text-sm text-muted-foreground">
                        Loading products...
                    </div>
                </div>

                {/* Skeleton Grid */}
                <div className={gridClasses}>
                    {Array.from({ length: 8 }).map((_, index) => (
                        <ProductCardSkeleton key={index} />
                    ))}
                </div>
            </div>
        );
    }

    // Empty state
    if (!loading && products.length === 0) {
        return (
            <div className={cn('space-y-6', className)}>
                <div className="text-center py-12">
                    <div className="w-24 h-24 bg-muted/50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <Package className="h-12 w-12 text-muted-foreground" />
                    </div>
                    <h3 className="text-lg font-semibold text-foreground mb-2">
                        No Products Found
                    </h3>
                    <p className="text-muted-foreground mb-6 max-w-md mx-auto">
                        {emptyMessage}
                    </p>
                    <Button variant="outline">
                        Browse All Products
                    </Button>
                </div>
            </div>
        );
    }

    return (
        <div className={cn('space-y-6', className)}>
            {/* Header with Layout Toggle and Count */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <Button
                        variant={currentLayout === 'grid' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setCurrentLayout('grid')}
                        leftIcon={<Grid className="h-4 w-4" />}
                    >
                        Grid
                    </Button>
                    <Button
                        variant={currentLayout === 'list' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setCurrentLayout('list')}
                        leftIcon={<List className="h-4 w-4" />}
                    >
                        List
                    </Button>
                </div>

                <div className="text-sm text-muted-foreground">
                    {products.length} product{products.length !== 1 ? 's' : ''}
                </div>
            </div>

            {/* Product Grid */}
            <motion.div
                layout
                className={gridClasses}
                initial={false}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.3 }}
            >
                <AnimatePresence mode="popLayout">
                    {products.map((product, index) => (
                        <motion.div
                            key={product.id}
                            layout
                            initial={{ opacity: 0, scale: 0.9 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: 0.9 }}
                            transition={{
                                duration: 0.3,
                                delay: index * 0.05,
                                layout: { duration: 0.3 }
                            }}
                        >
                            <ProductCard
                                product={product}
                                layout={currentLayout}
                                priority={index < 4} // Prioritize first 4 images
                                showQuickAdd={true}
                                showWishlist={true}
                                showCompare={true}
                            />
                        </motion.div>
                    ))}
                </AnimatePresence>
            </motion.div>
        </div>
    );
};

// Product Grid with Filters (Combined Component)
interface ProductGridWithFiltersProps extends ProductGridProps {
    showFilterSidebar?: boolean;
    filters?: React.ReactNode;
    sortOptions?: React.ReactNode;
}

export const ProductGridWithFilters: React.FC<ProductGridWithFiltersProps> = ({
                                                                                  showFilterSidebar = true,
                                                                                  filters,
                                                                                  sortOptions,
                                                                                  ...productGridProps
                                                                              }) => {
    const [showMobileFilters, setShowMobileFilters] = React.useState(false);

    return (
        <div className="grid lg:grid-cols-4 gap-8">
            {/* Filters Sidebar */}
            {showFilterSidebar && (
                <>
                    {/* Desktop Filters */}
                    <div className="hidden lg:block">
                        <div className="sticky top-24 space-y-6">
                            {filters}
                        </div>
                    </div>

                    {/* Mobile Filters Toggle */}
                    <div className="lg:hidden mb-4">
                        <Button
                            variant="outline"
                            onClick={() => setShowMobileFilters(!showMobileFilters)}
                            className="w-full"
                        >
                            {showMobileFilters ? 'Hide' : 'Show'} Filters
                        </Button>

                        {showMobileFilters && (
                            <motion.div
                                initial={{ opacity: 0, height: 0 }}
                                animate={{ opacity: 1, height: 'auto' }}
                                exit={{ opacity: 0, height: 0 }}
                                className="mt-4 p-4 border rounded-lg bg-background"
                            >
                                {filters}
                            </motion.div>
                        )}
                    </div>
                </>
            )}

            {/* Products */}
            <div className={cn(
                showFilterSidebar ? 'lg:col-span-3' : 'col-span-full'
            )}>
                {/* Sort Options */}
                {sortOptions && (
                    <div className="mb-6">
                        {sortOptions}
                    </div>
                )}

                <ProductGrid {...productGridProps} />
            </div>
        </div>
    );
};

export default ProductGrid;
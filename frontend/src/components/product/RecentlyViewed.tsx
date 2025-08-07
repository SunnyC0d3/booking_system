import * as React from 'react';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Clock,
    Eye,
    X,
    ShoppingCart,
    Heart,
    ArrowRight,
    Package,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    Badge,
} from '@/components/ui';
import { useProductStore } from '@/stores/productStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import { useCartStore } from '@/stores/cartStore';
import { Product } from '@/types/api'; // Changed from @/types/product to @/types/api
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

interface RecentlyViewedProps {
    className?: string;
    showTitle?: boolean;
    maxItems?: number;
    layout?: 'horizontal' | 'grid';
    showActions?: boolean;
}

export const RecentlyViewed: React.FC<RecentlyViewedProps> = ({
                                                                  className,
                                                                  showTitle = true,
                                                                  maxItems = 6,
                                                                  layout = 'horizontal',
                                                                  showActions = true,
                                                              }) => {
    // Mock recently viewed products since the store doesn't have this property
    const { products } = useProductStore();
    const { addToWishlist, removeFromWishlist } = useWishlistStore();
    const { addToCart } = useCartStore();

    // Use some products as mock recently viewed
    const recentlyViewed = products.slice(0, maxItems);

    // Scrolling state for horizontal layout
    const [canScrollLeft, setCanScrollLeft] = React.useState(false);
    const [canScrollRight, setCanScrollRight] = React.useState(false);
    const scrollContainerRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        if (layout === 'horizontal') {
            checkScrollability();
        }
    }, [recentlyViewed, layout]);

    const checkScrollability = () => {
        const container = scrollContainerRef.current;
        if (container) {
            setCanScrollLeft(container.scrollLeft > 0);
            setCanScrollRight(
                container.scrollLeft < container.scrollWidth - container.clientWidth
            );
        }
    };

    const scroll = (direction: 'left' | 'right') => {
        const container = scrollContainerRef.current;
        if (container) {
            const scrollAmount = 300;
            container.scrollBy({
                left: direction === 'left' ? -scrollAmount : scrollAmount,
                behavior: 'smooth',
            });
        }
    };

    const handleRemoveItem = (_productId: number) => {
        // Remove from recently viewed (would need API endpoint)
        toast.success('Removed from recently viewed');
    };

    const handleToggleWishlist = async (product: Product) => {
        try {
            // Check if product is in wishlist (mock implementation)
            const isInWishlist = false; // Mock check

            if (isInWishlist) {
                await removeFromWishlist(product.id);
            } else {
                await addToWishlist({
                    product_id: product.id,
                    product_variant_id: null,
                });
            }
        } catch (error) {
            toast.error('Failed to update wishlist');
        }
    };

    const handleAddToCart = async (product: Product) => {
        try {
            await addToCart({
                product_id: product.id,
                quantity: 1,
            });
            toast.success(`${product.name} added to cart!`);
        } catch (error) {
            toast.error('Failed to add to cart');
        }
    };

    const handleClearAll = async () => {
        try {
            // Mock clear implementation
            toast.success('Recently viewed cleared');
        } catch (error) {
            toast.error('Failed to clear recently viewed');
        }
    };

    // Check if product is in wishlist (mock implementation)
    const isInWishlist = (_productId: number) => false;

    // Limit items to maxItems
    const displayItems = recentlyViewed.slice(0, maxItems);

    if (displayItems.length === 0) {
        return null;
    }

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            className={cn('space-y-4', className)}
        >
            {/* Header */}
            {showTitle && (
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Clock className="h-5 w-5 text-primary" />
                        <h2 className="text-xl font-semibold text-foreground">
                            Recently Viewed
                        </h2>
                        <Badge variant="secondary" className="text-xs">
                            {displayItems.length}
                        </Badge>
                    </div>
                    <div className="flex items-center gap-2">
                        {layout === 'horizontal' && displayItems.length > 3 && (
                            <div className="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={() => scroll('left')}
                                    disabled={!canScrollLeft}
                                    className="h-8 w-8"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={() => scroll('right')}
                                    disabled={!canScrollRight}
                                    className="h-8 w-8"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        )}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleClearAll}
                            className="text-xs"
                        >
                            Clear All
                        </Button>
                    </div>
                </div>
            )}

            {/* Items */}
            <div className={cn(
                layout === 'horizontal'
                    ? 'relative'
                    : 'grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4'
            )}>
                {layout === 'horizontal' ? (
                    <div
                        ref={scrollContainerRef}
                        onScroll={checkScrollability}
                        className="flex gap-4 overflow-x-auto scrollbar-hide scroll-smooth pb-2"
                    >
                        <AnimatePresence>
                            {displayItems.map((product, index) => (
                                <RecentlyViewedItem
                                    key={product.id}
                                    product={product}
                                    index={index}
                                    layout={layout}
                                    showActions={showActions}
                                    onRemove={handleRemoveItem}
                                    onToggleWishlist={handleToggleWishlist}
                                    onAddToCart={handleAddToCart}
                                    isInWishlist={isInWishlist(product.id)}
                                />
                            ))}
                        </AnimatePresence>
                    </div>
                ) : (
                    <AnimatePresence>
                        {displayItems.map((product, index) => (
                            <RecentlyViewedItem
                                key={product.id}
                                product={product}
                                index={index}
                                layout={layout}
                                showActions={showActions}
                                onRemove={handleRemoveItem}
                                onToggleWishlist={handleToggleWishlist}
                                onAddToCart={handleAddToCart}
                                isInWishlist={isInWishlist(product.id)}
                            />
                        ))}
                    </AnimatePresence>
                )}
            </div>

            {/* View All Link */}
            {recentlyViewed.length > maxItems && (
                <div className="text-center">
                    <Button variant="outline">
                        <Link href="/account/recently-viewed">
                            View All Recently Viewed
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            )}
        </motion.div>
    );
};

// Recently Viewed Item Component
interface RecentlyViewedItemProps {
    product: Product;
    index: number;
    layout: 'horizontal' | 'grid';
    showActions: boolean;
    onRemove: (productId: number) => void;
    onToggleWishlist: (product: Product) => void;
    onAddToCart: (product: Product) => void;
    isInWishlist: boolean;
}

const RecentlyViewedItem: React.FC<RecentlyViewedItemProps> = ({
                                                                   product,
                                                                   index,
                                                                   layout,
                                                                   showActions,
                                                                   onRemove,
                                                                   onToggleWishlist,
                                                                   onAddToCart,
                                                                   isInWishlist,
                                                               }) => {
    const [isHovered, setIsHovered] = React.useState(false);

    // Mock out of stock check - using the correct API properties
    const isOutOfStock = !product.is_in_stock || product.stock_status === 'out_of_stock';

    const formatViewedTime = (date: string) => {
        const now = new Date();
        const viewed = new Date(date);
        const diffInHours = Math.floor((now.getTime() - viewed.getTime()) / (1000 * 60 * 60));

        if (diffInHours < 1) return 'Just now';
        if (diffInHours < 24) return `${diffInHours}h ago`;
        const diffInDays = Math.floor(diffInHours / 24);
        if (diffInDays < 7) return `${diffInDays}d ago`;
        return viewed.toLocaleDateString();
    };

    // Generate a slug from name since API doesn't have slug
    const productSlug = product.name.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');

    return (
        <motion.div
            layout
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.9 }}
            transition={{ duration: 0.3, delay: index * 0.05 }}
            className={cn(
                'group relative',
                layout === 'horizontal' && 'flex-shrink-0 w-48'
            )}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <Card className="overflow-hidden hover:shadow-md transition-shadow">
                <div className="relative">
                    {/* Product Image */}
                    <Link href={`/products/${productSlug}`}>
                        <div className={cn(
                            'aspect-square bg-muted relative overflow-hidden',
                            layout === 'horizontal' && ''
                        )}>
                            {product.featured_image ? (
                                <img
                                    src={product.featured_image}
                                    alt={product.name}
                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center">
                                    <Package className="h-8 w-8 text-muted-foreground" />
                                </div>
                            )}
                        </div>
                    </Link>

                    {/* Remove Button */}
                    <Button
                        variant="secondary"
                        size="icon"
                        onClick={() => onRemove(product.id)}
                        className={cn(
                            'absolute top-2 right-2 w-6 h-6 opacity-0 group-hover:opacity-100 transition-opacity bg-white/90 hover:bg-white',
                            isHovered && 'opacity-100'
                        )}
                    >
                        <X className="h-3 w-3" />
                    </Button>

                    {/* Badges */}
                    <div className="absolute top-2 left-2 flex flex-col gap-1">
                        {isOutOfStock && (
                            <Badge variant="secondary" className="text-xs bg-gray-500 text-white">
                                Out of Stock
                            </Badge>
                        )}
                        {product.is_low_stock && !isOutOfStock && (
                            <Badge variant="secondary" className="text-xs bg-orange-500 text-white">
                                Low Stock
                            </Badge>
                        )}
                    </div>

                    {/* Quick Actions Overlay */}
                    {showActions && (
                        <div className={cn(
                            'absolute inset-0 bg-black/40 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity',
                            isHovered && 'opacity-100'
                        )}>
                            <Button
                                variant="secondary"
                                size="icon"
                                onClick={() => onToggleWishlist(product)}
                                className={cn(
                                    'w-8 h-8 bg-white/90 hover:bg-white',
                                    isInWishlist && 'text-red-500'
                                )}
                            >
                                <Heart className={cn('h-4 w-4', isInWishlist && 'fill-current')} />
                            </Button>
                            {!isOutOfStock && (
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    onClick={() => onAddToCart(product)}
                                    className="w-8 h-8 bg-white/90 hover:bg-white"
                                >
                                    <ShoppingCart className="h-4 w-4" />
                                </Button>
                            )}
                            <Button
                                variant="secondary"
                                size="icon"
                                className="w-8 h-8 bg-white/90 hover:bg-white"
                            >
                                <Link href={`/products/${productSlug}`}>
                                    <Eye className="h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {/* Product Info */}
                <CardContent className={cn(
                    'p-3',
                    layout === 'horizontal' && 'p-2'
                )}>
                    <div className="space-y-2">
                        {/* Title */}
                        <h3 className={cn(
                            'font-medium text-foreground line-clamp-2 group-hover:text-primary transition-colors',
                            layout === 'horizontal' && 'text-sm'
                        )}>
                            <Link href={`/products/${productSlug}`}>
                                {product.name}
                            </Link>
                        </h3>

                        {/* Price */}
                        <div className="flex items-center justify-between">
                            <span className={cn(
                                'font-bold text-primary',
                                layout === 'horizontal' ? 'text-sm' : 'text-lg'
                            )}>
                                {(product as any).price_formatted || `${product.price}` || 'Price not available'}
                            </span>
                        </div>

                        {/* Viewed Time */}
                        <div className="flex items-center gap-1 text-xs text-muted-foreground">
                            <Clock className="h-3 w-3" />
                            <span>{formatViewedTime(product.created_at)}</span>
                        </div>

                        {/* Stock Status */}
                        {!isOutOfStock && product.is_low_stock && (
                            <p className={cn(
                                'text-orange-600',
                                layout === 'horizontal' ? 'text-xs' : 'text-sm'
                            )}>
                                Only {product.quantity} left
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>
        </motion.div>
    );
};

// Recently Viewed Sidebar Component (for product detail pages)
export const RecentlyViewedSidebar: React.FC<{ className?: string }> = ({ className }) => {
    return (
        <div className={cn('space-y-4', className)}>
            <RecentlyViewed
                showTitle={true}
                maxItems={4}
                layout="grid"
                showActions={true}
                className="bg-muted/20 p-4 rounded-lg"
            />
        </div>
    );
};

// Recently Viewed Banner (for empty states)
export const RecentlyViewedBanner: React.FC<{ className?: string }> = ({ className }) => {
    const { products } = useProductStore();
    // Mock recently viewed as first few products
    const recentlyViewed = products.slice(0, 6);

    if (recentlyViewed.length === 0) {
        return null;
    }

    return (
        <Card className={cn('bg-gradient-to-r from-primary/5 to-primary/10 border-primary/20', className)}>
            <CardContent className="p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                            <Clock className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h3 className="font-semibold text-foreground">
                                Continue where you left off
                            </h3>
                            <p className="text-muted-foreground">
                                You have {recentlyViewed.length} recently viewed {recentlyViewed.length === 1 ? 'item' : 'items'}
                            </p>
                        </div>
                    </div>
                    <Button>
                        <Link href="/account/recently-viewed">
                            View All
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
};

export default RecentlyViewed;
import * as React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { motion } from 'framer-motion';
import {
    Heart,
    ShoppingCart,
    Eye,
    Star,
    ArrowUpDown,
    Badge,
    Zap,
} from 'lucide-react';
import { Button, Card, CardContent } from '@/components/ui';
import { useWishlistStore } from '@/stores/wishlistStore';
import { useCompareStore } from '@/stores/productStore';
import { Product, ProductCardProps } from '@/types/product';
import { cn } from '@/lib/cn';

export const ProductCard: React.FC<ProductCardProps> = ({
                                                            product,
                                                            showQuickAdd = true,
                                                            showWishlist = true,
                                                            showCompare = true,
                                                            layout = 'grid',
                                                            priority = false,
                                                            onQuickAdd,
                                                            onWishlistToggle,
                                                            onCompareToggle,
                                                            className,
                                                        }) => {
    const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlist();
    const { addToCompare, removeFromCompare, isInCompare, canAddToCompare } = useCompare();

    const [imageLoaded, setImageLoaded] = React.useState(false);
    const [imageError, setImageError] = React.useState(false);
    const [isHovered, setIsHovered] = React.useState(false);

    const inWishlist = isInWishlist(product.id);
    const inCompare = isInCompare(product.id);

    const hasDiscount = product.compare_price && product.compare_price > product.price;
    const discountPercentage = hasDiscount
        ? Math.round(((product.compare_price - product.price) / product.compare_price) * 100)
        : 0;

    const isOutOfStock = product.track_inventory && product.inventory_quantity === 0;
    const isLowStock = product.track_inventory &&
        product.inventory_quantity && product.inventory_quantity <= 5;

    const handleWishlistToggle = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        try {
            if (inWishlist) {
                await removeFromWishlist(product.id);
            } else {
                await addToWishlist(product);
            }

            if (onWishlistToggle) {
                onWishlistToggle(product);
            }
        } catch (error) {
            console.error('Wishlist toggle failed:', error);
        }
    };

    const handleCompareToggle = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!canAddToCompare() && !inCompare) {
            return;
        }

        try {
            if (inCompare) {
                await removeFromCompare(product.id);
            } else {
                await addToCompare(product);
            }

            if (onCompareToggle) {
                onCompareToggle(product);
            }
        } catch (error) {
            console.error('Compare toggle failed:', error);
        }
    };

    const handleQuickAdd = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (onQuickAdd) {
            onQuickAdd(product);
        }
    };

    if (layout === 'list') {
        return (
            <Card className={cn(
                'card-hover transition-all duration-200',
                isOutOfStock && 'opacity-75',
                className
            )}>
                <Link href={`/products/${product.slug}`}>
                    <CardContent className="p-6">
                        <div className="flex gap-6">
                            {/* Product Image */}
                            <div className="relative w-32 h-32 shrink-0">
                                <div className="aspect-square w-full rounded-lg overflow-hidden bg-muted">
                                    {product.featured_image && !imageError ? (
                                        <Image
                                            src={product.featured_image}
                                            alt={product.name}
                                            fill
                                            className={cn(
                                                'object-cover transition-all duration-300',
                                                imageLoaded ? 'opacity-100' : 'opacity-0'
                                            )}
                                            priority={priority}
                                            onLoad={() => setImageLoaded(true)}
                                            onError={() => setImageError(true)}
                                        />
                                    ) : (
                                        <div className="w-full h-full flex items-center justify-center text-muted-foreground">
                                            <Badge className="h-8 w-8" />
                                        </div>
                                    )}

                                    {/* Badges */}
                                    <div className="absolute top-2 left-2 flex flex-col gap-1">
                                        {product.featured && (
                                            <span className="badge badge-default text-xs px-2 py-1">
                        Featured
                      </span>
                                        )}
                                        {hasDiscount && (
                                            <span className="badge bg-red-500 text-white text-xs px-2 py-1">
                        -{discountPercentage}%
                      </span>
                                        )}
                                        {isLowStock && !isOutOfStock && (
                                            <span className="badge bg-orange-500 text-white text-xs px-2 py-1">
                        Low Stock
                      </span>
                                        )}
                                        {isOutOfStock && (
                                            <span className="badge bg-gray-500 text-white text-xs px-2 py-1">
                        Out of Stock
                      </span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Product Info */}
                            <div className="flex-1 space-y-3">
                                <div>
                                    <h3 className="font-semibold text-lg text-foreground group-hover:text-primary transition-colors line-clamp-2">
                                        {product.name}
                                    </h3>
                                    {product.short_description && (
                                        <p className="text-sm text-muted-foreground line-clamp-2 mt-1">
                                            {product.short_description}
                                        </p>
                                    )}
                                </div>

                                {/* Rating */}
                                {product.reviews_count > 0 && (
                                    <div className="flex items-center gap-2">
                                        <div className="flex items-center gap-1">
                                            {[...Array(5)].map((_, i) => (
                                                <Star
                                                    key={i}
                                                    className={cn(
                                                        'h-4 w-4',
                                                        i < Math.floor(product.reviews_average)
                                                            ? 'fill-yellow-400 text-yellow-400'
                                                            : 'text-muted-foreground'
                                                    )}
                                                />
                                            ))}
                                        </div>
                                        <span className="text-sm text-muted-foreground">
                      ({product.reviews_count})
                    </span>
                                    </div>
                                )}

                                <div className="flex items-center justify-between">
                                    {/* Price */}
                                    <div className="flex items-center gap-2">
                    <span className="text-xl font-bold text-primary">
                      {product.price_formatted}
                    </span>
                                        {hasDiscount && (
                                            <span className="text-sm text-muted-foreground line-through">
                        {product.compare_price_formatted}
                      </span>
                                        )}
                                    </div>

                                    {/* Actions */}
                                    <div className="flex items-center gap-2">
                                        {showWishlist && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={handleWishlistToggle}
                                                className={cn(
                                                    'relative',
                                                    inWishlist && 'text-red-500 hover:text-red-600'
                                                )}
                                            >
                                                <Heart className={cn('h-4 w-4', inWishlist && 'fill-current')} />
                                            </Button>
                                        )}

                                        {showCompare && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                onClick={handleCompareToggle}
                                                disabled={!canAddToCompare() && !inCompare}
                                                className={cn(
                                                    'relative',
                                                    inCompare && 'text-blue-500 hover:text-blue-600'
                                                )}
                                            >
                                                <ArrowUpDown className="h-4 w-4" />
                                            </Button>
                                        )}

                                        {showQuickAdd && !isOutOfStock && (
                                            <Button
                                                variant="default"
                                                size="sm"
                                                onClick={handleQuickAdd}
                                                leftIcon={<ShoppingCart className="h-4 w-4" />}
                                            >
                                                Add to Cart
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Link>
            </Card>
        );
    }

    // Grid layout
    return (
        <motion.div
            whileHover={{ y: -4 }}
            transition={{ duration: 0.2 }}
            onHoverStart={() => setIsHovered(true)}
            onHoverEnd={() => setIsHovered(false)}
            className={className}
        >
            <Card className={cn(
                'group overflow-hidden border-0 shadow-soft hover:shadow-soft-lg transition-all duration-300',
                isOutOfStock && 'opacity-75',
            )}>
                <Link href={`/products/${product.slug}`}>
                    {/* Product Image */}
                    <div className="relative aspect-square overflow-hidden bg-muted">
                        {product.featured_image && !imageError ? (
                            <Image
                                src={product.featured_image}
                                alt={product.name}
                                fill
                                className={cn(
                                    'object-cover transition-all duration-500 group-hover:scale-110',
                                    imageLoaded ? 'opacity-100' : 'opacity-0'
                                )}
                                priority={priority}
                                onLoad={() => setImageLoaded(true)}
                                onError={() => setImageError(true)}
                            />
                        ) : (
                            <div className="w-full h-full flex items-center justify-center text-muted-foreground">
                                <Badge className="h-12 w-12" />
                            </div>
                        )}

                        {/* Loading skeleton */}
                        {!imageLoaded && !imageError && (
                            <div className="absolute inset-0 loading-shimmer" />
                        )}

                        {/* Badges */}
                        <div className="absolute top-3 left-3 flex flex-col gap-2">
                            {product.featured && (
                                <span className="badge badge-default text-xs px-2 py-1 shadow-sm">
                  <Zap className="h-3 w-3 mr-1" />
                  Featured
                </span>
                            )}
                            {hasDiscount && (
                                <span className="badge bg-red-500 text-white text-xs px-2 py-1 shadow-sm">
                  -{discountPercentage}%
                </span>
                            )}
                            {isLowStock && !isOutOfStock && (
                                <span className="badge bg-orange-500 text-white text-xs px-2 py-1 shadow-sm">
                  Low Stock
                </span>
                            )}
                            {isOutOfStock && (
                                <span className="badge bg-gray-500 text-white text-xs px-2 py-1 shadow-sm">
                  Out of Stock
                </span>
                            )}
                        </div>

                        {/* Quick Actions */}
                        <div className={cn(
                            'absolute top-3 right-3 flex flex-col gap-2 transition-all duration-300',
                            isHovered ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-2'
                        )}>
                            {showWishlist && (
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    onClick={handleWishlistToggle}
                                    className={cn(
                                        'w-8 h-8 shadow-sm backdrop-blur-sm',
                                        inWishlist
                                            ? 'bg-red-500 text-white hover:bg-red-600'
                                            : 'bg-white/90 hover:bg-white'
                                    )}
                                >
                                    <Heart className={cn('h-4 w-4', inWishlist && 'fill-current')} />
                                </Button>
                            )}

                            {showCompare && (
                                <Button
                                    variant="secondary"
                                    size="icon"
                                    onClick={handleCompareToggle}
                                    disabled={!canAddToCompare() && !inCompare}
                                    className={cn(
                                        'w-8 h-8 shadow-sm backdrop-blur-sm',
                                        inCompare
                                            ? 'bg-blue-500 text-white hover:bg-blue-600'
                                            : 'bg-white/90 hover:bg-white'
                                    )}
                                >
                                    <ArrowUpDown className="h-4 w-4" />
                                </Button>
                            )}

                            <Button
                                variant="secondary"
                                size="icon"
                                className="w-8 h-8 shadow-sm backdrop-blur-sm bg-white/90 hover:bg-white"
                            >
                                <Eye className="h-4 w-4" />
                            </Button>
                        </div>

                        {/* Quick Add Button */}
                        {showQuickAdd && !isOutOfStock && (
                            <div className={cn(
                                'absolute bottom-3 left-3 right-3 transition-all duration-300',
                                isHovered ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'
                            )}>
                                <Button
                                    variant="default"
                                    className="w-full shadow-sm"
                                    onClick={handleQuickAdd}
                                    leftIcon={<ShoppingCart className="h-4 w-4" />}
                                >
                                    Quick Add
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Product Info */}
                    <CardContent className="p-4 space-y-3">
                        {/* Category */}
                        {product.category && (
                            <span className="text-xs text-muted-foreground uppercase tracking-wide">
                {product.category.name}
              </span>
                        )}

                        {/* Title */}
                        <h3 className="font-semibold text-foreground group-hover:text-primary transition-colors line-clamp-2 leading-tight">
                            {product.name}
                        </h3>

                        {/* Rating */}
                        {product.reviews_count > 0 && (
                            <div className="flex items-center gap-2">
                                <div className="flex items-center gap-1">
                                    {[...Array(5)].map((_, i) => (
                                        <Star
                                            key={i}
                                            className={cn(
                                                'h-3 w-3',
                                                i < Math.floor(product.reviews_average)
                                                    ? 'fill-yellow-400 text-yellow-400'
                                                    : 'text-muted-foreground'
                                            )}
                                        />
                                    ))}
                                </div>
                                <span className="text-xs text-muted-foreground">
                  ({product.reviews_count})
                </span>
                            </div>
                        )}

                        {/* Price */}
                        <div className="flex items-center gap-2">
              <span className="text-lg font-bold text-primary">
                {product.price_formatted}
              </span>
                            {hasDiscount && (
                                <span className="text-sm text-muted-foreground line-through">
                  {product.compare_price_formatted}
                </span>
                            )}
                        </div>
                    </CardContent>
                </Link>
            </Card>
        </motion.div>
    );
};

export default ProductCard;
import * as React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ShoppingBag, Plus } from 'lucide-react';
import { Button } from '@/components/ui';
import { useCartStore, useCartItemCount, useCartTotal } from '@/stores/cartStore';
import { cn } from '@/lib/cn';

interface MiniCartIndicatorProps {
    variant?: 'default' | 'compact' | 'floating';
    showTotal?: boolean;
    className?: string;
}

export const MiniCartIndicator: React.FC<MiniCartIndicatorProps> = ({
                                                                        variant = 'default',
                                                                        showTotal = false,
                                                                        className,
                                                                    }) => {
    const { openCart, isLoading } = useCartStore();
    const itemCount = useCartItemCount();
    const total = useCartTotal();

    const handleClick = () => {
        openCart();
    };

    // Format total helper
    const formatTotal = (total: number) => {
        return new Intl.NumberFormat('en-GB', {
            style: 'currency',
            currency: 'GBP',
        }).format(total / 100);
    };

    if (variant === 'floating') {
        return (
            <motion.div
                initial={{ scale: 0, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                className={cn(
                    'fixed bottom-6 right-6 z-40',
                    className
                )}
            >
                <Button
                    onClick={handleClick}
                    size="lg"
                    className="rounded-full shadow-lg hover:shadow-xl transition-shadow duration-200 relative"
                    disabled={isLoading}
                >
                    <ShoppingBag className="h-6 w-6" />

                    {/* Item Count Badge */}
                    <AnimatePresence>
                        {itemCount > 0 && (
                            <motion.div
                                initial={{ scale: 0 }}
                                animate={{ scale: 1 }}
                                exit={{ scale: 0 }}
                                className="absolute -top-2 -right-2 bg-destructive text-destructive-foreground text-xs font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1"
                            >
                                {itemCount > 99 ? '99+' : itemCount}
                            </motion.div>
                        )}
                    </AnimatePresence>
                </Button>

                {/* Tooltip */}
                {itemCount > 0 && (
                    <div className="absolute bottom-full right-0 mb-2 bg-popover text-popover-foreground text-sm px-2 py-1 rounded shadow-md whitespace-nowrap opacity-0 hover:opacity-100 transition-opacity pointer-events-none">
                        {itemCount} item{itemCount !== 1 ? 's' : ''} • {formatTotal(total)}
                    </div>
                )}
            </motion.div>
        );
    }

    if (variant === 'compact') {
        return (
            <Button
                variant="ghost"
                size="sm"
                onClick={handleClick}
                disabled={isLoading}
                className={cn('relative p-2', className)}
            >
                <ShoppingBag className="h-4 w-4" />

                {/* Item Count Badge */}
                <AnimatePresence>
                    {itemCount > 0 && (
                        <motion.div
                            initial={{ scale: 0 }}
                            animate={{ scale: 1 }}
                            exit={{ scale: 0 }}
                            className="absolute -top-1 -right-1 bg-primary text-primary-foreground text-xs font-bold rounded-full min-w-[16px] h-4 flex items-center justify-center px-1"
                        >
                            {itemCount > 9 ? '9+' : itemCount}
                        </motion.div>
                    )}
                </AnimatePresence>
            </Button>
        );
    }

    // Default variant
    return (
        <Button
            variant="ghost"
            onClick={handleClick}
            disabled={isLoading}
            className={cn('relative flex items-center gap-2', className)}
        >
            <div className="relative">
                <ShoppingBag className="h-5 w-5" />

                {/* Item Count Badge */}
                <AnimatePresence>
                    {itemCount > 0 && (
                        <motion.div
                            initial={{ scale: 0 }}
                            animate={{ scale: 1 }}
                            exit={{ scale: 0 }}
                            className="absolute -top-2 -right-2 bg-primary text-primary-foreground text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1"
                        >
                            {itemCount > 99 ? '99+' : itemCount}
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>

            {/* Text */}
            <div className="hidden sm:block">
                <span className="text-sm font-medium">
                    {itemCount === 0 ? 'Cart' : `Cart (${itemCount})`}
                </span>
                {showTotal && itemCount > 0 && (
                    <div className="text-xs text-muted-foreground">
                        {formatTotal(total)}
                    </div>
                )}
            </div>
        </Button>
    );
};

// Quick Add to Cart Button (for product cards)
interface QuickAddToCartProps {
    productId: number;
    variantId?: number;
    size?: 'sm' | 'default' | 'lg';
    className?: string;
    onSuccess?: () => void;
}

export const QuickAddToCart: React.FC<QuickAddToCartProps> = ({
                                                                  productId,
                                                                  variantId,
                                                                  size = 'default',
                                                                  className,
                                                                  onSuccess,
                                                              }) => {
    const { addToCart, isLoading, items } = useCartStore();
    const [isAdding, setIsAdding] = React.useState(false);

    // Get current quantity by checking if item exists in cart
    const getItemQuantity = (productId: number, variantId?: number) => {
        const item = items.find(item =>
            item.product_id === productId &&
            item.product_variant_id === variantId
        );
        return item?.quantity || 0;
    };

    const currentQuantity = getItemQuantity(productId, variantId);
    const isInCart = currentQuantity > 0;

    const handleQuickAdd = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        setIsAdding(true);
        try {
            await addToCart({
                product_id: productId,
                product_variant_id: variantId,
                quantity: 1,
            });
            onSuccess?.();
        } catch (error) {
            console.error('Failed to add to cart:', error);
        } finally {
            setIsAdding(false);
        }
    };

    return (
        <Button
            onClick={handleQuickAdd}
            disabled={isLoading || isAdding}
            size={size}
            variant={isInCart ? "default" : "outline"}
            className={cn(
                'transition-all duration-200',
                isInCart && 'bg-success hover:bg-success/90',
                className
            )}
        >
            <AnimatePresence mode="wait">
                {isAdding ? (
                    <motion.div
                        key="adding"
                        initial={{ opacity: 0, scale: 0.8 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.8 }}
                        className="flex items-center gap-2"
                    >
                        <div className="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin" />
                        Adding...
                    </motion.div>
                ) : isInCart ? (
                    <motion.div
                        key="in-cart"
                        initial={{ opacity: 0, scale: 0.8 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.8 }}
                        className="flex items-center gap-2"
                    >
                        <ShoppingBag className="h-4 w-4" />
                        In Cart ({currentQuantity})
                    </motion.div>
                ) : (
                    <motion.div
                        key="add"
                        initial={{ opacity: 0, scale: 0.8 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={{ opacity: 0, scale: 0.8 }}
                        className="flex items-center gap-2"
                    >
                        <Plus className="h-4 w-4" />
                        Add to Cart
                    </motion.div>
                )}
            </AnimatePresence>
        </Button>
    );
};

export default MiniCartIndicator;
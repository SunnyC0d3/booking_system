'use client'

import * as React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
    Plus,
    Minus,
    Trash2,
    AlertTriangle,
    ExternalLink,
} from 'lucide-react';
import { Button, Card, CardContent } from '@/components/ui';
import { useCartStore } from '@/stores/cartStore';
import { CartItem as CartItemType } from '@/types/api';
import { cn } from '@/lib/cn';

interface CartItemProps {
    item: CartItemType;
    layout?: 'default' | 'compact' | 'checkout';
    showRemove?: boolean;
    showQuantityControls?: boolean;
    className?: string;
}

export const CartItem: React.FC<CartItemProps> = ({
                                                      item,
                                                      layout = 'default',
                                                      showRemove = true,
                                                      showQuantityControls = true,
                                                      className,
                                                  }) => {
    const {
        updateCartItem,
        removeFromCart,
        incrementItem,
        decrementItem,
        isLoading,
    } = useCartStore();

    const [isUpdating, setIsUpdating] = React.useState(false);

    const handleQuantityChange = async (newQuantity: number) => {
        if (newQuantity < 0) return;

        setIsUpdating(true);
        try {
            if (newQuantity === 0) {
                await removeFromCart(item.id);
            } else {
                await updateCartItem(item.id, newQuantity);
            }
        } catch (error) {
            console.error('Failed to update quantity:', error);
        } finally {
            setIsUpdating(false);
        }
    };

    const handleIncrement = async () => {
        setIsUpdating(true);
        try {
            await incrementItem(item.id);
        } catch (error) {
            console.error('Failed to increment:', error);
        } finally {
            setIsUpdating(false);
        }
    };

    const handleDecrement = async () => {
        setIsUpdating(true);
        try {
            await decrementItem(item.id);
        } catch (error) {
            console.error('Failed to decrement:', error);
        } finally {
            setIsUpdating(false);
        }
    };

    const handleRemove = async () => {
        setIsUpdating(true);
        try {
            await removeFromCart(item.id);
        } catch (error) {
            console.error('Failed to remove item:', error);
        } finally {
            setIsUpdating(false);
        }
    };

    // Check for issues
    const hasIssues = !item.is_available || item.has_price_changed;
    const isOutOfStock = !item.is_available;
    const isLowStock = item.available_stock > 0 && item.available_stock < 5;

    if (layout === 'compact') {
        return (
            <motion.div
                layout
                initial={{ opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                exit={{ opacity: 0, scale: 0.9 }}
                className={cn('flex items-center gap-3 p-3 bg-background rounded-lg', className)}
            >
                {/* Image */}
                <div className="relative w-12 h-12 flex-shrink-0">
                    {item.product?.featured_image ? (
                        <Image
                            src={item.product.featured_image}
                            alt={item.product.name || 'Product'}
                            fill
                            className="object-cover rounded-md"
                        />
                    ) : (
                        <div className="w-full h-full bg-muted rounded-md flex items-center justify-center">
                            <span className="text-xs text-muted-foreground">No image</span>
                        </div>
                    )}
                </div>

                {/* Details */}
                <div className="flex-1 min-w-0">
                    <h4 className="font-medium text-sm truncate">
                        {item.product?.name || 'Unknown Product'}
                    </h4>
                    {item.product_variant && (
                        <p className="text-xs text-muted-foreground">
                            {item.product_variant.product_attribute?.name}: {item.product_variant.value}
                        </p>
                    )}
                    <div className="flex items-center gap-2 mt-1">
                        <span className="text-sm font-medium">
                            {item.line_total_formatted}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            × {item.quantity}
                        </span>
                    </div>
                </div>

                {/* Quantity Controls */}
                {showQuantityControls && (
                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleDecrement}
                            disabled={isUpdating || isLoading}
                            className="h-6 w-6 p-0"
                        >
                            <Minus className="h-3 w-3" />
                        </Button>

                        <span className="w-8 text-center text-sm font-medium">
                            {item.quantity}
                        </span>

                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={handleIncrement}
                            disabled={isUpdating || isLoading || item.quantity >= item.available_stock}
                            className="h-6 w-6 p-0"
                        >
                            <Plus className="h-3 w-3" />
                        </Button>
                    </div>
                )}
            </motion.div>
        );
    }

    return (
        <motion.div
            layout
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            className={className}
        >
            <Card className={cn(
                'overflow-hidden',
                hasIssues && 'ring-2 ring-warning/50',
                isOutOfStock && 'ring-destructive/50'
            )}>
                <CardContent className="p-4">
                    <div className="flex gap-4">
                        {/* Product Image */}
                        <div className="relative w-20 h-20 flex-shrink-0">
                            {item.product?.featured_image ? (
                                <Image
                                    src={item.product.featured_image}
                                    alt={item.product.name || 'Product'}
                                    fill
                                    className="object-cover rounded-lg"
                                />
                            ) : (
                                <div className="w-full h-full bg-muted rounded-lg flex items-center justify-center">
                                    <span className="text-xs text-muted-foreground">No image</span>
                                </div>
                            )}

                            {/* Status Badge */}
                            {isOutOfStock && (
                                <div className="absolute -top-1 -right-1 bg-destructive text-destructive-foreground text-xs px-1.5 py-0.5 rounded-full">
                                    Out
                                </div>
                            )}
                            {isLowStock && !isOutOfStock && (
                                <div className="absolute -top-1 -right-1 bg-warning text-warning-foreground text-xs px-1.5 py-0.5 rounded-full">
                                    Low
                                </div>
                            )}
                        </div>

                        {/* Product Details */}
                        <div className="flex-1 min-w-0">
                            <div className="flex items-start justify-between">
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={`/products/${item.product?.id || ''}`}
                                        className="font-medium text-foreground hover:text-primary transition-colors group inline-flex items-center gap-1"
                                    >
                                        <span className="truncate">
                                            {item.product?.name || 'Unknown Product'}
                                        </span>
                                        <ExternalLink className="h-3 w-3 opacity-0 group-hover:opacity-100 transition-opacity" />
                                    </Link>

                                    {/* Variant Info */}
                                    {item.product_variant && (
                                        <p className="text-sm text-muted-foreground mt-1">
                                            {item.product_variant.product_attribute?.name}: {item.product_variant.value}
                                            {item.product_variant.additional_price_formatted && (
                                                <span className="ml-1">
                                                    (+{item.product_variant.additional_price_formatted})
                                                </span>
                                            )}
                                        </p>
                                    )}

                                    {/* Price */}
                                    <div className="flex items-center gap-2 mt-2">
                                        <span className="font-medium text-lg">
                                            {item.line_total_formatted}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            ({item.price_formatted} each)
                                        </span>
                                    </div>

                                    {/* Price Change Alert */}
                                    {item.has_price_changed && (
                                        <div className="flex items-center gap-1 mt-2 text-warning">
                                            <AlertTriangle className="h-3 w-3" />
                                            <span className="text-xs">Price updated since added</span>
                                        </div>
                                    )}

                                    {/* Stock Warning */}
                                    {isOutOfStock && (
                                        <div className="flex items-center gap-1 mt-2 text-destructive">
                                            <AlertTriangle className="h-3 w-3" />
                                            <span className="text-xs">Out of stock</span>
                                        </div>
                                    )}
                                    {isLowStock && !isOutOfStock && (
                                        <div className="flex items-center gap-1 mt-2 text-warning">
                                            <AlertTriangle className="h-3 w-3" />
                                            <span className="text-xs">
                                                Only {item.available_stock} left in stock
                                            </span>
                                        </div>
                                    )}
                                </div>

                                {/* Remove Button */}
                                {showRemove && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleRemove}
                                        disabled={isUpdating || isLoading}
                                        className="ml-2 text-muted-foreground hover:text-destructive"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>

                            {/* Quantity Controls */}
                            {showQuantityControls && (
                                <div className="flex items-center gap-3 mt-4">
                                    <span className="text-sm text-muted-foreground">Quantity:</span>

                                    <div className="flex items-center border rounded-lg">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleDecrement}
                                            disabled={isUpdating || isLoading}
                                            className="h-8 w-8 p-0 rounded-r-none"
                                        >
                                            <Minus className="h-4 w-4" />
                                        </Button>

                                        <span className="px-3 py-1 text-sm font-medium bg-muted/50 min-w-[50px] text-center">
                                            {item.quantity}
                                        </span>

                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={handleIncrement}
                                            disabled={
                                                isUpdating ||
                                                isLoading ||
                                                item.quantity >= item.available_stock
                                            }
                                            className="h-8 w-8 p-0 rounded-l-none"
                                        >
                                            <Plus className="h-4 w-4" />
                                        </Button>
                                    </div>

                                    {item.available_stock > 0 && (
                                        <span className="text-xs text-muted-foreground">
                                            {item.available_stock} available
                                        </span>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </CardContent>
            </Card>
        </motion.div>
    );
};

export default CartItem;
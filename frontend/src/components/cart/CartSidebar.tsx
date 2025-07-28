import * as React from 'react';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
    X,
    ShoppingBag,
    ArrowRight,
    Trash2,
    RefreshCw,
    AlertTriangle,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui';
import { CartItem } from './CartItem';
import { useCartStore, useCartItems, useCartTotal, useCartItemCount } from '@/stores/cartStore';
import { cn } from '@/lib/cn';

interface CartSidebarProps {
    className?: string;
}

export const CartSidebar: React.FC<CartSidebarProps> = ({ className }) => {
    const {
        isOpen,
        closeCart,
        clearCart,
        syncCartPrices,
        isLoading,
        error,
    } = useCartStore();

    const items = useCartItems();
    const total = useCartTotal();
    const itemCount = useCartItemCount();

    const [showClearConfirm, setShowClearConfirm] = React.useState(false);

    const handleClearCart = async () => {
        try {
            await clearCart();
            setShowClearConfirm(false);
        } catch (error) {
            console.error('Failed to clear cart:', error);
        }
    };

    const handleSyncPrices = async () => {
        try {
            await syncCartPrices();
        } catch (error) {
            console.error('Failed to sync prices:', error);
        }
    };

    const hasItems = items.length > 0;
    const hasIssues = items.some(item => !item.is_available || item.has_price_changed);

    return (
        <>
            {/* Overlay */}
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="fixed inset-0 bg-black/50 backdrop-blur-sm z-40"
                        onClick={closeCart}
                    />
                )}
            </AnimatePresence>

            {/* Sidebar */}
            <AnimatePresence>
                {isOpen && (
                    <motion.div
                        initial={{ x: '100%' }}
                        animate={{ x: 0 }}
                        exit={{ x: '100%' }}
                        transition={{ type: 'spring', damping: 30, stiffness: 300 }}
                        className={cn(
                            'fixed right-0 top-0 h-full w-full max-w-md bg-background border-l shadow-xl z-50 flex flex-col',
                            className
                        )}
                    >
                        {/* Header */}
                        <div className="flex items-center justify-between p-4 border-b">
                            <div className="flex items-center gap-2">
                                <ShoppingBag className="h-5 w-5" />
                                <h2 className="text-lg font-semibold">
                                    Shopping Cart ({itemCount})
                                </h2>
                            </div>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={closeCart}
                                className="p-2"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>

                        {/* Error Banner */}
                        {error && (
                            <div className="bg-destructive/10 border-destructive/20 border-l-4 border-l-destructive p-3 m-4 rounded">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle className="h-4 w-4 text-destructive" />
                                    <p className="text-sm text-destructive">{error}</p>
                                </div>
                            </div>
                        )}

                        {/* Issues Banner */}
                        {hasIssues && (
                            <div className="bg-warning/10 border-warning/20 border-l-4 border-l-warning p-3 m-4 rounded">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <AlertTriangle className="h-4 w-4 text-warning" />
                                        <p className="text-sm text-warning">
                                            Some items have price changes or are out of stock
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={handleSyncPrices}
                                        disabled={isLoading}
                                        className="text-warning hover:text-warning"
                                    >
                                        <RefreshCw className={cn(
                                            "h-3 w-3",
                                            isLoading && "animate-spin"
                                        )} />
                                    </Button>
                                </div>
                            </div>
                        )}

                        {/* Cart Items */}
                        <div className="flex-1 overflow-y-auto">
                            {isLoading && items.length === 0 ? (
                                <div className="flex items-center justify-center h-40">
                                    <div className="text-center space-y-2">
                                        <RefreshCw className="h-6 w-6 animate-spin mx-auto text-muted-foreground" />
                                        <p className="text-sm text-muted-foreground">Loading cart...</p>
                                    </div>
                                </div>
                            ) : !hasItems ? (
                                <div className="flex flex-col items-center justify-center h-full p-8">
                                    <div className="w-24 h-24 bg-muted/50 rounded-full flex items-center justify-center mb-4">
                                        <ShoppingBag className="h-12 w-12 text-muted-foreground" />
                                    </div>
                                    <h3 className="text-lg font-semibold text-foreground mb-2">
                                        Your cart is empty
                                    </h3>
                                    <p className="text-muted-foreground text-center mb-6">
                                        Add some items to get started with your creative projects!
                                    </p>
                                    <Button onClick={closeCart} className="w-full max-w-xs">
                                        Continue Shopping
                                    </Button>
                                </div>
                            ) : (
                                <div className="p-4 space-y-4">
                                    <AnimatePresence mode="popLayout">
                                        {items.map((item) => (
                                            <CartItem
                                                key={`${item.product_id}-${item.product_variant_id || 'default'}`}
                                                item={item}
                                                layout="compact"
                                            />
                                        ))}
                                    </AnimatePresence>

                                    {/* Clear Cart Button */}
                                    {hasItems && (
                                        <div className="pt-4 border-t">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setShowClearConfirm(true)}
                                                disabled={isLoading}
                                                className="text-muted-foreground hover:text-destructive w-full"
                                            >
                                                <Trash2 className="h-4 w-4 mr-2" />
                                                Clear Cart
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Footer */}
                        {hasItems && (
                            <div className="border-t bg-muted/50 p-4 space-y-4">
                                {/* Total */}
                                <div className="flex items-center justify-between">
                                    <span className="text-base font-medium">Total:</span>
                                    <span className="text-xl font-bold text-primary">
                                        {total.formatted}
                                    </span>
                                </div>

                                {/* Action Buttons */}
                                <div className="space-y-2">
                                    <Link href="/cart" className="block">
                                        <Button
                                            variant="outline"
                                            className="w-full"
                                            onClick={closeCart}
                                        >
                                            View Cart
                                        </Button>
                                    </Link>

                                    <Link href="/checkout" className="block">
                                        <Button
                                            className="w-full"
                                            onClick={closeCart}
                                        >
                                            Checkout
                                            <ArrowRight className="h-4 w-4 ml-2" />
                                        </Button>
                                    </Link>
                                </div>

                                {/* Shipping Notice */}
                                <p className="text-xs text-muted-foreground text-center">
                                    Free shipping on orders over £50
                                </p>
                            </div>
                        )}
                    </motion.div>
                )}
            </AnimatePresence>

            {/* Clear Cart Confirmation Dialog */}
            <Dialog open={showClearConfirm} onOpenChange={setShowClearConfirm}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Clear Cart?</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <p className="text-muted-foreground">
                            Are you sure you want to remove all items from your cart? This action cannot be undone.
                        </p>
                        <div className="flex gap-3 justify-end">
                            <Button
                                variant="outline"
                                onClick={() => setShowClearConfirm(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleClearCart}
                                disabled={isLoading}
                            >
                                Clear Cart
                            </Button>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
};

export default CartSidebar;
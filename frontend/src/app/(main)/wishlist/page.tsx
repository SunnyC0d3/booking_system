import * as React from 'react';
import { Metadata } from 'next';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Heart,
    ShoppingCart,
    Trash2,
    Share2,
    ArrowRight,
    X,
    Star,
    Eye,
    Package,
    ShoppingBag,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    Badge,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui';
import { MainLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { useWishlistStore } from '@/stores/wishlistStore';
import { useCartStore } from '@/stores/cartStore';
import { ProductGrid } from '@/components/product/ProductGrid';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

export const metadata: Metadata = {
    title: 'My Wishlist | Creative Business',
    description: 'Save and manage your favorite products for future purchase.',
};

// Empty state component
const EmptyWishlist = () => (
    <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6 }}
        className="text-center py-16"
    >
        <div className="max-w-md mx-auto">
            <div className="w-24 h-24 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <Heart className="h-12 w-12 text-primary" />
            </div>
            <h2 className="text-2xl font-bold text-foreground mb-4">
                Your Wishlist is Empty
            </h2>
            <p className="text-muted-foreground mb-8 leading-relaxed">
                Start building your wishlist by browsing our products and clicking the heart icon
                on items you love. It's a great way to save items for later!
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
                <Button size="lg" asChild>
                    <Link href="/products">
                        <ShoppingBag className="mr-2 h-4 w-4" />
                        Browse Products
                    </Link>
                </Button>
                <Button variant="outline" size="lg" asChild>
                    <Link href="/collections">
                        <Eye className="mr-2 h-4 w-4" />
                        View Collections
                    </Link>
                </Button>
            </div>
        </div>
    </motion.div>
);

// Wishlist item component
interface WishlistItemProps {
    product: any;
    onRemove: (productId: number) => void;
    onAddToCart: (product: any) => void;
    index: number;
}

const WishlistItem: React.FC<WishlistItemProps> = ({
                                                       product,
                                                       onRemove,
                                                       onAddToCart,
                                                       index,
                                                   }) => {
    const [isRemoving, setIsRemoving] = React.useState(false);
    const [showRemoveDialog, setShowRemoveDialog] = React.useState(false);

    const hasDiscount = product.compare_price && product.compare_price > product.price;
    const discountPercentage = hasDiscount
        ? Math.round(((product.compare_price - product.price) / product.compare_price) * 100)
        : 0;

    const isOutOfStock = !product.is_in_stock;

    const handleRemove = async () => {
        setIsRemoving(true);
        try {
            await onRemove(product.id);
            setShowRemoveDialog(false);
        } catch (error) {
            console.error('Failed to remove from wishlist:', error);
        } finally {
            setIsRemoving(false);
        }
    };

    const handleAddToCart = () => {
        if (!isOutOfStock) {
            onAddToCart(product);
        }
    };

    return (
        <>
            <motion.div
                layout
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, x: -100 }}
                transition={{ duration: 0.3, delay: index * 0.1 }}
                className="group"
            >
                <Card className="overflow-hidden hover:shadow-lg transition-all duration-300">
                    <div className="relative">
                        {/* Product Image */}
                        <div className="aspect-square bg-muted relative overflow-hidden">
                            {product.featured_image ? (
                                <img
                                    src={product.featured_image}
                                    alt={product.name}
                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                            ) : (
                                <div className="w-full h-full flex items-center justify-center">
                                    <Package className="h-16 w-16 text-muted-foreground" />
                                </div>
                            )}

                            {/* Badges */}
                            <div className="absolute top-3 left-3 flex flex-col gap-2">
                                {hasDiscount && (
                                    <Badge className="bg-red-500 text-white">
                                        -{discountPercentage}%
                                    </Badge>
                                )}
                                {isOutOfStock && (
                                    <Badge variant="secondary" className="bg-gray-500 text-white">
                                        Out of Stock
                                    </Badge>
                                )}
                                {product.is_featured && (
                                    <Badge className="bg-primary text-primary-foreground">
                                        Featured
                                    </Badge>
                                )}
                            </div>

                            {/* Remove Button */}
                            <Button
                                variant="secondary"
                                size="icon"
                                onClick={() => setShowRemoveDialog(true)}
                                className="absolute top-3 right-3 w-8 h-8 opacity-0 group-hover:opacity-100 transition-opacity bg-white/90 hover:bg-white"
                            >
                                <X className="h-4 w-4" />
                            </Button>

                            {/* Quick Actions */}
                            <div className="absolute bottom-3 left-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        onClick={handleAddToCart}
                                        disabled={isOutOfStock}
                                        className="flex-1"
                                    >
                                        <ShoppingCart className="mr-2 h-4 w-4" />
                                        {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        asChild
                                        className="bg-white/90 hover:bg-white"
                                    >
                                        <Link href={`/products/${product.slug}`}>
                                            <Eye className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>

                        {/* Product Info */}
                        <CardContent className="p-4">
                            <div className="space-y-3">
                                {/* Title and Category */}
                                <div>
                                    <h3 className="font-semibold text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                                        <Link href={`/products/${product.slug}`}>
                                            {product.name}
                                        </Link>
                                    </h3>
                                    {product.category && (
                                        <p className="text-sm text-muted-foreground">
                                            {product.category.name}
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

                                {/* Price */}
                                <div className="flex items-center justify-between">
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

                                    {/* Date Added */}
                                    <span className="text-xs text-muted-foreground">
                                        Added {new Date(product.added_to_wishlist_at || product.created_at).toLocaleDateString()}
                                    </span>
                                </div>

                                {/* Stock Status */}
                                {!isOutOfStock && product.quantity <= 10 && (
                                    <p className="text-sm text-orange-600">
                                        Only {product.quantity} left in stock
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </div>
                </Card>
            </motion.div>

            {/* Remove Confirmation Dialog */}
            <Dialog open={showRemoveDialog} onOpenChange={setShowRemoveDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove from Wishlist</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove "{product.name}" from your wishlist?
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex gap-3 mt-6">
                        <Button
                            variant="destructive"
                            onClick={handleRemove}
                            disabled={isRemoving}
                            className="flex-1"
                        >
                            {isRemoving ? 'Removing...' : 'Remove'}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setShowRemoveDialog(false)}
                            disabled={isRemoving}
                            className="flex-1"
                        >
                            Cancel
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
};

export default function WishlistPage() {
    const {
        items,
        isLoading,
        removeFromWishlist,
        fetchWishlist,
        clearWishlist
    } = useWishlistStore();

    const { addToCart } = useCartStore();
    const [showClearDialog, setShowClearDialog] = React.useState(false);
    const [isClearing, setIsClearing] = React.useState(false);

    // Fetch wishlist on mount
    React.useEffect(() => {
        fetchWishlist();
    }, [fetchWishlist]);

    const handleRemoveFromWishlist = async (productId: number) => {
        await removeFromWishlist(productId);
    };

    const handleAddToCart = async (product: any) => {
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

    const handleAddAllToCart = async () => {
        const availableItems = items.filter(item => item.is_in_stock);

        if (availableItems.length === 0) {
            toast.error('No available items to add to cart');
            return;
        }

        try {
            for (const item of availableItems) {
                await addToCart({
                    product_id: item.id,
                    quantity: 1,
                });
            }
            toast.success(`Added ${availableItems.length} items to cart!`);
        } catch (error) {
            toast.error('Failed to add some items to cart');
        }
    };

    const handleClearWishlist = async () => {
        setIsClearing(true);
        try {
            clearWishlist();
            setShowClearDialog(false);
            toast.success('Wishlist cleared successfully');
        } catch (error) {
            toast.error('Failed to clear wishlist');
        } finally {
            setIsClearing(false);
        }
    };

    const handleShareWishlist = async () => {
        try {
            await navigator.share({
                title: 'Check out my wishlist',
                text: 'Take a look at the products I\'m interested in',
                url: window.location.href,
            });
        } catch (error) {
            // Fallback to copying URL
            navigator.clipboard.writeText(window.location.href);
            toast.success('Wishlist URL copied to clipboard');
        }
    };

    const availableItems = items.filter(item => item.is_in_stock);
    const unavailableItems = items.filter(item => !item.is_in_stock);

    return (
        <RouteGuard requireAuth>
            <MainLayout>
                <div className="container mx-auto px-4 py-8">
                    {/* Header */}
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        className="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8"
                    >
                        <div>
                            <h1 className="text-3xl lg:text-4xl font-bold text-foreground mb-2">
                                My Wishlist
                            </h1>
                            <p className="text-muted-foreground">
                                {items.length === 0
                                    ? 'Start saving items you love'
                                    : `${items.length} ${items.length === 1 ? 'item' : 'items'} saved`
                                }
                            </p>
                        </div>

                        {items.length > 0 && (
                            <div className="flex gap-3 mt-4 lg:mt-0">
                                <Button
                                    variant="outline"
                                    onClick={handleShareWishlist}
                                    className="flex-1 sm:flex-none"
                                >
                                    <Share2 className="mr-2 h-4 w-4" />
                                    Share
                                </Button>
                                {availableItems.length > 0 && (
                                    <Button
                                        onClick={handleAddAllToCart}
                                        className="flex-1 sm:flex-none"
                                    >
                                        <ShoppingCart className="mr-2 h-4 w-4" />
                                        Add All to Cart ({availableItems.length})
                                    </Button>
                                )}
                                <Button
                                    variant="outline"
                                    onClick={() => setShowClearDialog(true)}
                                    className="flex-1 sm:flex-none"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Clear All
                                </Button>
                            </div>
                        )}
                    </motion.div>

                    {/* Loading State */}
                    {isLoading && items.length === 0 && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            {[...Array(8)].map((_, i) => (
                                <Card key={i} className="overflow-hidden">
                                    <div className="aspect-square bg-muted animate-pulse" />
                                    <CardContent className="p-4">
                                        <div className="space-y-3">
                                            <div className="h-4 bg-muted rounded animate-pulse" />
                                            <div className="h-3 bg-muted rounded w-2/3 animate-pulse" />
                                            <div className="h-6 bg-muted rounded w-1/3 animate-pulse" />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}

                    {/* Empty State */}
                    {!isLoading && items.length === 0 && <EmptyWishlist />}

                    {/* Wishlist Items */}
                    {items.length > 0 && (
                        <AnimatePresence mode="popLayout">
                            <motion.div
                                layout
                                className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6"
                            >
                                {items.map((product, index) => (
                                    <WishlistItem
                                        key={product.id}
                                        product={product}
                                        onRemove={handleRemoveFromWishlist}
                                        onAddToCart={handleAddToCart}
                                        index={index}
                                    />
                                ))}
                            </motion.div>
                        </AnimatePresence>
                    )}

                    {/* Summary Stats */}
                    {items.length > 0 && (
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            className="mt-12"
                        >
                            <Card>
                                <CardContent className="p-6">
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
                                        <div>
                                            <div className="text-2xl font-bold text-primary mb-1">
                                                {items.length}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                Total Items
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-2xl font-bold text-success mb-1">
                                                {availableItems.length}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                Available
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-2xl font-bold text-muted-foreground mb-1">
                                                {unavailableItems.length}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                Out of Stock
                                            </div>
                                        </div>
                                        <div>
                                            <div className="text-2xl font-bold text-primary mb-1">
                                                {new Intl.NumberFormat('en-GB', {
                                                    style: 'currency',
                                                    currency: 'GBP',
                                                }).format(
                                                    availableItems.reduce((total, item) => total + item.price, 0) / 100
                                                )}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                Total Value
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>
                    )}

                    {/* Clear Confirmation Dialog */}
                    <Dialog open={showClearDialog} onOpenChange={setShowClearDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Clear Wishlist</DialogTitle>
                                <DialogDescription>
                                    Are you sure you want to remove all items from your wishlist?
                                    This action cannot be undone.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex gap-3 mt-6">
                                <Button
                                    variant="destructive"
                                    onClick={handleClearWishlist}
                                    disabled={isClearing}
                                    className="flex-1"
                                >
                                    {isClearing ? 'Clearing...' : 'Clear All'}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowClearDialog(false)}
                                    disabled={isClearing}
                                    className="flex-1"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </MainLayout>
        </RouteGuard>
    );
}
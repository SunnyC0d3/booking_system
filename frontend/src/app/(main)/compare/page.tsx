'use client'

import * as React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    ArrowUpDown,
    X,
    Star,
    ShoppingCart,
    Heart,
    Eye,
    Package,
    Check,
    Share2,
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
    DialogDescription,
} from '@/components/ui';
import { MainLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { useCompareStore } from '@/stores/productStore';
import { useCartStore } from '@/stores/cartStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';
import type { Product as ApiProduct } from '@/types/api';
import type { WishlistItem } from '@/types/api';

// Empty state component
const EmptyCompare = () => (
    <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6 }}
        className="text-center py-16"
    >
        <div className="max-w-md mx-auto">
            <div className="w-24 h-24 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-6">
                <ArrowUpDown className="h-12 w-12 text-primary" />
            </div>
            <h2 className="text-2xl font-bold text-foreground mb-4">
                No Products to Compare
            </h2>
            <p className="text-muted-foreground mb-8 leading-relaxed">
                Start comparing products by clicking the compare icon on product cards.
                You can compare up to 4 products at once to find the perfect match.
            </p>
            <Button size="lg" href="/products">
                <Package className="mr-2 h-4 w-4" />
                Browse Products
            </Button>
        </div>
    </motion.div>
);

// Comparison attribute row
interface AttributeRowProps {
    label: string;
    values: (string | number | boolean | React.ReactNode)[];
    isHighlight?: boolean;
}

const AttributeRow: React.FC<AttributeRowProps> = ({ label, values, isHighlight = false }) => (
    <tr className={cn('border-b hover:bg-muted/50', isHighlight && 'bg-primary/5')}>
        <td className="p-4 font-medium text-muted-foreground bg-muted/30">
            {label}
        </td>
        {values.map((value, index) => (
            <td key={index} className="p-4 text-center">
                {typeof value === 'boolean' ? (
                    value ? (
                        <Check className="h-4 w-4 text-green-600 mx-auto" />
                    ) : (
                        <X className="h-4 w-4 text-red-600 mx-auto" />
                    )
                ) : typeof value === 'number' ? (
                    <span className="font-medium">{value}</span>
                ) : (
                    value
                )}
            </td>
        ))}
    </tr>
);

// Compare product card component
interface CompareProductCardProps {
    product: ApiProduct;
    onRemove: (productId: number) => void;
    onAddToCart: (product: ApiProduct) => void;
    onToggleWishlist: (product: ApiProduct) => void;
    isInWishlist: boolean;
}

const CompareProductCard: React.FC<CompareProductCardProps> = ({
                                                                   product,
                                                                   onRemove,
                                                                   onAddToCart,
                                                                   onToggleWishlist,
                                                                   isInWishlist,
                                                               }) => (
    <Card className="h-fit">
        <CardContent className="p-6">
            <div className="relative mb-4">
                <div className="aspect-square bg-muted rounded-lg mb-4 relative overflow-hidden">
                    {product.featured_image ? (
                        <img
                            src={product.featured_image}
                            alt={product.name}
                            className="w-full h-full object-cover"
                        />
                    ) : product.gallery?.[0] ? (
                        <img
                            src={product.gallery[0].url}
                            alt={product.name}
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full flex items-center justify-center">
                            <Package className="h-12 w-12 text-muted-foreground" />
                        </div>
                    )}
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => onRemove(product.id)}
                        className="absolute top-2 right-2 bg-background/80 hover:bg-background"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                </div>

                <div className="space-y-2">
                    <h3 className="font-semibold text-lg leading-tight">{product.name}</h3>
                    <div className="flex items-center gap-2">
                        <div className="flex items-center">
                            {[...Array(5)].map((_, i) => (
                                <Star
                                    key={i}
                                    className={cn(
                                        'h-4 w-4',
                                        i < Math.floor(0) // No reviews_average in API Product
                                            ? 'fill-yellow-400 text-yellow-400'
                                            : 'text-muted-foreground'
                                    )}
                                />
                            ))}
                        </div>
                        <span className="text-sm text-muted-foreground">
                            (0) {/* No reviews_count in API Product */}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-2xl font-bold text-primary">
                            {product.price_formatted}
                        </span>
                    </div>
                </div>
            </div>

            <div className="flex gap-2">
                <Button
                    onClick={() => onAddToCart(product)}
                    className="flex-1"
                    size="sm"
                >
                    <ShoppingCart className="mr-2 h-4 w-4" />
                    Add to Cart
                </Button>
                <Button
                    variant={isInWishlist ? "default" : "outline"}
                    size="icon"
                    onClick={() => onToggleWishlist(product)}
                    className={cn(isInWishlist && 'text-red-500')}
                >
                    <Heart className={cn('h-4 w-4', isInWishlist && 'fill-current')} />
                </Button>
                <Button variant="outline" size="icon" href={`/products/${product.id}`}>
                    <Eye className="h-4 w-4" />
                </Button>
            </div>
        </CardContent>
    </Card>
);

function ComparePage() {
    const compareStore = useCompareStore();
    const { addToCart } = useCartStore();
    const wishlistStore = useWishlistStore();
    const [showClearDialog, setShowClearDialog] = React.useState(false);

    // Get items from compare store (assuming it has compareItems property)
    const items = compareStore.compareItems || [];

    // Get wishlist items (handle different possible property names)
    const wishlistItems = (wishlistStore as any).items || (wishlistStore as any).wishlist?.items || [];

    const isInWishlist = (productId: number) => {
        return wishlistItems.some((item: WishlistItem) => item.product_id === productId);
    };

    const handleRemoveFromCompare = (productId: number) => {
        compareStore.removeFromCompare(productId);
        toast.success('Product removed from comparison');
    };

    const handleAddToCart = (product: ApiProduct) => {
        addToCart({
            product_id: product.id,
            quantity: 1,
            product_variant_id: product.variants?.[0]?.id || null,
        });
        toast.success(`${product.name} added to cart`);
    };

    const handleToggleWishlist = (product: ApiProduct) => {
        if (isInWishlist(product.id)) {
            wishlistStore.removeFromWishlist(product.id);
            toast.success(`${product.name} removed from wishlist`);
        } else {
            wishlistStore.addToWishlist({
                product_id: product.id,
                product_variant_id: product.variants?.[0]?.id || null,
            });
            toast.success(`${product.name} added to wishlist`);
        }
    };

    const handleShare = async () => {
        if (navigator.share) {
            try {
                await navigator.share({
                    title: 'Product Comparison',
                    text: `Compare ${items.length} products`,
                    url: window.location.href,
                });
            } catch (error) {
                // User cancelled sharing
            }
        } else {
            // Fallback: copy to clipboard
            navigator.clipboard.writeText(window.location.href);
            toast.success('Comparison link copied to clipboard');
        }
    };

    const handleClearAll = () => {
        compareStore.clearCompare();
        setShowClearDialog(false);
        toast.success('All products removed from comparison');
    };

    // Generate comparison attributes
    const getComparisonAttributes = (): AttributeRowProps[] => {
        if (items.length === 0) return [];

        const attributes: AttributeRowProps[] = [];

        // Basic attributes
        attributes.push({
            label: 'Price',
            values: items.map((item: ApiProduct) => item.price_formatted),
            isHighlight: true,
        });

        attributes.push({
            label: 'Rating',
            values: items.map((_: ApiProduct) => (
                <div className="flex items-center gap-1">
                    <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
                    <span>0.0</span> {/* No rating data in API Product */}
                </div>
            )),
        });

        attributes.push({
            label: 'Reviews',
            values: items.map((_: ApiProduct) => 0), // No review count in API Product
        });

        // Check if any items have colors - simplified since API structure is different
        const hasColors = items.some((item: ApiProduct) =>
            item.variants?.length && item.variants.length > 0
        );
        if (hasColors) {
            attributes.push({
                label: 'Available Colors',
                values: items.map((item: ApiProduct) => {
                    const variantCount = item.variants?.length || 0;
                    return variantCount > 0 ? `${variantCount} options` : 'N/A';
                }),
            });
        }

        const hasSizes = items.some((item: ApiProduct) =>
            item.variants?.length && item.variants.length > 0
        );
        if (hasSizes) {
            attributes.push({
                label: 'Available Variants',
                values: items.map((item: ApiProduct) => {
                    const variantCount = item.variants?.length || 0;
                    return variantCount > 0 ? `${variantCount} variants` : 'N/A';
                }),
            });
        }

        return attributes;
    };

    const attributes = getComparisonAttributes();

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
                                Compare Products
                            </h1>
                            <p className="text-muted-foreground">
                                {items.length === 0
                                    ? 'Add products to compare them side by side'
                                    : `Comparing ${items.length} ${items.length === 1 ? 'product' : 'products'} • ${4 - items.length} slots available`
                                }
                            </p>
                        </div>

                        {items.length > 0 && (
                            <div className="flex gap-3 mt-4 lg:mt-0">
                                <Button
                                    variant="outline"
                                    onClick={handleShare}
                                    className="flex-1 sm:flex-none"
                                >
                                    <Share2 className="mr-2 h-4 w-4" />
                                    Share
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setShowClearDialog(true)}
                                    className="flex-1 sm:flex-none"
                                >
                                    <X className="mr-2 h-4 w-4" />
                                    Clear All
                                </Button>
                            </div>
                        )}
                    </motion.div>

                    {/* Empty State */}
                    {items.length === 0 && <EmptyCompare />}

                    {/* Comparison Content */}
                    {items.length > 0 && (
                        <div className="space-y-8">
                            {/* Product Cards */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.2 }}
                                className={cn(
                                    'grid gap-6',
                                    items.length === 1 && 'grid-cols-1 max-w-md mx-auto',
                                    items.length === 2 && 'grid-cols-1 md:grid-cols-2',
                                    items.length === 3 && 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
                                    items.length === 4 && 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
                                )}
                            >
                                <AnimatePresence>
                                    {items.map((product: ApiProduct) => (
                                        <motion.div
                                            key={product.id}
                                            layout
                                            initial={{ opacity: 0, scale: 0.9 }}
                                            animate={{ opacity: 1, scale: 1 }}
                                            exit={{ opacity: 0, scale: 0.9 }}
                                            transition={{ duration: 0.3 }}
                                        >
                                            <CompareProductCard
                                                product={product}
                                                onRemove={handleRemoveFromCompare}
                                                onAddToCart={handleAddToCart}
                                                onToggleWishlist={handleToggleWishlist}
                                                isInWishlist={isInWishlist(product.id)}
                                            />
                                        </motion.div>
                                    ))}
                                </AnimatePresence>
                            </motion.div>

                            {/* Comparison Table */}
                            {items.length > 1 && attributes.length > 0 && (
                                <motion.div
                                    initial={{ opacity: 0, y: 20 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.6, delay: 0.4 }}
                                >
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="flex items-center gap-2">
                                                <ArrowUpDown className="h-5 w-5" />
                                                Detailed Comparison
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="overflow-x-auto">
                                                <table className="w-full">
                                                    <thead>
                                                    <tr className="border-b">
                                                        <th className="p-4 text-left font-medium text-muted-foreground bg-muted/30">
                                                            Feature
                                                        </th>
                                                        {items.map((product: ApiProduct) => (
                                                            <th key={product.id} className="p-4 text-center font-medium">
                                                                {product.name}
                                                            </th>
                                                        ))}
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    {attributes.map((attr, index) => (
                                                        <AttributeRow
                                                            key={index}
                                                            label={attr.label}
                                                            values={attr.values}
                                                            isHighlight={!!attr.isHighlight}
                                                        />
                                                    ))}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </motion.div>
                            )}

                            {/* Call to Action */}
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.6, delay: 0.6 }}
                                className="text-center"
                            >
                                <Card className="p-8">
                                    <h3 className="text-xl font-semibold mb-4">Ready to decide?</h3>
                                    <p className="text-muted-foreground mb-6">
                                        Add your favorite products to cart or continue browsing for more options.
                                    </p>
                                    <div className="flex gap-4 justify-center">
                                        <Button variant="outline" href="/products">
                                            <Package className="mr-2 h-4 w-4" />
                                            Browse More Products
                                        </Button>
                                        <Button href="/cart">
                                            <ShoppingCart className="mr-2 h-4 w-4" />
                                            View Cart
                                        </Button>
                                    </div>
                                </Card>
                            </motion.div>
                        </div>
                    )}

                    {/* Clear All Dialog */}
                    <Dialog open={showClearDialog} onOpenChange={setShowClearDialog}>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Clear All Products?</DialogTitle>
                                <DialogDescription>
                                    This will remove all products from your comparison. This action cannot be undone.
                                </DialogDescription>
                            </DialogHeader>
                            <div className="flex gap-3 justify-end">
                                <Button
                                    variant="outline"
                                    onClick={() => setShowClearDialog(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={handleClearAll}
                                >
                                    Clear All
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>
                </div>
            </MainLayout>
        </RouteGuard>
    );
}

export default ComparePage;
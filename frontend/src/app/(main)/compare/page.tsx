// frontend/src/app/(main)/compare/page.tsx
import * as React from 'react';
import { Metadata } from 'next';
import Link from 'next/link';
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
    Minus,
    Plus,
    Share2,
    ArrowRight,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Badge,
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

export const metadata: Metadata = {
    title: 'Compare Products | Creative Business',
    description: 'Compare products side by side to make the best choice for your needs.',
};

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
            <Button size="lg" asChild>
                <Link href="/products">
                    <Package className="mr-2 h-4 w-4" />
                    Browse Products
                </Link>
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
                        <Check className="h-5 w-5 text-success mx-auto" />
                    ) : (
                        <Minus className="h-5 w-5 text-muted-foreground mx-auto" />
                    )
                ) : (
                    <span className={cn(isHighlight && 'font-semibold')}>{value}</span>
                )}
            </td>
        ))}
    </tr>
);

// Product comparison card
interface CompareProductCardProps {
    product: any;
    onRemove: (productId: number) => void;
    onAddToCart: (product: any) => void;
    onToggleWishlist: (product: any) => void;
    isInWishlist: boolean;
}

const CompareProductCard: React.FC<CompareProductCardProps> = ({
                                                                   product,
                                                                   onRemove,
                                                                   onAddToCart,
                                                                   onToggleWishlist,
                                                                   isInWishlist,
                                                               }) => {
    const hasDiscount = product.compare_price && product.compare_price > product.price;
    const discountPercentage = hasDiscount
        ? Math.round(((product.compare_price - product.price) / product.compare_price) * 100)
        : 0;

    const isOutOfStock = !product.is_in_stock;

    return (
        <div className="relative">
            {/* Remove Button */}
            <Button
                variant="secondary"
                size="icon"
                onClick={() => onRemove(product.id)}
                className="absolute -top-2 -right-2 w-8 h-8 rounded-full z-10 bg-white shadow-md hover:bg-gray-50"
            >
                <X className="h-4 w-4" />
            </Button>

            <Card className="overflow-hidden">
                <div className="relative">
                    {/* Product Image */}
                    <div className="aspect-square bg-muted relative overflow-hidden">
                        {product.featured_image ? (
                            <img
                                src={product.featured_image}
                                alt={product.name}
                                className="w-full h-full object-cover"
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
                    </div>

                    {/* Product Info */}
                    <CardContent className="p-4">
                        <div className="space-y-4">
                            {/* Title and Category */}
                            <div className="text-center">
                                <h3 className="font-semibold text-foreground line-clamp-2 hover:text-primary transition-colors">
                                    <Link href={`/products/${product.slug}`}>
                                        {product.name}
                                    </Link>
                                </h3>
                                {product.category && (
                                    <p className="text-sm text-muted-foreground mt-1">
                                        {product.category.name}
                                    </p>
                                )}
                            </div>

                            {/* Rating */}
                            {product.reviews_count > 0 && (
                                <div className="flex items-center justify-center gap-2">
                                    <div className="flex items-center gap-1">
                                        <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
                                        <span className="text-sm font-medium">
                                            {product.reviews_average.toFixed(1)}
                                        </span>
                                    </div>
                                    <span className="text-sm text-muted-foreground">
                                        ({product.reviews_count})
                                    </span>
                                </div>
                            )}

                            {/* Price */}
                            <div className="text-center">
                                <div className="text-2xl font-bold text-primary">
                                    {product.price_formatted}
                                </div>
                                {hasDiscount && (
                                    <div className="text-sm text-muted-foreground line-through">
                                        {product.compare_price_formatted}
                                    </div>
                                )}
                            </div>

                            {/* Actions */}
                            <div className="space-y-2">
                                <Button
                                    onClick={() => onAddToCart(product)}
                                    disabled={isOutOfStock}
                                    className="w-full"
                                >
                                    <ShoppingCart className="mr-2 h-4 w-4" />
                                    {isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
                                </Button>

                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => onToggleWishlist(product)}
                                        className={cn(
                                            'flex-1',
                                            isInWishlist && 'text-red-500 border-red-500'
                                        )}
                                    >
                                        <Heart className={cn('mr-2 h-4 w-4', isInWishlist && 'fill-current')} />
                                        {isInWishlist ? 'In Wishlist' : 'Save'}
                                    </Button>
                                    <Button variant="outline" size="sm" asChild className="flex-1">
                                        <Link href={`/products/${product.slug}`}>
                                            <Eye className="mr-2 h-4 w-4" />
                                            View
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </div>
            </Card>
        </div>
    );
};

export default function ComparePage() {
    const { items, removeFromCompare, clearCompare, canAddToCompare } = useCompareStore();
    const { addToCart } = useCartStore();
    const { addToWishlist, removeFromWishlist, isInWishlist } = useWishlistStore();

    const [showClearDialog, setShowClearDialog] = React.useState(false);
    const [isClearing, setIsClearing] = React.useState(false);

    const handleRemoveFromCompare = async (productId: number) => {
        await removeFromCompare(productId);
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

    const handleToggleWishlist = async (product: any) => {
        try {
            if (isInWishlist(product.id)) {
                await removeFromWishlist(product.id);
            } else {
                await addToWishlist(product);
            }
        } catch (error) {
            toast.error('Failed to update wishlist');
        }
    };

    const handleClearAll = async () => {
        setIsClearing(true);
        try {
            await clearCompare();
            setShowClearDialog(false);
            toast.success('Comparison cleared');
        } catch (error) {
            toast.error('Failed to clear comparison');
        } finally {
            setIsClearing(false);
        }
    };

    const handleShare = async () => {
        try {
            await navigator.share({
                title: 'Product Comparison',
                text: 'Check out this product comparison',
                url: window.location.href,
            });
        } catch (error) {
            navigator.clipboard.writeText(window.location.href);
            toast.success('Comparison URL copied to clipboard');
        }
    };

    // Generate comparison attributes
    const getComparisonAttributes = () => {
        if (items.length === 0) return [];

        const attributes = [
            {
                label: 'Price',
                values: items.map(item => item.price_formatted),
                isHighlight: true,
            },
            {
                label: 'Rating',
                values: items.map(item =>
                    item.reviews_count > 0
                        ? `${item.reviews_average.toFixed(1)} (${item.reviews_count} reviews)`
                        : 'No reviews'
                ),
            },
            {
                label: 'Availability',
                values: items.map(item => item.is_in_stock),
            },
            {
                label: 'Category',
                values: items.map(item => item.category?.name || 'Uncategorized'),
            },
        ];

        // Add variant attributes if available
        const hasColors = items.some(item => item.variants?.some((v: any) => v.attribute_name === 'Color'));
        if (hasColors) {
            attributes.push({
                label: 'Available Colors',
                values: items.map(item => {
                    const colors = item.variants?.filter((v: any) => v.attribute_name === 'Color') || [];
                    return colors.length > 0 ? `${colors.length} colors` : 'N/A';
                }),
            });
        }

        const hasSizes = items.some(item => item.variants?.some((v: any) => v.attribute_name === 'Size'));
        if (hasSizes) {
            attributes.push({
                label: 'Available Sizes',
                values: items.map(item => {
                    const sizes = item.variants?.filter((v: any) => v.attribute_name === 'Size') || [];
                    return sizes.length > 0 ? `${sizes.length} sizes` : 'N/A';
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
                                    {items.map((product) => (
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
                        </div>

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
                                        <ArrowUpDown className="h-5 w-5 text-primary" />
                                        Detailed Comparison
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                            <tr className="border-b bg-muted/50">
                                                <th className="p-4 text-left font-medium text-muted-foreground">
                                                    Specification
                                                </th>
                                                {items.map((product) => (
                                                    <th key={product.id} className="p-4 text-center min-w-[200px]">
                                                        <div className="text-sm font-medium text-foreground line-clamp-2">
                                                            {product.name}
                                                        </div>
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
                                                    isHighlight={attr.isHighlight}
                                                />
                                            ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>
                    )}

                    {/* Add More Products CTA */}
                    {canAddToCompare() && (
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6, delay: 0.6 }}
                            className="text-center"
                        >
                            <Card className="bg-muted/20 border-dashed">
                                <CardContent className="p-8">
                                    <div className="max-w-md mx-auto">
                                        <Plus className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                        <h3 className="text-lg font-semibold text-foreground mb-2">
                                            Add More Products
                                        </h3>
                                        <p className="text-muted-foreground mb-6">
                                            You can compare up to 4 products at once. Add more items to get
                                            a comprehensive comparison.
                                        </p>
                                        <Button asChild>
                                            <Link href="/products">
                                                Browse Products
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>
                    )}
                </div>
                )}

                {/* Clear Confirmation Dialog */}
                <Dialog open={showClearDialog} onOpenChange={setShowClearDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Clear Comparison</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to remove all products from comparison?
                                This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex gap-3 mt-6">
                            <Button
                                variant="destructive"
                                onClick={handleClearAll}
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
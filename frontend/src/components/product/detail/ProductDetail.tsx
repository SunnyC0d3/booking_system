import * as React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Heart,
    ShoppingCart,
    Share2,
    Plus,
    Minus,
    Truck,
    Shield,
    RotateCcw,
    Zap,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Badge,
} from '@/components/ui';
import { useCartStore } from '@/stores/cartStore';
import { useWishlistStore } from '@/stores/wishlistStore';
import { Product, ProductVariant } from '@/types/api'; // Changed import to use api types
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

interface ProductDetailProps {
    product: Product;
    className?: string;
}

export const ProductDetail: React.FC<ProductDetailProps> = ({
                                                                product,
                                                                className,
                                                            }) => {
    const [selectedImageIndex, setSelectedImageIndex] = React.useState(0);
    const [selectedVariant, setSelectedVariant] = React.useState<ProductVariant | null>(
        product.variants?.[0] || null
    );
    const [quantity, setQuantity] = React.useState(1);
    const [isZoomed, setIsZoomed] = React.useState(false);

    const { addToCart, isLoading: cartLoading } = useCartStore();
    const { addToWishlist, removeFromWishlist } = useWishlistStore(); // Removed isInWishlist since it doesn't exist

    // Mock wishlist check since isInWishlist doesn't exist in the store
    const inWishlist = false; // You'll need to implement this logic based on your store structure

    // Use API price structure - no compare_price in API types, so we'll mock discount logic
    const hasDiscount = false; // Set to false since compare_price doesn't exist in API
    const discountPercentage = 0;

    // Get current price based on selected variant - use price_formatted from API
    const currentPriceFormatted = selectedVariant && selectedVariant.additional_price_formatted
        ? selectedVariant.total_price_formatted
        : product.price_formatted;

    // Gallery images - use API structure
    const galleryImages = [
        product.featured_image,
        ...(product.gallery?.map(img => img.url) || [])
    ].filter((img): img is string => Boolean(img));

    const handleAddToCart = async () => {
        try {
            await addToCart({
                product_id: product.id,
                product_variant_id: selectedVariant?.id || null, // Changed undefined to null
                quantity,
            });
            toast.success('Added to cart successfully!');
        } catch (error) {
            toast.error('Failed to add to cart');
        }
    };

    const handleWishlistToggle = () => {
        if (inWishlist) {
            removeFromWishlist(product.id);
            toast.success('Removed from wishlist');
        } else {
            // Fix: Use correct AddToWishlistRequest structure
            addToWishlist({
                product_id: product.id,
                product_variant_id: selectedVariant?.id || null,
            });
            toast.success('Added to wishlist');
        }
    };

    const handleShare = async () => {
        if (navigator.share) {
            try {
                await navigator.share({
                    title: product.name,
                    text: product.description || '',
                    url: window.location.href,
                });
            } catch (error) {
                console.log('Error sharing:', error);
            }
        } else {
            // Fallback to copying URL
            navigator.clipboard.writeText(window.location.href);
            toast.success('Link copied to clipboard!');
        }
    };

    return (
        <div className={cn('max-w-7xl mx-auto', className)}>
            <div className="grid lg:grid-cols-2 gap-8 lg:gap-12">
                {/* Product Images */}
                <div className="space-y-4">
                    {/* Main Image */}
                    <div className="relative aspect-square overflow-hidden rounded-xl bg-muted">
                        <AnimatePresence mode="wait">
                            <motion.div
                                key={selectedImageIndex}
                                initial={{ opacity: 0 }}
                                animate={{ opacity: 1 }}
                                exit={{ opacity: 0 }}
                                transition={{ duration: 0.3 }}
                                className="relative w-full h-full cursor-zoom-in"
                                onClick={() => setIsZoomed(!isZoomed)}
                            >
                                {galleryImages[selectedImageIndex] ? (
                                    <Image
                                        src={galleryImages[selectedImageIndex]} // Now properly typed as string
                                        alt={product.name}
                                        fill
                                        className="object-cover"
                                        priority
                                    />
                                ) : (
                                    <div className="flex items-center justify-center w-full h-full bg-muted">
                                        <span className="text-muted-foreground">No image available</span>
                                    </div>
                                )}
                            </motion.div>
                        </AnimatePresence>

                        {/* Image Navigation */}
                        {galleryImages.length > 1 && (
                            <>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="absolute left-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white"
                                    onClick={() => setSelectedImageIndex(prev =>
                                        prev === 0 ? galleryImages.length - 1 : prev - 1
                                    )}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="absolute right-2 top-1/2 -translate-y-1/2 bg-white/80 hover:bg-white"
                                    onClick={() => setSelectedImageIndex(prev =>
                                        prev === galleryImages.length - 1 ? 0 : prev + 1
                                    )}
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </>
                        )}

                        {/* Badges */}
                        <div className="absolute top-3 left-3 flex flex-col gap-2">
                            {product.is_low_stock && (
                                <Badge variant="default" className="bg-orange-500">
                                    <Zap className="h-3 w-3 mr-1" />
                                    Low Stock
                                </Badge>
                            )}
                            {hasDiscount && (
                                <Badge variant="destructive">
                                    -{discountPercentage}%
                                </Badge>
                            )}
                        </div>
                    </div>

                    {/* Thumbnail Gallery */}
                    {galleryImages.length > 1 && (
                        <div className="flex gap-2 overflow-x-auto pb-2">
                            {galleryImages.map((image, index) => (
                                <button
                                    key={index}
                                    onClick={() => setSelectedImageIndex(index)}
                                    className={cn(
                                        "relative flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 transition-colors",
                                        selectedImageIndex === index
                                            ? "border-primary"
                                            : "border-transparent hover:border-muted-foreground"
                                    )}
                                >
                                    <Image
                                        src={image}
                                        alt={`${product.name} ${index + 1}`}
                                        fill
                                        className="object-cover"
                                    />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Product Info */}
                <div className="space-y-6">
                    {/* Header */}
                    <div>
                        {/* Category Breadcrumb */}
                        {product.category && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-2">
                                <Link
                                    href={`/categories/${product.category.id}`} // Using id since no slug in API
                                    className="hover:text-primary transition-colors"
                                >
                                    {product.category.name}
                                </Link>
                            </div>
                        )}

                        <h1 className="text-3xl font-bold text-foreground mb-2">
                            {product.name}
                        </h1>

                        {/* Stock Status */}
                        <div className="flex items-center gap-2 mb-4">
                            <Badge
                                variant={product.is_in_stock ? "default" : "secondary"}
                                className={cn(
                                    product.is_in_stock ? "bg-green-500" : "bg-red-500"
                                )}
                            >
                                {product.stock_status.replace('_', ' ').toUpperCase()}
                            </Badge>
                            {product.quantity > 0 && (
                                <span className="text-sm text-muted-foreground">
                                    {product.quantity} available
                                </span>
                            )}
                        </div>

                        {/* Price */}
                        <div className="flex items-center gap-3 mb-6">
                            <span className="text-3xl font-bold text-primary">
                                {currentPriceFormatted}
                            </span>
                        </div>
                    </div>

                    {/* Description */}
                    {product.description && (
                        <p className="text-muted-foreground leading-relaxed">
                            {product.description}
                        </p>
                    )}

                    {/* Variant Selection */}
                    {product.variants && product.variants.length > 0 && (
                        <div className="space-y-4">
                            <h3 className="font-semibold">Options:</h3>
                            <div className="grid gap-3">
                                {product.variants.map((variant) => (
                                    <label
                                        key={variant.id}
                                        className={cn(
                                            "flex items-center justify-between p-3 border rounded-lg cursor-pointer transition-colors",
                                            selectedVariant?.id === variant.id
                                                ? "border-primary bg-primary/5"
                                                : "border-muted hover:border-muted-foreground"
                                        )}
                                    >
                                        <div className="flex items-center gap-3">
                                            <input
                                                type="radio"
                                                name="variant"
                                                checked={selectedVariant?.id === variant.id}
                                                onChange={() => setSelectedVariant(variant)}
                                                className="text-primary"
                                            />
                                            <div>
                                                <div className="font-medium">
                                                    {variant.product_attribute?.name}: {variant.value}
                                                </div>
                                                {variant.additional_price && variant.additional_price !== 0 && (
                                                    <div className="text-sm text-muted-foreground">
                                                        {variant.additional_price_formatted}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {variant.quantity > 0
                                                ? `${variant.quantity} in stock`
                                                : 'Out of stock'
                                            }
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Quantity & Add to Cart */}
                    <div className="space-y-4">
                        <div className="flex items-center gap-4">
                            <div className="flex items-center">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setQuantity(Math.max(1, quantity - 1))}
                                    disabled={quantity <= 1}
                                >
                                    <Minus className="h-4 w-4" />
                                </Button>
                                <Input
                                    type="number"
                                    value={quantity}
                                    onChange={(e) => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
                                    className="w-20 text-center mx-2"
                                    min="1"
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setQuantity(quantity + 1)}
                                >
                                    <Plus className="h-4 w-4" />
                                </Button>
                            </div>

                            <span className="text-sm text-muted-foreground">
                                {product.quantity > 0
                                    ? `${product.quantity} available`
                                    : 'Out of stock'
                                }
                            </span>
                        </div>

                        <div className="flex gap-3">
                            <Button
                                onClick={handleAddToCart}
                                disabled={cartLoading || !product.is_in_stock}
                                className="flex-1"
                                size="lg"
                            >
                                <ShoppingCart className="h-4 w-4 mr-2" />
                                {cartLoading ? 'Adding...' : 'Add to Cart'}
                            </Button>

                            <Button
                                variant="outline"
                                size="lg"
                                onClick={handleWishlistToggle}
                            >
                                <Heart
                                    className={cn(
                                        "h-4 w-4",
                                        inWishlist && "fill-primary text-primary"
                                    )}
                                />
                            </Button>

                            <Button
                                variant="outline"
                                size="lg"
                                onClick={handleShare}
                            >
                                <Share2 className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    {/* Features */}
                    <div className="grid gap-3 text-sm">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Truck className="h-4 w-4" />
                            Free shipping on orders over £50
                        </div>
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Shield className="h-4 w-4" />
                            Secure payment & data protection
                        </div>
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <RotateCcw className="h-4 w-4" />
                            30-day return guarantee
                        </div>
                    </div>

                    {/* Tags */}
                    {product.tags && product.tags.length > 0 && (
                        <div className="space-y-2">
                            <h3 className="font-semibold text-sm">Tags:</h3>
                            <div className="flex flex-wrap gap-2">
                                {product.tags.map((tag) => (
                                    <Link
                                        key={tag.id}
                                        href={`/products?tags=${tag.name}`} // Using name since no slug in API
                                        className="text-xs px-2 py-1 bg-muted hover:bg-muted/80 rounded-full transition-colors"
                                    >
                                        {tag.name}
                                    </Link>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Product Description */}
            {product.description && (
                <Card className="mt-12">
                    <CardHeader>
                        <CardTitle>Product Description</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            className="prose prose-sm max-w-none"
                            dangerouslySetInnerHTML={{ __html: product.description }}
                        />
                    </CardContent>
                </Card>
            )}
        </div>
    );
};

export default ProductDetail;
'use client'

import * as React from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    Star,
    ThumbsUp,
    ThumbsDown,
    Flag,
    User,
    Calendar,
    CheckCircle,
    Camera,
    X,
    Send,
    Filter,
    SortDesc,
    MessageSquare,
    BarChart3,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Badge,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    Progress,
    Avatar,
    AvatarFallback,
    AvatarImage,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui';
import { productApi } from '@/api/products';
import { ProductReview, ReviewStats } from '@/types/product';
import { useAuth } from '@/stores/authStore';
import { useNotifications } from '@/stores/notificationStore';
import { cn } from '@/lib/cn';

// Review form schema
const reviewSchema = z.object({
    rating: z.number().min(1, 'Please select a rating').max(5),
    title: z.string().min(1, 'Review title is required').max(100, 'Title must be less than 100 characters'),
    content: z.string().min(10, 'Review must be at least 10 characters').max(1000, 'Review must be less than 1000 characters'),
    images: z.array(z.instanceof(File)).max(5, 'Maximum 5 images allowed').optional(),
});

type ReviewFormData = z.infer<typeof reviewSchema>;

// Sort options
const sortOptions = [
    { value: 'newest', label: 'Newest First' },
    { value: 'oldest', label: 'Oldest First' },
    { value: 'highest', label: 'Highest Rating' },
    { value: 'lowest', label: 'Lowest Rating' },
    { value: 'helpful', label: 'Most Helpful' },
];

// Filter options
const filterOptions = [
    { value: 'all', label: 'All Reviews' },
    { value: '5', label: '5 Stars' },
    { value: '4', label: '4 Stars' },
    { value: '3', label: '3 Stars' },
    { value: '2', label: '2 Stars' },
    { value: '1', label: '1 Star' },
    { value: 'verified', label: 'Verified Purchases' },
    { value: 'with_images', label: 'With Images' },
];

interface ProductReviewsProps {
    productId: number;
    className?: string;
}

export const ProductReviews: React.FC<ProductReviewsProps> = ({
                                                                  productId,
                                                                  className,
                                                              }) => {
    const { user, isAuthenticated } = useAuth();
    const { success, error } = useNotifications();

    // State
    const [reviews, setReviews] = React.useState<ProductReview[]>([]);
    const [reviewStats, setReviewStats] = React.useState<ReviewStats | null>(null);
    const [isLoading, setIsLoading] = React.useState(true);
    const [sortBy, setSortBy] = React.useState('newest');
    const [filterBy, setFilterBy] = React.useState('all');
    const [isSubmittingReview, setIsSubmittingReview] = React.useState(false);
    const [showReviewForm, setShowReviewForm] = React.useState(false);

    // Fetch reviews and stats
    const fetchReviews = async () => {
        try {
            setIsLoading(true);
            const [reviewsResponse, statsResponse] = await Promise.all([
                productApi.getProductReviews(productId, {
                    sort: sortBy,
                    filter: filterBy !== 'all' ? filterBy : undefined,
                }),
                productApi.getReviewStats(productId),
            ]);

            setReviews(reviewsResponse.data);
            setReviewStats(statsResponse);
        } catch (err) {
            error('Failed to load reviews', 'Please try again later');
        } finally {
            setIsLoading(false);
        }
    };

    React.useEffect(() => {
        fetchReviews();
    }, [productId, sortBy, filterBy]);

    const handleHelpfulVote = async (reviewId: number) => {
        if (!isAuthenticated) {
            error('Please log in', 'You need to be logged in to vote on reviews');
            return;
        }

        try {
            await productApi.markReviewHelpful(reviewId);
            success('Vote recorded', 'Thank you for your feedback');
            fetchReviews(); // Refresh to show updated count
        } catch (err) {
            error('Failed to record vote', 'Please try again');
        }
    };

    return (
        <div className={cn('space-y-6', className)}>
            {/* Review Stats Overview */}
            {reviewStats && (
                <ReviewStatsOverview
                    stats={reviewStats}
                    onFilterChange={setFilterBy}
                    currentFilter={filterBy}
                />
            )}

            {/* Review Controls */}
            <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                <div className="flex gap-4">
                    {/* Sort */}
                    <Select value={sortBy} onValueChange={setSortBy}>
                        <SelectTrigger className="w-40">
                            <SortDesc className="mr-2 h-4 w-4" />
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {sortOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Filter */}
                    <Select value={filterBy} onValueChange={setFilterBy}>
                        <SelectTrigger className="w-40">
                            <Filter className="mr-2 h-4 w-4" />
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {filterOptions.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Write Review Button */}
                {isAuthenticated && (
                    <Dialog open={showReviewForm} onOpenChange={setShowReviewForm}>
                        <DialogTrigger>
                            <Button>
                                <MessageSquare className="mr-2 h-4 w-4" />
                                Write Review
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>Write a Review</DialogTitle>
                            </DialogHeader>
                            <ReviewForm
                                productId={productId}
                                onSubmit={async (data) => {
                                    setIsSubmittingReview(true);
                                    try {
                                        await productApi.submitReview(productId, data);
                                        success('Review submitted!', 'Thank you for your feedback');
                                        setShowReviewForm(false);
                                        fetchReviews();
                                    } catch (err) {
                                        error('Failed to submit review', 'Please try again');
                                    } finally {
                                        setIsSubmittingReview(false);
                                    }
                                }}
                                isSubmitting={isSubmittingReview}
                            />
                        </DialogContent>
                    </Dialog>
                )}
            </div>

            {/* Reviews List */}
            <div className="space-y-6">
                {isLoading ? (
                    <ReviewsSkeleton />
                ) : reviews.length > 0 ? (
                    <motion.div layout className="space-y-4">
                        <AnimatePresence>
                            {reviews.map((review, index) => (
                                <ReviewItem
                                    key={review.id}
                                    review={review}
                                    index={index}
                                    onHelpfulVote={handleHelpfulVote}
                                    isAuthenticated={isAuthenticated}
                                />
                            ))}
                        </AnimatePresence>
                    </motion.div>
                ) : (
                    <EmptyReviews
                        onWriteReview={() => setShowReviewForm(true)}
                        isAuthenticated={isAuthenticated}
                    />
                )}
            </div>
        </div>
    );
};

// Review Stats Overview Component
interface ReviewStatsOverviewProps {
    stats: ReviewStats;
    onFilterChange: (filter: string) => void;
    currentFilter: string;
}

const ReviewStatsOverview: React.FC<ReviewStatsOverviewProps> = ({
                                                                     stats,
                                                                     onFilterChange,
                                                                     currentFilter,
                                                                 }) => {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <BarChart3 className="h-5 w-5 text-primary" />
                    Customer Reviews
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid md:grid-cols-2 gap-6">
                    {/* Overall Rating */}
                    <div className="text-center">
                        <div className="text-4xl font-bold text-primary mb-2">
                            {stats.average_rating.toFixed(1)}
                        </div>
                        <div className="flex items-center justify-center gap-1 mb-2">
                            {[...Array(5)].map((_, i) => (
                                <Star
                                    key={i}
                                    className={cn(
                                        'h-5 w-5',
                                        i < Math.floor(stats.average_rating)
                                            ? 'fill-yellow-400 text-yellow-400'
                                            : 'text-muted-foreground'
                                    )}
                                />
                            ))}
                        </div>
                        <p className="text-muted-foreground">
                            Based on {stats.total_reviews} {stats.total_reviews === 1 ? 'review' : 'reviews'}
                        </p>
                    </div>

                    {/* Rating Distribution */}
                    <div className="space-y-2">
                        {[5, 4, 3, 2, 1].map((rating) => {
                            const count = stats.rating_distribution[rating] || 0;
                            const percentage = stats.total_reviews > 0
                                ? (count / stats.total_reviews) * 100
                                : 0;

                            return (
                                <div key={rating} className="flex items-center gap-3">
                                    <button
                                        onClick={() => onFilterChange(rating.toString())}
                                        className={cn(
                                            'flex items-center gap-1 text-sm hover:text-primary transition-colors',
                                            currentFilter === rating.toString() && 'text-primary font-medium'
                                        )}
                                    >
                                        <span>{rating}</span>
                                        <Star className="h-3 w-3 fill-yellow-400 text-yellow-400" />
                                    </button>
                                    <div className="flex-1">
                                        <Progress
                                            value={percentage}
                                            className="h-2"
                                        />
                                    </div>
                                    <span className="text-sm text-muted-foreground min-w-[3ch]">
                                        {count}
                                    </span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

// Individual Review Item Component
interface ReviewItemProps {
    review: ProductReview;
    index: number;
    onHelpfulVote: (reviewId: number) => void;
    isAuthenticated: boolean;
}

const ReviewItem: React.FC<ReviewItemProps> = ({
                                                   review,
                                                   index,
                                                   onHelpfulVote,
                                                   isAuthenticated,
                                               }) => {
    const [showFullContent, setShowFullContent] = React.useState(false);
    const [selectedImage, setSelectedImage] = React.useState<string | null>(null);

    const isLongContent = review.content.length > 300;
    const displayContent = showFullContent || !isLongContent
        ? review.content
        : `${review.content.substring(0, 300)}...`;

    return (
        <motion.div
            layout
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.3, delay: index * 0.05 }}
        >
            <Card>
                <CardContent className="p-6">
                    <div className="space-y-4">
                        {/* Header */}
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-3">
                                <Avatar className="w-10 h-10">
                                    <AvatarImage src={review.user.avatar} />
                                    <AvatarFallback>
                                        <User className="h-5 w-5" />
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <h4 className="font-medium text-foreground">
                                        {review.user.name}
                                    </h4>
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Calendar className="h-3 w-3" />
                                        <span>{new Date(review.created_at).toLocaleDateString()}</span>
                                        {review.verified_purchase && (
                                            <Badge variant="secondary" className="text-xs">
                                                <CheckCircle className="mr-1 h-3 w-3" />
                                                Verified Purchase
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Rating */}
                            <div className="flex items-center gap-1">
                                {[...Array(5)].map((_, i) => (
                                    <Star
                                        key={i}
                                        className={cn(
                                            'h-4 w-4',
                                            i < review.rating
                                                ? 'fill-yellow-400 text-yellow-400'
                                                : 'text-muted-foreground'
                                        )}
                                    />
                                ))}
                            </div>
                        </div>

                        {/* Title */}
                        {review.title && (
                            <h5 className="font-medium text-foreground">
                                {review.title}
                            </h5>
                        )}

                        {/* Content */}
                        <div className="space-y-2">
                            <p className="text-muted-foreground leading-relaxed">
                                {displayContent}
                            </p>
                            {isLongContent && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setShowFullContent(!showFullContent)}
                                    className="text-primary hover:text-primary/80 p-0 h-auto"
                                >
                                    {showFullContent ? 'Show Less' : 'Read More'}
                                </Button>
                            )}
                        </div>

                        {/* Images */}
                        {review.images.length > 0 && (
                            <div className="flex gap-2 flex-wrap">
                                {review.images.map((image, imgIndex) => (
                                    <button
                                        key={imgIndex}
                                        onClick={() => setSelectedImage(image.url)}
                                        className="relative w-16 h-16 rounded-lg overflow-hidden border hover:opacity-80 transition-opacity"
                                    >
                                        <img
                                            src={image.url}
                                            alt={`Review image ${imgIndex + 1}`}
                                            className="w-full h-full object-cover"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex items-center justify-between pt-4 border-t">
                            <div className="flex items-center gap-4">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => onHelpfulVote(review.id)}
                                    disabled={!isAuthenticated}
                                    className="flex items-center gap-2"
                                >
                                    <ThumbsUp className="h-4 w-4" />
                                    <span>Helpful ({review.helpful_count})</span>
                                </Button>
                            </div>

                            <Button variant="ghost" size="sm" className="text-muted-foreground">
                                <Flag className="h-4 w-4 mr-1" />
                                Report
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Image Modal */}
            {selectedImage && (
                <Dialog open={!!selectedImage} onOpenChange={() => setSelectedImage(null)}>
                    <DialogContent className="max-w-3xl">
                        <DialogHeader>
                            <DialogTitle className="sr-only">Review Image</DialogTitle>
                        </DialogHeader>
                        <div className="relative">
                            <img
                                src={selectedImage}
                                alt="Review image"
                                className="w-full h-auto max-h-[70vh] object-contain"
                            />
                            <Button
                                variant="secondary"
                                size="icon"
                                onClick={() => setSelectedImage(null)}
                                className="absolute top-2 right-2"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </motion.div>
    );
};

// Review Form Component
interface ReviewFormProps {
    productId: number;
    onSubmit: (data: ReviewFormData) => Promise<void>;
    isSubmitting: boolean;
}

const ReviewForm: React.FC<ReviewFormProps> = ({
                                                   productId,
                                                   onSubmit,
                                                   isSubmitting,
                                               }) => {
    const [selectedImages, setSelectedImages] = React.useState<File[]>([]);
    const [rating, setRating] = React.useState(0);
    const [hoveredRating, setHoveredRating] = React.useState(0);

    const {
        register,
        handleSubmit,
        formState: { errors },
        setValue,
        watch,
    } = useForm<ReviewFormData>({
        resolver: zodResolver(reviewSchema),
        defaultValues: {
            rating: 0,
            title: '',
            content: '',
            images: [],
        },
    });

    const handleImageUpload = (event: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files || []);
        if (selectedImages.length + files.length > 5) {
            alert('Maximum 5 images allowed');
            return;
        }
        setSelectedImages([...selectedImages, ...files]);
        setValue('images', [...selectedImages, ...files]);
    };

    const removeImage = (index: number) => {
        const newImages = selectedImages.filter((_, i) => i !== index);
        setSelectedImages(newImages);
        setValue('images', newImages);
    };

    const handleRatingClick = (value: number) => {
        setRating(value);
        setValue('rating', value);
    };

    const onFormSubmit = async (data: ReviewFormData) => {
        await onSubmit({
            ...data,
            rating,
            images: selectedImages,
        });
    };

    return (
        <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-6">
            {/* Rating */}
            <div className="space-y-2">
                <label className="text-sm font-medium">Rating *</label>
                <div className="flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map((value) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => handleRatingClick(value)}
                            onMouseEnter={() => setHoveredRating(value)}
                            onMouseLeave={() => setHoveredRating(0)}
                            className="transition-colors"
                        >
                            <Star
                                className={cn(
                                    'h-6 w-6',
                                    (hoveredRating || rating) >= value
                                        ? 'fill-yellow-400 text-yellow-400'
                                        : 'text-muted-foreground'
                                )}
                            />
                        </button>
                    ))}
                </div>
                {errors.rating && (
                    <p className="text-sm text-destructive">{errors.rating.message}</p>
                )}
            </div>

            {/* Title */}
            <div className="space-y-2">
                <Input
                    {...register('title')}
                    placeholder="Give your review a title"
                    label="Review Title *"
                    error={errors.title?.message}
                />
            </div>

            {/* Content */}
            <div className="space-y-2">
                <label className="text-sm font-medium">Review *</label>
                <textarea
                    {...register('content')}
                    rows={5}
                    placeholder="Share your experience with this product..."
                    className="w-full px-3 py-2 border border-input rounded-lg focus:outline-none focus:ring-2 focus:ring-primary resize-none"
                />
                {errors.content && (
                    <p className="text-sm text-destructive">{errors.content.message}</p>
                )}
                <p className="text-xs text-muted-foreground">
                    {watch('content')?.length || 0}/1000 characters
                </p>
            </div>

            {/* Image Upload */}
            <div className="space-y-2">
                <label className="text-sm font-medium">Add Photos (Optional)</label>
                <div className="space-y-3">
                    {selectedImages.length > 0 && (
                        <div className="flex gap-2 flex-wrap">
                            {selectedImages.map((file, index) => (
                                <div key={index} className="relative">
                                    <img
                                        src={URL.createObjectURL(file)}
                                        alt={`Preview ${index + 1}`}
                                        className="w-16 h-16 object-cover rounded-lg border"
                                    />
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="icon"
                                        onClick={() => removeImage(index)}
                                        className="absolute -top-2 -right-2 w-6 h-6"
                                    >
                                        <X className="h-3 w-3" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    )}
                    {selectedImages.length < 5 && (
                        <div className="border-2 border-dashed border-muted-foreground/25 rounded-lg p-6 text-center">
                            <Camera className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                            <p className="text-sm text-muted-foreground mb-2">
                                Add up to 5 photos to help others
                            </p>
                            <input
                                type="file"
                                multiple
                                accept="image/*"
                                onChange={handleImageUpload}
                                className="hidden"
                                id="image-upload"
                            />
                            <label htmlFor="image-upload">
                                <Button type="button" variant="outline" size="sm">
                                    <span>Choose Files</span>
                                </Button>
                            </label>
                        </div>
                    )}
                </div>
            </div>

            {/* Submit */}
            <div className="flex gap-3 pt-4">
                <Button
                    type="submit"
                    disabled={isSubmitting}
                    className="flex-1"
                >
                    {isSubmitting ? (
                        'Submitting...'
                    ) : (
                        <>
                            <Send className="mr-2 h-4 w-4" />
                            Submit Review
                        </>
                    )}
                </Button>
            </div>
        </form>
    );
};

// Empty Reviews Component
const EmptyReviews: React.FC<{
    onWriteReview: () => void;
    isAuthenticated: boolean;
}> = ({ onWriteReview, isAuthenticated }) => (
    <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="text-center py-12"
    >
        <MessageSquare className="h-16 w-16 text-muted-foreground mx-auto mb-6" />
        <h3 className="text-xl font-semibold text-foreground mb-4">
            No Reviews Yet
        </h3>
        <p className="text-muted-foreground mb-8 max-w-md mx-auto">
            Be the first to share your experience with this product and help other customers make informed decisions.
        </p>
        {isAuthenticated && (
            <Button onClick={onWriteReview}>
                <MessageSquare className="mr-2 h-4 w-4" />
                Write First Review
            </Button>
        )}
    </motion.div>
);

// Reviews Skeleton
const ReviewsSkeleton: React.FC = () => (
    <div className="space-y-4">
        {[...Array(3)].map((_, i) => (
            <Card key={i}>
                <CardContent className="p-6">
                    <div className="space-y-4">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-muted rounded-full animate-pulse" />
                            <div className="space-y-2">
                                <div className="h-4 bg-muted rounded w-32 animate-pulse" />
                                <div className="h-3 bg-muted rounded w-24 animate-pulse" />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <div className="h-4 bg-muted rounded w-3/4 animate-pulse" />
                            <div className="h-4 bg-muted rounded w-1/2 animate-pulse" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        ))}
    </div>
);

export default ProductReviews;
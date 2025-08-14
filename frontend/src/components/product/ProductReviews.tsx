'use client';

import {useState, useEffect} from 'react';
import {useAuthUtils} from '@/hooks/useAuthUtils';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
    Badge,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Label,
    Textarea
} from '@/components/ui';

import {toast} from 'sonner';
import {
    Star,
    ThumbsUp,
    ThumbsDown,
    MessageSquare,
    Edit,
    Trash2,
    Flag,
    MoreHorizontal
} from 'lucide-react';

interface Review {
    id: number;
    rating: number;
    title: string;
    comment: string;
    user: {
        id: number;
        name: string;
        avatar_url?: string;
    };
    created_at: string;
    updated_at: string;
    helpful_count: number;
    user_found_helpful: boolean;
    verified_purchase: boolean;
    is_own_review: boolean;
}

interface ProductReviewsProps {
    productId: number;
    productName: string;
    userCanReview?: boolean;
}

export function ProductReviews({
                                   productId,
                                   productName,
                                   userCanReview = false
                               }: ProductReviewsProps) {
    const {
        isAuthenticated,
        requireAuth,
        getAuthHeaders
    } = useAuthUtils();

    const [reviews, setReviews] = useState<Review[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showReviewForm, setShowReviewForm] = useState(false);
    const [editingReview, setEditingReview] = useState<Review | null>(null);

    const [reviewForm, setReviewForm] = useState({
        rating: 5,
        title: '',
        comment: ''
    });

    const [averageRating, setAverageRating] = useState(0);
    const [totalReviews, setTotalReviews] = useState(0);
    const [ratingBreakdown, setRatingBreakdown] = useState<Record<number, number>>({});

    useEffect(() => {
        fetchReviews();
    }, [productId]);

    const fetchReviews = async () => {
        setIsLoading(true);
        try {
            const response = await fetch(`/api/products/${productId}/reviews`, {
                headers: isAuthenticated ? getAuthHeaders() : {},
            });

            if (response.ok) {
                const data = await response.json();
                setReviews(data.reviews || []);
                setAverageRating(data.average_rating || 0);
                setTotalReviews(data.total_reviews || 0);
                setRatingBreakdown(data.rating_breakdown || {});
            }
        } catch (error) {
            console.error('Failed to fetch reviews:', error);
            toast.error('Failed to load reviews');
        } finally {
            setIsLoading(false);
        }
    };

    const handleSubmitReview = async () => {
        if (!requireAuth('/login', 'Please sign in to write a review')) return;

        if (!reviewForm.title.trim()) {
            toast.error('Please enter a review title');
            return;
        }

        if (!reviewForm.comment.trim()) {
            toast.error('Please enter a review comment');
            return;
        }

        setIsSubmitting(true);
        try {
            const url = editingReview
                ? `/api/products/${productId}/reviews/${editingReview.id}`
                : `/api/products/${productId}/reviews`;

            const method = editingReview ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    ...getAuthHeaders(),
                },
                body: JSON.stringify(reviewForm),
            });

            if (response.ok) {
                const data = await response.json();

                if (editingReview) {
                    setReviews(prev => prev.map(review =>
                        review.id === editingReview.id ? data.review : review
                    ));
                    toast.success('Review updated successfully');
                    setEditingReview(null);
                } else {
                    setReviews(prev => [data.review, ...prev]);
                    toast.success('Review submitted successfully');
                }

                setShowReviewForm(false);
                setReviewForm({rating: 5, title: '', comment: ''});

                fetchReviews();
            } else {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to submit review');
            }
        } catch (error: any) {
            console.error('Review submission failed:', error);
            toast.error(error.message || 'Failed to submit review');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleDeleteReview = async (reviewId: number) => {
        if (!requireAuth()) return;

        if (!confirm('Are you sure you want to delete this review?')) return;

        try {
            const response = await fetch(`/api/products/${productId}/reviews/${reviewId}`, {
                method: 'DELETE',
                headers: getAuthHeaders(),
            });

            if (response.ok) {
                setReviews(prev => prev.filter(review => review.id !== reviewId));
                toast.success('Review deleted successfully');
                fetchReviews(); // Refresh statistics
            } else {
                throw new Error('Failed to delete review');
            }
        } catch (error) {
            console.error('Review deletion failed:', error);
            toast.error('Failed to delete review');
        }
    };

    const handleHelpfulVote = async (reviewId: number, helpful: boolean) => {
        if (!requireAuth('/login', 'Please sign in to vote')) return;

        try {
            const response = await fetch(`/api/products/${productId}/reviews/${reviewId}/helpful`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...getAuthHeaders(),
                },
                body: JSON.stringify({helpful}),
            });

            if (response.ok) {
                const data = await response.json();
                setReviews(prev => prev.map(review =>
                    review.id === reviewId
                        ? {
                            ...review,
                            helpful_count: data.helpful_count,
                            user_found_helpful: data.user_found_helpful
                        }
                        : review
                ));
            }
        } catch (error) {
            console.error('Helpful vote failed:', error);
            toast.error('Failed to record vote');
        }
    };

    const startEditReview = (review: Review) => {
        setEditingReview(review);
        setReviewForm({
            rating: review.rating,
            title: review.title,
            comment: review.comment
        });
        setShowReviewForm(true);
    };

    const renderStars = (rating: number, interactive = false, size = 'default') => {
        const sizeClasses = {
            sm: 'h-3 w-3',
            default: 'h-4 w-4',
            lg: 'h-5 w-5'
        };

        return (
            <div className="flex items-center space-x-1">
                {[1, 2, 3, 4, 5].map((star) => (
                    <Star
                        key={star}
                        className={`${sizeClasses[size]} ${
                            star <= rating
                                ? 'fill-yellow-400 text-yellow-400'
                                : 'text-gray-300'
                        } ${interactive ? 'cursor-pointer hover:text-yellow-400' : ''}`}
                        onClick={interactive ? () => setReviewForm(prev => ({...prev, rating: star})) : undefined}
                    />
                ))}
            </div>
        );
    };

    const getUserInitials = (name: string) => {
        return name.split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    if (isLoading) {
        return (
            <Card>
                <CardContent className="p-6">
                    <div className="animate-pulse space-y-4">
                        <div className="h-6 bg-gray-200 rounded w-1/3"></div>
                        <div className="space-y-3">
                            {[1, 2, 3].map(i => (
                                <div key={i} className="h-20 bg-gray-200 rounded"></div>
                            ))}
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Customer Reviews</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex items-center space-x-6">
                        <div className="text-center">
                            <div className="text-3xl font-bold">{averageRating.toFixed(1)}</div>
                            {renderStars(Math.round(averageRating), false, 'lg')}
                            <div className="text-sm text-gray-600 mt-1">
                                {totalReviews} review{totalReviews !== 1 ? 's' : ''}
                            </div>
                        </div>

                        <div className="flex-1 space-y-2">
                            {[5, 4, 3, 2, 1].map(rating => (
                                <div key={rating} className="flex items-center space-x-3">
                                    <span className="text-sm w-8">{rating}</span>
                                    <Star className="h-3 w-3 fill-yellow-400 text-yellow-400"/>
                                    <div className="flex-1 bg-gray-200 rounded-full h-2">
                                        <div
                                            className="bg-yellow-400 h-2 rounded-full"
                                            style={{
                                                width: `${totalReviews > 0 ? ((ratingBreakdown[rating] || 0) / totalReviews) * 100 : 0}%`
                                            }}
                                        />
                                    </div>
                                    <span className="text-sm text-gray-600 w-8">
                    {ratingBreakdown[rating] || 0}
                  </span>
                                </div>
                            ))}
                        </div>
                    </div>

                    {isAuthenticated && userCanReview && (
                        <Dialog open={showReviewForm} onOpenChange={setShowReviewForm}>
                            <DialogTrigger asChild>
                                <Button className="w-full">
                                    <MessageSquare className="w-4 h-4 mr-2"/>
                                    Write a Review
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-2xl">
                                <DialogHeader>
                                    <DialogTitle>
                                        {editingReview ? 'Edit Review' : `Write a Review for ${productName}`}
                                    </DialogTitle>
                                </DialogHeader>

                                <div className="space-y-4">
                                    <div>
                                        <Label>Rating</Label>
                                        <div className="mt-2">
                                            {renderStars(reviewForm.rating, true, 'lg')}
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="title" required>Review Title</Label>
                                        <input
                                            id="title"
                                            className="mt-1 w-full p-2 border border-gray-300 rounded-md"
                                            placeholder="Summarize your experience"
                                            value={reviewForm.title}
                                            onChange={(e) => setReviewForm(prev => ({...prev, title: e.target.value}))}
                                            maxLength={100}
                                        />
                                    </div>

                                    <div>
                                        <Label htmlFor="comment" required>Your Review</Label>
                                        <Textarea
                                            id="comment"
                                            className="mt-1"
                                            placeholder="Tell others about your experience with this product"
                                            value={reviewForm.comment}
                                            onChange={(e) => setReviewForm(prev => ({
                                                ...prev,
                                                comment: e.target.value
                                            }))}
                                            rows={4}
                                            maxLength={1000}
                                        />
                                        <div className="text-right text-xs text-gray-500 mt-1">
                                            {reviewForm.comment.length}/1000
                                        </div>
                                    </div>

                                    <div className="flex justify-end space-x-3">
                                        <Button
                                            variant="outline"
                                            onClick={() => {
                                                setShowReviewForm(false);
                                                setEditingReview(null);
                                                setReviewForm({rating: 5, title: '', comment: ''});
                                            }}
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            onClick={handleSubmitReview}
                                            disabled={isSubmitting}
                                        >
                                            {isSubmitting ? 'Submitting...' : editingReview ? 'Update Review' : 'Submit Review'}
                                        </Button>
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>
                    )}

                    {!isAuthenticated && (
                        <div className="text-center text-sm text-gray-600">
                            <Button variant="outline" asChild>
                                <a href="/login">Sign in to write a review</a>
                            </Button>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="space-y-4">
                {reviews.map((review) => (
                    <Card key={review.id}>
                        <CardContent className="p-6">
                            <div className="space-y-4">
                                {/* Review Header */}
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center space-x-3">
                                        <Avatar className="h-10 w-10">
                                            <AvatarImage src={review.user.avatar_url}/>
                                            <AvatarFallback>
                                                {getUserInitials(review.user.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div className="flex items-center space-x-2">
                                                <span className="font-medium">{review.user.name}</span>
                                                {review.verified_purchase && (
                                                    <Badge variant="outline" className="text-xs">
                                                        Verified Purchase
                                                    </Badge>
                                                )}
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                {renderStars(review.rating)}
                                                <span className="text-sm text-gray-600">
                          {new Date(review.created_at).toLocaleDateString()}
                        </span>
                                            </div>
                                        </div>
                                    </div>

                                    {review.is_own_review && (
                                        <div className="flex items-center space-x-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => startEditReview(review)}
                                            >
                                                <Edit className="h-4 w-4"/>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => handleDeleteReview(review.id)}
                                            >
                                                <Trash2 className="h-4 w-4"/>
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                <div>
                                    <h4 className="font-semibold mb-2">{review.title}</h4>
                                    <p className="text-gray-700 leading-relaxed">{review.comment}</p>
                                </div>

                                <div className="flex items-center justify-between pt-2 border-t">
                                    <div className="flex items-center space-x-4">
                                        {isAuthenticated && !review.is_own_review && (
                                            <div className="flex items-center space-x-2">
                                                <span className="text-sm text-gray-600">Was this helpful?</span>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleHelpfulVote(review.id, true)}
                                                    className={review.user_found_helpful ? 'text-green-600' : ''}
                                                >
                                                    <ThumbsUp className="h-4 w-4 mr-1"/>
                                                    Yes
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleHelpfulVote(review.id, false)}
                                                >
                                                    <ThumbsDown className="h-4 w-4 mr-1"/>
                                                    No
                                                </Button>
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex items-center space-x-2 text-sm text-gray-600">
                                        {review.helpful_count > 0 && (
                                            <span>{review.helpful_count} found this helpful</span>
                                        )}
                                        {review.updated_at !== review.created_at && (
                                            <span className="text-xs">
                        (edited {new Date(review.updated_at).toLocaleDateString()})
                      </span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}

                {reviews.length === 0 && (
                    <Card>
                        <CardContent className="p-6 text-center">
                            <MessageSquare className="h-12 w-12 text-gray-400 mx-auto mb-4"/>
                            <h3 className="text-lg font-semibold text-gray-900 mb-2">No reviews yet</h3>
                            <p className="text-gray-600 mb-4">
                                Be the first to share your thoughts about this product.
                            </p>
                            {isAuthenticated && userCanReview && (
                                <Button onClick={() => setShowReviewForm(true)}>
                                    Write the First Review
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </div>
    );
}
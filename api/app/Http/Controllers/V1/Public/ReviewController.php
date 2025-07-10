<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use App\Services\V1\Reviews\ReviewService;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreReviewRequest;
use App\Requests\V1\UpdateReviewRequest;
use App\Requests\V1\FilterReviewsRequest;
use App\Requests\V1\ReportReviewRequest;
use App\Requests\V1\ReviewHelpfulnessRequest;
use Illuminate\Http\Request;
use Exception;

class ReviewController extends Controller
{
    use ApiResponses;

    private ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    /**
     * Get reviews for a specific product with filtering and sorting
     *
     * Retrieve all approved reviews for a product with advanced filtering capabilities.
     * Supports filtering by rating, verified purchases, media presence, and various sorting options.
     * Essential for product detail pages and review browsing functionality.
     *
     * @group Product Reviews
     * @unauthenticated
     *
     * @urlParam product integer required The ID of the product to get reviews for. Example: 15
     *
     * @queryParam rating array optional Filter by specific ratings (1-5). Can be multiple values. Example: [4,5]
     * @queryParam verified_only boolean optional Show only verified purchase reviews. Default: false. Example: true
     * @queryParam with_media boolean optional Show only reviews with photos/videos. Default: false. Example: true
     * @queryParam sort_by string optional Sort reviews. Options: newest, oldest, rating_high, rating_low, helpful. Default: newest. Example: helpful
     * @queryParam per_page integer optional Number of reviews per page (1-50). Default: 15. Example: 20
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     *
     * @response 200 scenario="Reviews retrieved successfully" {
     *   "message": "Product reviews retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 123,
     *         "user": {
     *           "id": 45,
     *           "name": "Sarah Johnson",
     *           "email": "s***@example.com"
     *         },
     *         "product": {
     *           "id": 15,
     *           "name": "Premium Wireless Earbuds",
     *           "price_formatted": "£89.99"
     *         },
     *         "rating": 5,
     *         "title": "Excellent sound quality!",
     *         "content": "These earbuds exceeded my expectations. The noise cancellation is fantastic and battery life is exactly as advertised.",
     *         "is_verified_purchase": true,
     *         "is_featured": false,
     *         "helpful_votes": 12,
     *         "total_votes": 15,
     *         "helpfulness_ratio": 80.0,
     *         "user_voted": null,
     *         "media": [
     *           {
     *             "id": 67,
     *             "media_type": "image",
     *             "url": "https://yourapi.com/storage/reviews/review-image-1.jpg",
     *             "thumbnail_url": "https://yourapi.com/storage/reviews/thumb_review-image-1.jpg"
     *           }
     *         ],
     *         "response": null,
     *         "created_at": "2025-01-10T14:30:00.000000Z",
     *         "can_edit": false,
     *         "can_delete": false
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 127,
     *     "last_page": 9
     *   },
     *   "meta": {
     *     "product_id": 15,
     *     "total_reviews": 127,
     *     "average_rating": 4.3,
     *     "rating_breakdown": {
     *       "1": 2,
     *       "2": 8,
     *       "3": 15,
     *       "4": 45,
     *       "5": 57
     *     },
     *     "verified_purchase_count": 89,
     *     "with_media_count": 34
     *   }
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "No query results for model [App\\Models\\Product] 999"
     * }
     */
    public function index(FilterReviewsRequest $request, Product $product)
    {
        try {
            return $this->reviewService->getProductReviews($request, $product);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get detailed information about a specific review
     *
     * Retrieve comprehensive details about a single review including user information,
     * media attachments, helpfulness votes, and vendor responses if available.
     *
     * @group Product Reviews
     * @unauthenticated
     *
     * @urlParam review integer required The ID of the review to retrieve. Example: 123
     *
     * @response 200 scenario="Review retrieved successfully" {
     *   "message": "Review retrieved successfully.",
     *   "data": {
     *     "id": 123,
     *     "user": {
     *       "id": 45,
     *       "name": "Sarah Johnson",
     *       "email": "s***@example.com"
     *     },
     *     "product": {
     *       "id": 15,
     *       "name": "Premium Wireless Earbuds",
     *       "price_formatted": "£89.99",
     *       "featured_image": "https://yourapi.com/storage/products/featured-image.jpg"
     *     },
     *     "rating": 5,
     *     "title": "Excellent sound quality!",
     *     "content": "These earbuds exceeded my expectations...",
     *     "is_verified_purchase": true,
     *     "is_featured": false,
     *     "helpful_votes": 12,
     *     "total_votes": 15,
     *     "helpfulness_ratio": 80.0,
     *     "user_voted": null,
     *     "media": [],
     *     "response": null,
     *     "created_at": "2025-01-10T14:30:00.000000Z",
     *     "can_edit": false,
     *     "can_delete": false
     *   }
     * }
     *
     * @response 404 scenario="Review not found" {
     *   "message": "No query results for model [App\\Models\\Review] 999"
     * }
     */
    public function show(Request $request, Review $review)
    {
        try {
            return $this->reviewService->getReview($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new product review
     *
     * Submit a new review for a product. Users can only review products they have purchased
     * and can only submit one review per product. Supports uploading media files (images/videos)
     * with the review for enhanced credibility.
     *
     * @group Product Reviews
     * @authenticated
     *
     * @bodyParam product_id integer required The ID of the product being reviewed. Example: 15
     * @bodyParam order_item_id integer optional The order item ID for verified purchase reviews. Example: 234
     * @bodyParam rating integer required Rating from 1-5 stars. Example: 5
     * @bodyParam title string optional Short review title (3-255 characters). Example: "Excellent sound quality!"
     * @bodyParam content string required Detailed review content (10-2000 characters). Example: "These earbuds exceeded my expectations..."
     * @bodyParam media file[] optional Upload images or videos with review (max 5 files, 10MB each).
     * @bodyParam media.*.file file Photo or video file (jpg, jpeg, png, gif, mp4, mov, avi).
     *
     * @response 201 scenario="Review created successfully" {
     *   "message": "Review submitted successfully.",
     *   "data": {
     *     "id": 123,
     *     "user": {
     *       "id": 45,
     *       "name": "Sarah Johnson"
     *     },
     *     "product": {
     *       "id": 15,
     *       "name": "Premium Wireless Earbuds"
     *     },
     *     "rating": 5,
     *     "title": "Excellent sound quality!",
     *     "content": "These earbuds exceeded my expectations...",
     *     "is_verified_purchase": true,
     *     "is_featured": false,
     *     "helpful_votes": 0,
     *     "total_votes": 0,
     *     "media": [],
     *     "created_at": "2025-01-10T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "You have already reviewed this product.",
     *     "Rating must be between 1 and 5 stars.",
     *     "Review content must be at least 10 characters long."
     *   ]
     * }
     *
     * @response 403 scenario="Cannot review product" {
     *   "message": "You can only review products you have purchased."
     * }
     */
    public function store(StoreReviewRequest $request)
    {
        try {
            return $this->reviewService->createReview($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing review
     *
     * Update your own review within the allowed time window (30 days after creation).
     * You can modify the rating, title, content, and add/remove media attachments.
     *
     * @group Product Reviews
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to update. Example: 123
     *
     * @bodyParam rating integer optional Updated rating from 1-5 stars. Example: 4
     * @bodyParam title string optional Updated review title (3-255 characters). Example: "Good sound quality"
     * @bodyParam content string optional Updated review content (10-2000 characters). Example: "Updated thoughts on these earbuds..."
     * @bodyParam media file[] optional New media files to add (max 5 total files).
     * @bodyParam remove_media integer[] optional Array of media IDs to remove. Example: [67, 68]
     *
     * @response 200 scenario="Review updated successfully" {
     *   "message": "Review updated successfully.",
     *   "data": {
     *     "id": 123,
     *     "rating": 4,
     *     "title": "Good sound quality",
     *     "content": "Updated thoughts on these earbuds...",
     *     "updated_at": "2025-01-15T10:20:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Cannot edit review" {
     *   "message": "You can only edit your own reviews within 30 days of creation."
     * }
     *
     * @response 404 scenario="Review not found" {
     *   "message": "No query results for model [App\\Models\\Review] 999"
     * }
     */
    public function update(UpdateReviewRequest $request, Review $review)
    {
        try {
            return $this->reviewService->updateReview($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a review
     *
     * Delete your own review. This action is irreversible and will also remove
     * all associated media files and helpfulness votes.
     *
     * @group Product Reviews
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to delete. Example: 123
     *
     * @response 200 scenario="Review deleted successfully" {
     *   "message": "Review deleted successfully."
     * }
     *
     * @response 403 scenario="Cannot delete review" {
     *   "message": "You can only delete your own reviews."
     * }
     *
     * @response 404 scenario="Review not found" {
     *   "message": "No query results for model [App\\Models\\Review] 999"
     * }
     */
    public function destroy(Request $request, Review $review)
    {
        try {
            return $this->reviewService->deleteReview($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Vote on review helpfulness
     *
     * Mark a review as helpful or not helpful. Users cannot vote on their own reviews
     * and can only vote once per review (but can change their vote).
     *
     * @group Product Reviews
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to vote on. Example: 123
     *
     * @bodyParam is_helpful boolean required Whether the review was helpful. Example: true
     *
     * @response 200 scenario="Vote recorded successfully" {
     *   "message": "Thank you for your feedback!",
     *   "data": {
     *     "review_id": 123,
     *     "helpful_votes": 13,
     *     "total_votes": 16,
     *     "helpfulness_ratio": 81.3,
     *     "user_vote": true
     *   }
     * }
     *
     * @response 403 scenario="Cannot vote on own review" {
     *   "message": "You cannot vote on your own review."
     * }
     *
     * @response 409 scenario="Already voted" {
     *   "message": "You have already voted on this review with the same rating."
     * }
     */
    public function voteHelpfulness(ReviewHelpfulnessRequest $request, Review $review)
    {
        try {
            return $this->reviewService->voteHelpfulness($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Report an inappropriate review
     *
     * Report a review for inappropriate content, spam, or other violations.
     * Reported reviews are flagged for admin moderation review.
     *
     * @group Product Reviews
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to report. Example: 123
     *
     * @bodyParam reason string required Reason for reporting. Options: spam, inappropriate_language, fake_review, off_topic, personal_information, other. Example: spam
     * @bodyParam details string optional Additional details (required if reason is "other"). Example: "This review contains promotional links"
     *
     * @response 200 scenario="Review reported successfully" {
     *   "message": "Review reported successfully. Our moderation team will review it shortly.",
     *   "data": {
     *     "report_id": 45,
     *     "review_id": 123,
     *     "reason": "spam",
     *     "status": "pending"
     *   }
     * }
     *
     * @response 409 scenario="Already reported" {
     *   "message": "You have already reported this review."
     * }
     *
     * @response 403 scenario="Cannot report own review" {
     *   "message": "You cannot report your own review."
     * }
     */
    public function report(ReportReviewRequest $request, Review $review)
    {
        try {
            return $this->reviewService->reportReview($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

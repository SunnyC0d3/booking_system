<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewResponse;
use App\Services\V1\Reviews\ReviewResponseService;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreReviewResponseRequest;
use App\Requests\V1\UpdateReviewResponseRequest;
use Illuminate\Http\Request;
use Exception;

class ReviewResponseController extends Controller
{
    use ApiResponses;

    private ReviewResponseService $reviewResponseService;

    public function __construct(ReviewResponseService $reviewResponseService)
    {
        $this->reviewResponseService = $reviewResponseService;
    }

    /**
     * Get all responses for a specific review (Public viewing)
     *
     * Retrieve all approved vendor responses for a specific review.
     * This endpoint is publicly accessible and does not require authentication.
     *
     * @group Public Review Responses
     *
     * @urlParam review integer required The ID of the review. Example: 123
     *
     * @queryParam per_page integer optional Number of responses per page (1-50). Default: 15. Example: 20
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     *
     * @response 200 scenario="Responses retrieved successfully" {
     *   "message": "Review responses retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 45,
     *         "vendor": {
     *           "id": 15,
     *           "name": "Tech Haven"
     *         },
     *         "content": "Thank you for your feedback! We're glad you enjoyed our product.",
     *         "created_at": "2025-01-16T14:30:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 2,
     *     "last_page": 1
     *   }
     * }
     */
    public function publicIndex(Request $request, Review $review)
    {
        try {
            return $this->reviewResponseService->getPublicResponses($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get individual response details (Public viewing)
     *
     * @group Public Review Responses
     *
     * @urlParam review integer required The ID of the review. Example: 123
     * @urlParam response integer required The ID of the response. Example: 45
     */
    public function publicShow(Request $request, Review $review, ReviewResponse $response)
    {
        try {
            return $this->reviewResponseService->getPublicResponse($request, $review, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Store a new vendor response to a review
     *
     * Allows vendors to respond to reviews on their products. Each vendor can only
     * respond once per review, but can edit their response within 24 hours.
     *
     * @group Vendor Review Responses
     * @authenticated
     *
     * @urlParam review integer required The ID of the review to respond to. Example: 123
     *
     * @bodyParam content string required Response content (10-1000 characters). Example: "Thank you for your feedback! We're glad you enjoyed our product."
     *
     * @response 201 scenario="Response created successfully" {
     *   "message": "Response submitted successfully.",
     *   "data": {
     *     "id": 45,
     *     "review_id": 123,
     *     "vendor": {
     *       "id": 15,
     *       "name": "Tech Haven"
     *     },
     *     "user": {
     *       "id": 12,
     *       "name": "John Vendor"
     *     },
     *     "content": "Thank you for your feedback! We're glad you enjoyed our product.",
     *     "is_approved": true,
     *     "created_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Not authorized to respond" {
     *   "message": "You can only respond to reviews on your products."
     * }
     *
     * @response 409 scenario="Already responded" {
     *   "message": "You have already responded to this review."
     * }
     */
    public function store(StoreReviewResponseRequest $request, Review $review)
    {
        try {
            return $this->reviewResponseService->createResponse($request, $review);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing vendor response
     *
     * Vendors can edit their response within 24 hours of creation.
     * After 24 hours, responses become locked to maintain transparency.
     *
     * @group Vendor Review Responses
     * @authenticated
     *
     * @urlParam review integer required The ID of the review. Example: 123
     * @urlParam response integer required The ID of the response to update. Example: 45
     *
     * @bodyParam content string required Updated response content (10-1000 characters). Example: "Thank you for your updated feedback!"
     *
     * @response 200 scenario="Response updated successfully" {
     *   "message": "Response updated successfully.",
     *   "data": {
     *     "id": 45,
     *     "content": "Thank you for your updated feedback!",
     *     "updated_at": "2025-01-16T15:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Cannot edit response" {
     *   "message": "You can only edit your responses within 24 hours of creation."
     * }
     */
    public function update(UpdateReviewResponseRequest $request, Review $review, ReviewResponse $response)
    {
        try {
            return $this->reviewResponseService->updateResponse($request, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a vendor response
     *
     * Vendors can delete their response within 24 hours of creation.
     * This action is irreversible.
     *
     * @group Vendor Review Responses
     * @authenticated
     *
     * @urlParam review integer required The ID of the review. Example: 123
     * @urlParam response integer required The ID of the response to delete. Example: 45
     *
     * @response 200 scenario="Response deleted successfully" {
     *   "message": "Response deleted successfully."
     * }
     *
     * @response 403 scenario="Cannot delete response" {
     *   "message": "You can only delete your responses within 24 hours of creation."
     * }
     */
    public function destroy(Request $request, Review $review, ReviewResponse $response)
    {
        try {
            return $this->reviewResponseService->deleteResponse($request, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get vendor's responses (for vendor dashboard)
     *
     * Retrieve all responses made by the authenticated vendor with filtering
     * and sorting options for vendor dashboard management.
     *
     * @group Vendor Review Responses
     * @authenticated
     *
     * @queryParam product_id integer optional Filter by specific product ID. Example: 15
     * @queryParam rating array optional Filter by review ratings. Example: [1,2]
     * @queryParam sort_by string optional Sort responses. Options: newest, oldest, rating_high, rating_low. Default: newest. Example: newest
     * @queryParam per_page integer optional Number of responses per page (1-50). Default: 15. Example: 20
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     *
     * @response 200 scenario="Responses retrieved successfully" {
     *   "message": "Your responses retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 45,
     *         "review": {
     *           "id": 123,
     *           "rating": 4,
     *           "title": "Good product",
     *           "content": "Works well but could be better...",
     *           "user": {
     *             "name": "Customer Name"
     *           },
     *           "product": {
     *             "id": 15,
     *             "name": "Premium Wireless Earbuds"
     *           }
     *         },
     *         "content": "Thank you for your feedback!",
     *         "is_approved": true,
     *         "created_at": "2025-01-16T14:30:00.000000Z",
     *         "can_edit": true,
     *         "can_delete": true
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 25,
     *     "last_page": 2
     *   }
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->reviewResponseService->getVendorResponses($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get individual response details
     *
     * @group Vendor Review Responses
     * @authenticated
     */
    public function show(Request $request, Review $review, ReviewResponse $response)
    {
        try {
            return $this->reviewResponseService->getResponse($request, $response);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get unanswered reviews for vendor
     *
     * @group Vendor Review Responses
     * @authenticated
     */
    public function getUnansweredReviews(Request $request)
    {
        try {
            return $this->reviewResponseService->getUnansweredReviews($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

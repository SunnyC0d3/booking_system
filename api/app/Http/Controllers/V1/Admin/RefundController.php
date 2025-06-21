<?php

namespace App\Http\Controllers\V1\Admin;

use App\Constants\PaymentMethods;
use App\Http\Controllers\Controller;
use App\Services\V1\Orders\Refunds\RefundProcessor;
use App\Services\V1\Orders\Refunds\Refunds;
use App\Services\V1\Orders\Refunds\ManualStripeRefund;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    use ApiResponses;

    private $refunds;

    public function __construct(Refunds $refunds)
    {
        $this->refunds = $refunds;
    }

    /**
     * Retrieve a paginated list of all refund records
     *
     * Get a comprehensive list of all refunds processed in the system with detailed information
     * about amounts, statuses, associated orders, and processing details. This endpoint is essential
     * for financial reporting, refund tracking, customer service, and administrative oversight
     * of refund operations. Includes complete audit trail for each refund transaction.
     *
     * @group Refund Management
     * @authenticated
     *
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of refunds per page (max 100). Default: 20. Example: 25
     * @queryParam status string optional Filter by refund status. Available: Pending, Processing, Refunded, Partially Refunded, Failed, Cancelled, Declined. Example: Refunded
     * @queryParam date_from string optional Filter refunds from this date (YYYY-MM-DD format). Example: 2025-01-01
     * @queryParam date_to string optional Filter refunds to this date (YYYY-MM-DD format). Example: 2025-01-31
     * @queryParam order_id integer optional Filter refunds by specific order ID. Example: 45
     * @queryParam user_id integer optional Filter refunds by specific customer ID. Example: 123
     * @queryParam amount_min numeric optional Filter refunds with minimum amount in pounds. Example: 10.00
     * @queryParam amount_max numeric optional Filter refunds with maximum amount in pounds. Example: 500.00
     *
     * @response 200 scenario="Refunds retrieved successfully" {
     *     "message": "Refunds retrieved successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 1,
     *                 "amount": 2999,
     *                 "amount_formatted": "£29.99",
     *                 "processed_at": "2025-01-15T14:45:00.000000Z",
     *                 "notes": "Refund processed for damaged product - customer reported speaker crackling at high volume",
     *                 "status": {
     *                     "id": 3,
     *                     "name": "Refunded"
     *                 },
     *                 "order_return": {
     *                     "id": 15,
     *                     "reason": "Product damaged on arrival - speaker has crackling sound at any volume level",
     *                     "status": {
     *                         "id": 7,
     *                         "name": "Completed"
     *                     },
     *                     "created_at": "2025-01-14T09:30:00.000000Z",
     *                     "updated_at": "2025-01-15T14:45:00.000000Z",
     *                     "order_item": {
     *                         "id": 25,
     *                         "quantity": 1,
     *                         "price": 2999,
     *                         "price_formatted": "£29.99",
     *                         "product": {
     *                             "id": 12,
     *                             "name": "Wireless Headphones",
     *                             "description": "Premium quality wireless headphones with active noise cancellation"
     *                         },
     *                         "order": {
     *                             "id": 8,
     *                             "total_amount": 5998,
     *                             "total_amount_formatted": "£59.98",
     *                             "created_at": "2025-01-10T16:30:00.000000Z",
     *                             "user": {
     *                                 "id": 3,
     *                                 "name": "Sarah Johnson",
     *                                 "email": "sarah@example.com",
     *                                 "email_verified_at": "2025-01-05T12:00:00.000000Z"
     *                             }
     *                         }
     *                     }
     *                 },
     *                 "created_at": "2025-01-15T14:45:00.000000Z",
     *                 "updated_at": "2025-01-15T14:45:00.000000Z"
     *             },
     *             {
     *                 "id": 2,
     *                 "amount": 7999,
     *                 "amount_formatted": "£79.99",
     *                 "processed_at": "2025-01-14T11:20:00.000000Z",
     *                 "notes": "Customer changed mind within return period - full refund approved",
     *                 "status": {
     *                     "id": 3,
     *                     "name": "Refunded"
     *                 },
     *                 "order_return": {
     *                     "id": 12,
     *                     "reason": "Changed mind about purchase - no longer needed",
     *                     "status": {
     *                         "id": 7,
     *                         "name": "Completed"
     *                     },
     *                     "order_item": {
     *                         "id": 18,
     *                         "quantity": 1,
     *                         "price": 7999,
     *                         "price_formatted": "£79.99",
     *                         "product": {
     *                             "id": 23,
     *                             "name": "Premium Bluetooth Speaker"
     *                         },
     *                         "order": {
     *                             "id": 6,
     *                             "total_amount": 7999,
     *                             "total_amount_formatted": "£79.99",
     *                             "user": {
     *                                 "id": 7,
     *                                 "name": "Michael Chen",
     *                                 "email": "michael@example.com"
     *                             }
     *                         }
     *                     }
     *                 },
     *                 "created_at": "2025-01-14T11:20:00.000000Z",
     *                 "updated_at": "2025-01-14T11:20:00.000000Z"
     *             },
     *             {
     *                 "id": 3,
     *                 "amount": 1500,
     *                 "amount_formatted": "£15.00",
     *                 "processed_at": null,
     *                 "notes": "Partial refund for shipping delay compensation",
     *                 "status": {
     *                     "id": 1,
     *                     "name": "Processing"
     *                 },
     *                 "order_return": {
     *                     "id": 18,
     *                     "reason": "Shipping delay compensation request",
     *                     "status": {
     *                         "id": 3,
     *                         "name": "Approved"
     *                     },
     *                     "order_item": {
     *                         "id": 32,
     *                         "product": {
     *                             "id": 45,
     *                             "name": "Wireless Charging Pad"
     *                         },
     *                         "order": {
     *                             "id": 12,
     *                             "user": {
     *                                 "id": 15,
     *                                 "name": "Emma Wilson",
     *                                 "email": "emma@example.com"
     *                             }
     *                         }
     *                     }
     *                 },
     *                 "created_at": "2025-01-16T09:15:00.000000Z",
     *                 "updated_at": "2025-01-16T09:15:00.000000Z"
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 20,
     *         "total": 127,
     *         "last_page": 7,
     *         "from": 1,
     *         "to": 20,
     *         "path": "https://yourapi.com/api/v1/admin/refunds",
     *         "first_page_url": "https://yourapi.com/api/v1/admin/refunds?page=1",
     *         "last_page_url": "https://yourapi.com/api/v1/admin/refunds?page=7",
     *         "next_page_url": "https://yourapi.com/api/v1/admin/refunds?page=2",
     *         "prev_page_url": null
     *     }
     * }
     *
     * @response 200 scenario="No refunds found" {
     *     "message": "Refunds retrieved successfully.",
     *     "data": {
     *         "data": [],
     *         "current_page": 1,
     *         "per_page": 20,
     *         "total": 0,
     *         "last_page": 1,
     *         "from": null,
     *         "to": null
     *     }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *     "errors": [
     *         "The status field must be one of: Pending, Processing, Refunded, Partially Refunded, Failed, Cancelled, Declined.",
     *         "The date from field must be a valid date in YYYY-MM-DD format.",
     *         "The amount min field must be a number greater than 0.",
     *         "The order id field must be an integer."
     *     ]
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->refunds->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Process a refund for an approved return through the specified payment gateway
     *
     * Execute a refund transaction for an approved return request through the specified payment
     * gateway. This endpoint handles the actual refund processing, updates order statuses, and
     * maintains audit trails. Only approved returns can be processed for refunds. The system
     * automatically calculates refund amounts and updates payment statuses accordingly.
     *
     * @group Refund Processing
     * @authenticated
     *
     * @urlParam gateway string required The payment gateway to process the refund through. Currently supports: stripe. Example: stripe
     * @urlParam id integer required The ID of the return request to refund. Must be an approved return. Example: 15
     *
     * @response 200 scenario="Refund processed successfully" {
     *     "message": "Refund processed successfully."
     * }
     *
     * @response 400 scenario="Unsupported payment gateway" {
     *     "message": "Unsupported payment gateway"
     * }
     *
     * @response 400 scenario="Return not approved for refund" {
     *     "message": "This return has not been approved for refund."
     * }
     *
     * @response 400 scenario="Insufficient refundable amount" {
     *     "message": "Refund amount exceeds the remaining refundable balance for this order."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Return request not found" {
     *     "message": "Return request not found."
     * }
     *
     * @response 404 scenario="No payment found for order" {
     *     "message": "No valid payment found for this order. Cannot process refund."
     * }
     *
     * @response 422 scenario="Refund failed at gateway" {
     *     "message": "Refund failed. Please try again later."
     * }
     *
     * @response 422 scenario="Payment intent not found" {
     *     "message": "Payment intent not found in payment gateway. Cannot process refund."
     * }
     *
     * @response 409 scenario="Refund already processed" {
     *     "message": "A refund has already been processed for this return request."
     * }
     *
     * @response 422 scenario="Order not in refundable state" {
     *     "message": "Order is not in a state that allows refunds (must be paid or partially refunded)."
     * }
     *
     * @response 500 scenario="Gateway communication error" {
     *     "message": "Unable to communicate with payment gateway. Please try again later."
     * }
     *
     * @response 500 scenario="Refund processing error" {
     *     "message": "An error occurred while processing the refund."
     * }
     */
    public function refund(Request $request, string $gateway, int $id)
    {
        try {
            $handler = $this->handle($gateway);

            if (!class_exists($handler)) {
                return $this->error('Unsupported payment gateway', 400);
            }

            $refundProcessor = new RefundProcessor(app($handler));

            return $refundProcessor->refund($request, $id);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Map payment gateway names to their corresponding refund handler classes.
     *
     * This private method provides a mapping between payment gateway identifiers and their
     * corresponding refund handler implementations. This allows for easy extension to support
     * additional payment gateways in the future while maintaining a clean separation of concerns.
     *
     * @param string $gateway The payment gateway identifier (e.g., 'stripe', 'paypal')
     * @return string|null The fully qualified class name of the refund handler, or null if unsupported
     */
    private function handle(string $gateway)
    {
        return match ($gateway) {
            PaymentMethods::STRIPE => ManualStripeRefund::class,
            default => null,
        };
    }
}

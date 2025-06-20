<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Payments\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;

class PaymentController extends Controller
{
    use ApiResponses;

    private $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * Retrieve paginated list of payments
     *
     * Get a comprehensive list of all payments in the system with pagination support.
     * This endpoint provides administrators with detailed payment information including
     * transaction details, payment methods, amounts, statuses, and associated order information.
     * Essential for financial reporting, payment reconciliation, and transaction monitoring.
     *
     * @group Payment Management
     * @authenticated
     *
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of payments per page (max 100). Default: 15. Example: 25
     * @queryParam status string optional Filter by payment status. Available: Paid, Pending, Failed, Canceled, Refunded, Partially Refunded. Example: Paid
     * @queryParam method string optional Filter by payment method. Available: stripe, paypal, card, bank transfer, apple pay, google pay. Example: stripe
     * @queryParam user_id integer optional Filter payments by specific user ID. Example: 123
     * @queryParam order_id integer optional Filter payments by specific order ID. Example: 456
     * @queryParam date_from string optional Filter payments from this date (YYYY-MM-DD format). Example: 2025-01-01
     * @queryParam date_to string optional Filter payments to this date (YYYY-MM-DD format). Example: 2025-01-31
     * @queryParam amount_min numeric optional Filter payments with minimum amount in pounds. Example: 10.00
     * @queryParam amount_max numeric optional Filter payments with maximum amount in pounds. Example: 500.00
     *
     * @response 200 scenario="Payments retrieved successfully" {
     *   "message": "Payments retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 267,
     *         "gateway": "stripe",
     *         "amount": 8498,
     *         "amount_formatted": "£84.98",
     *         "method": "stripe",
     *         "status": "Paid",
     *         "transaction_reference": "pi_1Hxxxxxxxxxxxx",
     *         "processed_at": "2025-01-15T14:22:00",
     *         "created_at": "2025-01-15T14:21:00",
     *         "updated_at": "2025-01-15T14:22:00",
     *         "user": {
     *           "id": 123,
     *           "name": "Sarah Johnson",
     *           "email": "sarah.johnson@example.com",
     *           "email_verified_at": "2025-01-10T08:00:00.000000Z"
     *         },
     *         "order": {
     *           "id": 45,
     *           "total_amount": 8498,
     *           "total_amount_formatted": "£84.98",
     *           "status": {
     *             "id": 3,
     *             "name": "Confirmed"
     *           },
     *           "created_at": "2025-01-15T14:20:00.000000Z",
     *           "updated_at": "2025-01-15T14:22:00.000000Z"
     *         },
     *         "payment_method": {
     *           "id": 1,
     *           "name": "stripe"
     *         }
     *       },
     *       {
     *         "id": 265,
     *         "gateway": "stripe",
     *         "amount": 2999,
     *         "amount_formatted": "£29.99",
     *         "method": "stripe",
     *         "status": "Partially Refunded",
     *         "transaction_reference": "pi_1Gxxxxxxxxxx",
     *         "processed_at": "2025-01-10T16:32:00",
     *         "created_at": "2025-01-10T16:31:00",
     *         "updated_at": "2025-01-14T11:25:00",
     *         "user": {
     *           "id": 89,
     *           "name": "Michael Chen",
     *           "email": "michael.chen@example.com",
     *           "email_verified_at": "2025-01-08T12:00:00.000000Z"
     *         },
     *         "order": {
     *           "id": 38,
     *           "total_amount": 2999,
     *           "total_amount_formatted": "£29.99",
     *           "status": {
     *             "id": 9,
     *             "name": "Partially Refunded"
     *           },
     *           "created_at": "2025-01-10T16:30:00.000000Z",
     *           "updated_at": "2025-01-14T11:25:00.000000Z"
     *         },
     *         "payment_method": {
     *           "id": 1,
     *           "name": "stripe"
     *         }
     *       },
     *       {
     *         "id": 263,
     *         "gateway": "stripe",
     *         "amount": 15500,
     *         "amount_formatted": "£155.00",
     *         "method": "stripe",
     *         "status": "Failed",
     *         "transaction_reference": "pi_1Fxxxxxxxxxx",
     *         "processed_at": "2025-01-08T09:15:00",
     *         "created_at": "2025-01-08T09:14:00",
     *         "updated_at": "2025-01-08T09:15:00",
     *         "user": {
     *           "id": 67,
     *           "name": "Emma Wilson",
     *           "email": "emma.wilson@example.com",
     *           "email_verified_at": "2025-01-05T14:00:00.000000Z"
     *         },
     *         "order": {
     *           "id": 34,
     *           "total_amount": 15500,
     *           "total_amount_formatted": "£155.00",
     *           "status": {
     *             "id": 10,
     *             "name": "Failed"
     *           },
     *           "created_at": "2025-01-08T09:12:00.000000Z",
     *           "updated_at": "2025-01-08T09:15:00.000000Z"
     *         },
     *         "payment_method": {
     *           "id": 1,
     *           "name": "stripe"
     *         }
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 1247,
     *     "last_page": 84,
     *     "from": 1,
     *     "to": 15,
     *     "path": "https://yourapi.com/api/v1/admin/payments",
     *     "first_page_url": "https://yourapi.com/api/v1/admin/payments?page=1",
     *     "last_page_url": "https://yourapi.com/api/v1/admin/payments?page=84",
     *     "next_page_url": "https://yourapi.com/api/v1/admin/payments?page=2",
     *     "prev_page_url": null
     *   }
     * }
     *
     * @response 200 scenario="Filtered payments with specific status" {
     *   "message": "Payments retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 268,
     *         "gateway": "stripe",
     *         "amount": 5999,
     *         "amount_formatted": "£59.99",
     *         "method": "stripe",
     *         "status": "Pending",
     *         "transaction_reference": "pi_1Jxxxxxxxxxx",
     *         "processed_at": "2025-01-16T10:30:00",
     *         "created_at": "2025-01-16T10:29:00",
     *         "updated_at": "2025-01-16T10:30:00",
     *         "user": {
     *           "id": 156,
     *           "name": "David Rodriguez",
     *           "email": "david.rodriguez@example.com"
     *         },
     *         "order": {
     *           "id": 47,
     *           "total_amount": 5999,
     *           "total_amount_formatted": "£59.99",
     *           "status": {
     *             "id": 1,
     *             "name": "Pending Payment"
     *           }
     *         },
     *         "payment_method": {
     *           "id": 1,
     *           "name": "stripe"
     *         }
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 23,
     *     "last_page": 2,
     *     "from": 1,
     *     "to": 15
     *   }
     * }
     *
     * @response 200 scenario="No payments found" {
     *   "message": "Payments retrieved successfully.",
     *   "data": {
     *     "data": [],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 0,
     *     "last_page": 1,
     *     "from": null,
     *     "to": null
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *   "errors": [
     *     "The status field must be one of: Paid, Pending, Failed, Canceled, Refunded, Partially Refunded.",
     *     "The date from field must be a valid date in YYYY-MM-DD format.",
     *     "The amount min field must be a number greater than 0.",
     *     "The user id field must be an integer."
     *   ]
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->payment->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

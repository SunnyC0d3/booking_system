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
     * Retrieve a paginated list of all refund records.
     *
     * @group Refunds
     * @authenticated
     *
     * @response 200 {
     *     "message": "Refunds retrieved successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 1,
     *                 "amount": 2999,
     *                 "processed_at": "2025-01-15T14:45:00.000000Z",
     *                 "notes": "Refund processed for damaged product",
     *                 "created_at": "2025-01-15T14:45:00.000000Z",
     *                 "updated_at": "2025-01-15T14:45:00.000000Z",
     *                 "status": {
     *                     "id": 1,
     *                     "name": "Refunded"
     *                 },
     *                 "order_return": {
     *                     "id": 15,
     *                     "reason": "Product damaged on arrival",
     *                     "order_item": {
     *                         "id": 25,
     *                         "product": {
     *                             "id": 12,
     *                             "name": "Wireless Headphones"
     *                         },
     *                         "order": {
     *                             "id": 8,
     *                             "total_amount": 5998,
     *                             "user": {
     *                                 "id": 3,
     *                                 "email": "customer@example.com"
     *                             }
     *                         }
     *                     }
     *                 }
     *             }
     *         ],
     *         "current_page": 1,
     *         "per_page": 20,
     *         "total": 32
     *     }
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Process a refund for an approved return through the specified payment gateway.
     *
     * @group Refunds
     * @authenticated
     *
     * @urlParam gateway string required The payment gateway to process the refund through. Currently supports: stripe. Example: stripe
     * @urlParam id integer required The ID of the return request to refund. Example: 15
     *
     * @response 200 {
     *     "message": "Refund processed successfully."
     * }
     *
     * @response 400 {
     *     "message": "Unsupported payment gateway"
     * }
     *
     * @response 400 {
     *     "message": "This return has not been approved for refund."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     *
     * @response 404 {
     *     "message": "Return request not found."
     * }
     *
     * @response 422 {
     *     "message": "Refund failed. Please try again later."
     * }
     *
     * @response 500 {
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
     * @param string $gateway The payment gateway identifier
     * @return string|null The fully qualified class name of the refund handler
     */
    private function handle(string $gateway)
    {
        return match ($gateway) {
            PaymentMethods::STRIPE => ManualStripeRefund::class,
            default => null,
        };
    }
}

<?php

namespace App\Http\Controllers\V1\Public;

use App\Requests\V1\VerifyPaymentRequest;
use App\Services\V1\Webhook\StripeWebhook;
use Illuminate\Http\Request;
use App\Requests\V1\StorePaymentRequest;
use App\Http\Controllers\Controller;
use App\Services\V1\Payments\StripePayment;
use App\Traits\V1\ApiResponses;
use App\Constants\PaymentMethods;
use \Exception;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
    }

    /**
     * Stripe webhook listener
     *
     * This endpoint receives webhook events from Stripe when payment-related events occur.
     * It automatically processes payment status updates, handles refunds, and manages order status changes.
     * This endpoint is called directly by Stripe's servers and requires proper webhook signature verification.
     *
     * **Important**: This endpoint should be excluded from CSRF protection and rate limiting.
     *
     * @group Payment Webhooks
     * @unauthenticated
     *
     * @bodyParam id string required The unique identifier for the webhook event. Example: evt_1234567890abcdef
     * @bodyParam object string required Always "event" for webhook events. Example: event
     * @bodyParam type string required The type of event (e.g., payment_intent.succeeded, charge.refunded). Example: payment_intent.succeeded
     * @bodyParam data object required The event data containing the relevant object (payment_intent, charge, etc.).
     * @bodyParam data.object object required The main object for this event (PaymentIntent, Charge, Refund, etc.).
     * @bodyParam created integer required Unix timestamp when the event was created. Example: 1640995200
     * @bodyParam livemode boolean required Whether this event is from live mode (true) or test mode (false). Example: false
     *
     * @response 200 scenario="Webhook processed successfully" {
     *   "status": true,
     *   "message": "Webhook update.",
     *   "data": null
     * }
     *
     * @response 200 scenario="Payment success webhook" {
     *   "status": true,
     *   "message": "Payment success webhook processed.",
     *   "data": null
     * }
     *
     * @response 200 scenario="Refund webhook processed" {
     *   "status": true,
     *   "message": "External refund processed.",
     *   "data": null
     * }
     *
     * @response 400 scenario="Invalid Stripe signature" {
     *   "status": false,
     *   "message": "Invalid Stripe signature",
     *   "data": null
     * }
     *
     * @response 400 scenario="Invalid payload format" {
     *   "status": false,
     *   "message": "Invalid payload",
     *   "data": null
     * }
     *
     * @response 500 scenario="Webhook processing error" {
     *   "status": false,
     *   "message": "An error occurred while processing the webhook.",
     *   "data": null
     * }
     */
    public function stripeWebhook(Request $request)
    {
        try {
            $handler = app(StripeWebhook::class);
            return $handler->webhook($request);
        } catch (Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create or retrieve payment intent
     *
     * Creates a new Stripe PaymentIntent for the specified order, or returns an existing one if already created.
     * This prevents duplicate payment intents for the same order. The client secret returned should be used
     * on the frontend to complete the payment with Stripe's client-side libraries.
     *
     * @group Payment Processing
     * @authenticated
     *
     * @urlParam gateway string required The payment gateway to use. Currently only "stripe" is supported. Example: stripe
     *
     * @bodyParam order_id integer required The ID of the order to create payment for. Must be a valid, unpaid order. Example: 123
     *
     * @response 200 scenario="New PaymentIntent created" {
     *   "status": true,
     *   "message": "PaymentIntent created successfully.",
     *   "data": {
     *     "client_secret": "pi_1234567890abcdef_secret_xyz789abc123"
     *   }
     * }
     *
     * @response 200 scenario="Existing PaymentIntent retrieved" {
     *   "status": true,
     *   "message": "Existing PaymentIntent retrieved.",
     *   "data": {
     *     "client_secret": "pi_1234567890abcdef_secret_xyz789abc123"
     *   }
     * }
     *
     * @response 200 scenario="Order already paid" {
     *   "status": true,
     *   "message": "Payment has already been processed for this order.",
     *   "data": null
     * }
     *
     * @response 400 scenario="Unsupported gateway" {
     *   "status": false,
     *   "message": "Unsupported payment gateway",
     *   "data": null
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "status": false,
     *   "message": "No query results for model [App\\Models\\Order] 999",
     *   "data": null
     * }
     *
     * @response 422 scenario="Invalid order data" {
     *   "errors": [
     *     "The order id field is required.",
     *     "The order id must be an integer.",
     *     "The selected order id is invalid."
     *   ]
     * }
     *
     * @response 400 scenario="Order not eligible for payment" {
     *   "status": false,
     *   "message": "Order has already been paid or is not in a payable state.",
     *   "data": null
     * }
     *
     * @response 500 scenario="Stripe API error" {
     *   "status": false,
     *   "message": "Unable to create payment intent with Stripe. Please try again.",
     *   "data": null
     * }
     */
    public function store(StorePaymentRequest $request, string $gateway)
    {
        try {
            $handler = $this->handle($gateway);

            if (!class_exists($handler)) {
                return $this->error('Unsupported payment gateway', 400);
            }

            $handler = app($handler);

            return $handler->createPayment($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Verify payment status
     *
     * Manually verifies the status of a payment with Stripe and updates the local database accordingly.
     * This is useful for handling cases where webhooks might have failed or been delayed.
     * It fetches the latest payment status directly from Stripe and synchronizes it with your database.
     *
     * @group Payment Processing
     * @authenticated
     *
     * @urlParam gateway string required The payment gateway used. Currently only "stripe" is supported. Example: stripe
     *
     * @bodyParam payment_intent_id string required The Stripe PaymentIntent ID to verify. Must be a valid Stripe PaymentIntent ID format. Example: pi_1234567890abcdef
     * @bodyParam order_id integer required The ID of the order associated with this payment for verification. Example: 123
     *
     * @response 200 scenario="Payment verified as successful" {
     *   "status": true,
     *   "message": "Payment verified",
     *   "data": {
     *     "payment_status": "Paid",
     *     "order_status": "Confirmed"
     *   }
     * }
     *
     * @response 200 scenario="Payment still pending" {
     *   "status": true,
     *   "message": "Payment verified",
     *   "data": {
     *     "payment_status": "Pending",
     *     "order_status": "Pending Payment"
     *   }
     * }
     *
     * @response 200 scenario="Payment failed" {
     *   "status": true,
     *   "message": "Payment verified",
     *   "data": {
     *     "payment_status": "Failed",
     *     "order_status": "Failed"
     *   }
     * }
     *
     * @response 400 scenario="Unsupported gateway" {
     *   "status": false,
     *   "message": "Unsupported payment gateway",
     *   "data": null
     * }
     *
     * @response 404 scenario="Payment not found in database" {
     *   "status": false,
     *   "message": "Payment not found",
     *   "data": null
     * }
     *
     * @response 400 scenario="Payment amount mismatch" {
     *   "status": false,
     *   "message": "Payment amount mismatch",
     *   "data": null
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The payment intent id field is required.",
     *     "The payment intent id format is invalid. Must be a valid Stripe payment intent ID.",
     *     "The order id field is required.",
     *     "The selected order id is invalid."
     *   ]
     * }
     *
     * @response 500 scenario="Stripe API error" {
     *   "status": false,
     *   "message": "Unable to retrieve payment from Stripe: No such payment_intent: 'pi_invalid'",
     *   "data": null
     * }
     */
    public function verify(VerifyPaymentRequest $request, string $gateway)
    {
        try {
            $handler = $this->handle($gateway);

            if (!class_exists($handler)) {
                return $this->error('Unsupported payment gateway', 400);
            }

            $handler = app($handler);

            return $handler->verifyPayment($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get payment handler class for gateway
     *
     * Maps payment gateway identifiers to their corresponding handler classes.
     * This allows for easy extension to support additional payment gateways in the future.
     *
     * @param string $gateway The payment gateway identifier
     * @return string|null The fully qualified class name of the payment handler
     */
    private function handle(string $gateway)
    {
        return match ($gateway) {
            PaymentMethods::STRIPE => StripePayment::class,
            default => null,
        };
    }
}

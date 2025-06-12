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
     * Stripe Webhook Listener
     *
     * This endpoint is triggered by Stripe when a payment event occurs (e.g., payment succeeded, failed).
     * It verifies the webhook signature and processes the payment status accordingly.
     *
     * @group Payments
     *
     * @bodyParam object $event Stripe webhook payload. The full JSON payload sent by Stripe will be validated internally.
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Webhook update.",
     *   "data": null
     * }
     *
     * @response 400 scenario="Invalid Signature or Payload" {
     *   "status": false,
     *   "message": "Invalid Stripe signature",
     *   "data": null
     * }
     *
     * @response 400 scenario="Invalid Payload Format" {
     *   "status": false,
     *   "message": "Invalid payload",
     *   "data": null
     * }
     *
     * @urlParam none
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
     * Initiate Payment
     *
     * Creates or retrieves a Stripe PaymentIntent for a given order. If a payment already exists,
     * it will return the existing client secret to avoid duplicate charges.
     *
     * @group Payments
     *
     * @bodyParam order_id int required The ID of the order being paid for. Example: 123
     *
     * @urlParam gateway string required The payment gateway to use. Currently supported: `stripe`. Example: stripe
     *
     * @response 200 {
     *   "status": true,
     *   "message": "PaymentIntent created successfully.",
     *   "data": {
     *     "client_secret": "pi_1Hxxxxxxxxxxxx_secret_xxxxxxxxx"
     *   }
     * }
     *
     * @response 200 scenario="Already Paid" {
     *   "status": true,
     *   "message": "Payment has already been processed for this order.",
     *   "data": null
     * }
     *
     * @response 400 scenario="Unsupported Gateway" {
     *   "status": false,
     *   "message": "Unsupported payment gateway",
     *   "data": null
     * }
     *
     * @response 404 scenario="Order Not Found" {
     *   "status": false,
     *   "message": "No query results for model [App\\Models\\Order] 999",
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
     * Verify Payment Status
     *
     * Verifies the current status of a payment with the chosen payment gateway and updates
     * the local database accordingly. This is useful for handling cases where webhooks might
     * have failed or been delayed.
     *
     * @group Payments
     *
     * @bodyParam payment_intent_id string required The Stripe Payment Intent ID to verify. Example: pi_1Hxxxxxxxxxxxx
     * @bodyParam order_id int required The ID of the order associated with this payment. Example: 123
     *
     * @response 200 {
     *   "status": true,
     *   "message": "Payment verified",
     *   "data": {
     *     "payment_status": "paid",
     *     "order_status": "confirmed"
     *   }
     * }
     *
     * @response 500 scenario="Stripe API Error" {
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

    private function handle(string $gateway)
    {
        return match ($gateway) {
            PaymentMethods::STRIPE => StripePayment::class,
            default => null,
        };
    }
}

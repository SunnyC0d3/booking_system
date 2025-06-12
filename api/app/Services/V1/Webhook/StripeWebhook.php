<?php

namespace App\Services\V1\Webhook;

use App\Constants\OrderStatuses;
use App\Constants\PaymentStatuses;
use App\Models\OrderStatus;
use App\Services\V1\Orders\Refunds\RefundHandlerInterface;
use App\Services\V1\Orders\Refunds\RefundProcessor;
use App\Services\V1\Orders\Refunds\StripeRefundGateway;
use Illuminate\Http\Request;
use App\Models\Payment as DB;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhook implements WebhookHandlerInterface
{
    use ApiResponses;

    private $secret;
    private $webhook_secret;

    private RefundHandlerInterface $refundProcessor;

    public function __construct()
    {
        $this->secret = config('services.stripe_secret');
        $this->webhook_secret = config('services.stripe_webhook_secret');
        Stripe::setApiKey($this->secret);

        $this->refundProcessor = new RefundProcessor(new StripeRefundGateway());
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhook_secret);

            Log::info('Stripe Webhook Received', [
                'type' => $event->type,
                'id' => $event->id,
                'livemode' => $event->livemode
            ]);

        } catch (UnexpectedValueException $e) {
            Log::error('Invalid Stripe payload', ['error' => $e->getMessage()]);
            return $this->error('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid Stripe signature', ['error' => $e->getMessage()]);
            return $this->error('Invalid Stripe signature', 400);
        }

        $intent = $event->data->object;

        Log::info('Processing webhook event', [
            'event_type' => $event->type,
            'payment_intent_id' => $intent->id,
            'status' => $intent->status ?? 'unknown'
        ]);

        $payment = DB::where('transaction_reference', $intent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'payment_intent_id' => $intent->id,
                'event_type' => $event->type
            ]);
            return $this->ok('Payment not found, but webhook processed.');
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                if ($payment->status !== PaymentStatuses::PAID) {
                    $order = $payment->order;

                    $payment->status = PaymentStatuses::PAID;
                    $payment->processed_at = now();
                    $payment->response_payload = json_encode($intent);
                    $payment->save();

                    $order->status_id = OrderStatus::where('name', OrderStatuses::CONFIRMED)->value('id');
                    $order->save();

                    Log::info('Payment marked as paid via webhook', [
                        'payment_id' => $payment->id,
                        'order_id' => $order->id
                    ]);
                }
                break;

            case 'payment_intent.payment_failed':
                if ($payment->status !== PaymentStatuses::FAILED) {
                    $order = $payment->order;

                    $payment->status = PaymentStatuses::FAILED;
                    $payment->processed_at = now();
                    $payment->response_payload = json_encode($intent);
                    $payment->save();

                    $order->status_id = OrderStatus::where('name', OrderStatuses::FAILED)->value('id');
                    $order->save();

                    Log::info('Payment marked as failed via webhook', [
                        'payment_id' => $payment->id,
                        'order_id' => $order->id
                    ]);
                }
                break;

            default:
                Log::info('Unhandled webhook event type', ['type' => $event->type]);
                break;
        }

        return $this->ok('Webhook processed successfully.');
    }
}

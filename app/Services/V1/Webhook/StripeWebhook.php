<?php

namespace App\Services\V1\Webhook;

use App\Constants\OrderStatuses;
use App\Constants\PaymentStatuses;
use App\Models\OrderStatus;
use App\Services\V1\Orders\Refunds\StripeRefund;
use Illuminate\Http\Request;
use App\Models\Payment as DB;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhook implements WebhookHandler
{
    use ApiResponses;

    private $secret;
    private $webhook_secret;

    private $stripeRefund;

    public function __construct(StripeRefund $stripeRefund)
    {
        $this->secret = config('services.stripe_secret');
        $this->webhook_secret = config('services.stripe_webhook_secret');
        Stripe::setApiKey($this->secret);

        $this->stripeRefund = $stripeRefund;
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $this->webhook_secret);
            Log::info('Stripe Webhook Event Type:', (array)$event);
        } catch (UnexpectedValueException $e) {
            Log::error('Invalid Stripe payload', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Invalid Stripe signature', ['error' => $e->getMessage()]);
            return $this->error($e->getMessage(), 400);
        }

        $intent = $event->data->object;
        $payment = DB::where('transaction_reference', $intent->id)->first();

        if ($payment) {
            $order = $payment->order;

            if ($event->type === 'payment_intent.succeeded') {
                $payment->status = PaymentStatuses::PAID;
                $order->status_id = OrderStatus::where('name', OrderStatuses::CONFIRMED)->value('id');
                $order->save();

                $payment->processed_at = now();
                $payment->response_payload = json_encode($intent);
                $payment->save();
            }

            if ($event->type === 'payment_intent.payment_failed') {
                $payment->status = PaymentStatuses::FAILED;
                $order->status_id = OrderStatus::where('name', OrderStatuses::FAILED)->value('id');
                $order->save();

                $payment->processed_at = now();
                $payment->response_payload = json_encode($intent);
                $payment->save();
            }

            if ($event->type === 'charge.refunded') {
                $this->stripeRefund->refund($request, $event->data->object->metadata->order_id, true);
            }
        }

        return $this->ok('Webhook update.');
    }
}

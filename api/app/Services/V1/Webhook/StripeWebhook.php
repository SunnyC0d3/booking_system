<?php

namespace App\Services\V1\Webhook;

use App\Constants\OrderStatuses;
use App\Constants\PaymentStatuses;
use App\Constants\ReturnStatuses;
use App\Models\OrderStatus;
use App\Models\OrderReturnStatus;
use App\Services\V1\Orders\Refunds\RefundHandlerInterface;
use App\Services\V1\Orders\Refunds\RefundProcessor;
use App\Services\V1\Orders\Refunds\StripeRefundGateway;
use Illuminate\Http\Request;
use App\Models\Payment as DB;
use App\Traits\V1\ApiResponses;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Charge;
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
        $this->refundProcessor->enableWebhook();
        $this->refundProcessor->skipGatewayProcessing();
        $this->refundProcessor->setRefundSource('stripe_webhook');
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

        $eventObject = $event->data->object;

        Log::info('Processing webhook event', [
            'event_type' => $event->type,
            'object_id' => $eventObject->id,
            'status' => $eventObject->status ?? 'unknown'
        ]);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentSucceeded($eventObject);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentFailed($eventObject);

            case 'charge.refunded':
                return $this->handleChargeRefunded($eventObject);

            case 'invoice.payment_succeeded':
                return $this->handleInvoicePaymentSucceeded($eventObject);

            case 'refund.created':
                return $this->handleRefundCreated($eventObject);

            case 'refund.updated':
                return $this->handleRefundUpdated($eventObject);

            case 'charge.refund.updated':
                return $this->handleChargeRefundUpdated($eventObject);

            default:
                Log::info('Unhandled webhook event type', ['type' => $event->type]);
                return $this->ok('Webhook processed successfully.');
        }
    }

    private function handleRefundCreated($refund)
    {
        Log::info('Processing refund.created webhook', [
            'refund_id' => $refund->id,
            'charge_id' => $refund->charge,
            'status' => $refund->status,
            'amount' => $refund->amount,
            'reason' => $refund->reason ?? 'none'
        ]);

        if ($refund->status === 'succeeded') {
            return $this->processSuccessfulRefund($refund);
        }

        return $this->ok('Refund creation webhook processed.');
    }

    private function handleChargeRefundUpdated($refund)
    {
        Log::info('Processing charge.refund.updated webhook', [
            'refund_id' => $refund->id,
            'charge_id' => $refund->charge,
            'status' => $refund->status,
            'amount' => $refund->amount
        ]);

        if ($refund->status === 'succeeded') {
            return $this->processSuccessfulRefund($refund);
        }

        return $this->ok('Charge refund update webhook processed.');
    }

    private function handleChargeRefunded($charge)
    {
        Log::info('Processing charge.refunded webhook', [
            'charge_id' => $charge->id,
            'payment_intent' => $charge->payment_intent,
            'amount_refunded' => $charge->amount_refunded,
            'refunded' => $charge->refunded
        ]);

        $payment = DB::where('transaction_reference', $charge->payment_intent)->first();

        if (!$payment) {
            Log::warning('Payment not found for refund webhook', [
                'payment_intent_id' => $charge->payment_intent,
                'charge_id' => $charge->id
            ]);
            return $this->ok('Payment not found, but webhook processed.');
        }

        return $this->ok('Charge refund webhook processed.');
    }

    private function processSuccessfulRefund($refund)
    {
        try {
            $charge = Charge::retrieve($refund->charge);
            $payment = DB::where('transaction_reference', $charge->payment_intent)->first();

            if (!$payment) {
                Log::warning('Payment not found for successful refund', [
                    'refund_id' => $refund->id,
                    'charge_id' => $refund->charge,
                    'payment_intent' => $charge->payment_intent
                ]);
                return $this->ok('Payment not found, but webhook processed.');
            }

            $order = $payment->order;

            Log::info('Processing successful refund', [
                'order_id' => $order->id,
                'refund_amount' => $refund->amount / 100,
                'refund_id' => $refund->id
            ]);

            $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');

            $approvedReturns = $order->orderItems()
                ->whereHas('orderReturn', function ($q) use ($approvedStatusId) {
                    $q->where('order_return_status_id', $approvedStatusId)
                        ->whereDoesntHave('orderRefund');
                })
                ->get();

            if ($approvedReturns->isNotEmpty()) {
                $this->refundProcessor->setRefundNotes('Refund processed via Stripe (internal)');
                $result = $this->refundProcessor->refund(request(), $order->id);

                Log::info('Internal refund processing completed via webhook', [
                    'order_id' => $order->id,
                    'approved_returns_count' => $approvedReturns->count(),
                    'refund_id' => $refund->id
                ]);
            } else {
                $notes = 'Manual refund processed via Stripe Dashboard (Refund ID: ' . $refund->id . ')';
                $success = $this->refundProcessor->createManualRefund(
                    $order->id,
                    $refund->amount,
                    $notes,
                    'stripe_dashboard'
                );

                if (!$success) {
                    Log::error('Failed to create manual refund record - no approved returns found', [
                        'order_id' => $order->id,
                        'refund_id' => $refund->id,
                        'note' => 'Manual refunds require approved returns due to database constraints'
                    ]);
                } else {
                    Log::info('Manual Stripe refund processed', [
                        'order_id' => $order->id,
                        'refund_amount' => $refund->amount / 100,
                        'refund_id' => $refund->id,
                        'source' => 'stripe_dashboard'
                    ]);
                }
            }

            return $this->ok('Successful refund processed.');

        } catch (\Exception $e) {
            Log::error('Failed to process successful refund', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to process successful refund', 500);
        }
    }

    private function handleRefundUpdated($refund)
    {
        Log::info('Processing refund.updated webhook', [
            'refund_id' => $refund->id,
            'charge_id' => $refund->charge,
            'status' => $refund->status,
            'amount' => $refund->amount,
            'reason' => $refund->reason ?? 'none'
        ]);

        if ($refund->status === 'succeeded') {
            return $this->processSuccessfulRefund($refund);
        }

        if ($refund->status === 'canceled') {
            return $this->handleRefundCanceled($refund);
        }

        if ($refund->status === 'failed') {
            return $this->handleRefundFailed($refund);
        }

        Log::info('Refund status updated but no action needed', [
            'refund_id' => $refund->id,
            'status' => $refund->status
        ]);

        return $this->ok('Refund update webhook processed.');
    }

    private function handleRefundCanceled($refund)
    {
        Log::info('Processing refund cancellation', [
            'refund_id' => $refund->id,
            'charge_id' => $refund->charge,
            'amount' => $refund->amount / 100
        ]);

        try {
            $charge = Charge::retrieve($refund->charge);
            $payment = DB::where('transaction_reference', $charge->payment_intent)->first();

            if (!$payment) {
                Log::warning('Payment not found for canceled refund', [
                    'refund_id' => $refund->id,
                    'charge_id' => $refund->charge
                ]);
                return $this->ok('Payment not found, but webhook processed.');
            }

            $order = $payment->order;

            $success = $this->refundProcessor->cancelRefund($order->id, $refund->amount);

            if ($success) {
                Log::info('Refund cancellation processed successfully', [
                    'order_id' => $order->id,
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount / 100
                ]);
            } else {
                Log::warning('Refund cancellation could not be processed', [
                    'order_id' => $order->id,
                    'refund_id' => $refund->id
                ]);
            }

            return $this->ok('Refund cancellation processed successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to process refund cancellation', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to process refund cancellation', 500);
        }
    }

    private function handleRefundFailed($refund)
    {
        Log::info('Processing failed refund', [
            'refund_id' => $refund->id,
            'charge_id' => $refund->charge,
            'failure_reason' => $refund->failure_reason ?? 'unknown'
        ]);

        try {
            $charge = Charge::retrieve($refund->charge);
            $payment = DB::where('transaction_reference', $charge->payment_intent)->first();

            if (!$payment) {
                Log::warning('Payment not found for failed refund', [
                    'refund_id' => $refund->id,
                    'charge_id' => $refund->charge
                ]);
                return $this->ok('Payment not found, but webhook processed.');
            }

            $order = $payment->order;
            $failureReason = 'Refund failed in Stripe: ' . ($refund->failure_reason ?? 'unknown reason');

            $success = $this->refundProcessor->failRefund($order->id, $failureReason);

            if ($success) {
                Log::info('Failed refund processed successfully', [
                    'order_id' => $order->id,
                    'refund_id' => $refund->id,
                    'failure_reason' => $refund->failure_reason ?? 'unknown'
                ]);
            } else {
                Log::warning('No pending refunds found to mark as failed', [
                    'order_id' => $order->id,
                    'refund_id' => $refund->id
                ]);
            }

            return $this->ok('Failed refund processed successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to process failed refund', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to process failed refund', 500);
        }
    }

    private function handlePaymentSucceeded($intent)
    {
        $payment = DB::where('transaction_reference', $intent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'payment_intent_id' => $intent->id,
                'event_type' => 'payment_intent.succeeded'
            ]);
            return $this->ok('Payment not found, but webhook processed.');
        }

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

        return $this->ok('Payment success webhook processed.');
    }

    private function handlePaymentFailed($intent)
    {
        $payment = DB::where('transaction_reference', $intent->id)->first();

        if (!$payment) {
            Log::warning('Payment not found for webhook', [
                'payment_intent_id' => $intent->id,
                'event_type' => 'payment_intent.payment_failed'
            ]);
            return $this->ok('Payment not found, but webhook processed.');
        }

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

        return $this->ok('Payment failure webhook processed.');
    }

    private function handleInvoicePaymentSucceeded($invoice)
    {
        Log::info('Invoice payment succeeded', [
            'invoice_id' => $invoice->id,
            'customer' => $invoice->customer,
            'amount_paid' => $invoice->amount_paid
        ]);

        return $this->ok('Invoice payment webhook processed.');
    }
}

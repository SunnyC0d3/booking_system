<?php

namespace App\Services\V1\Webhook;

use App\Constants\OrderStatuses;
use App\Constants\PaymentStatuses;
use App\Constants\RefundStatuses;
use App\Constants\ReturnStatuses;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\OrderRefundStatus;
use App\Models\OrderReturn;
use App\Models\OrderReturnStatus;
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

            case 'charge.refunded.updated':
                return $this->handleChargeRefunded($eventObject, $event);

            case 'invoice.payment_succeeded':
                return $this->handleInvoicePaymentSucceeded($eventObject);

            case 'refund.updated':
                return $this->handleRefundUpdated($eventObject);

            default:
                Log::info('Unhandled webhook event type', ['type' => $event->type]);
                return $this->ok('Webhook processed successfully.');
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

    private function handleChargeRefunded($charge, $event)
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

        $order = $payment->order;

        $isFullRefund = $charge->refunded && $charge->amount_refunded >= $charge->amount;

        Log::info('Refund webhook analysis', [
            'order_id' => $order->id,
            'is_full_refund' => $isFullRefund,
            'charge_amount' => $charge->amount,
            'amount_refunded' => $charge->amount_refunded,
            'total_refunded' => $charge->refunded
        ]);

        $approvedReturns = $order->orderItems()
            ->whereHas('orderReturn', function ($q) {
                $q->where('status', ReturnStatuses::APPROVED)
                    ->whereDoesntHave('orderRefund');
            })
            ->get();

        if ($approvedReturns->isNotEmpty()) {
            try {
                $this->refundProcessor->refund(request(), $order->id);

                Log::info('Internal refund processing completed via webhook', [
                    'order_id' => $order->id,
                    'approved_returns_count' => $approvedReturns->count(),
                    'refund_type' => $isFullRefund ? 'full' : 'partial'
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to process internal refund records via webhook', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        } else {
            $newPaymentStatus = $isFullRefund
                ? PaymentStatuses::REFUNDED
                : PaymentStatuses::PARTIALLY_REFUNDED;

            $payment->update(['status' => $newPaymentStatus]);

            $newOrderStatusName = $isFullRefund
                ? OrderStatuses::REFUNDED
                : OrderStatuses::PARTIALLY_REFUNDED;

            $newOrderStatusId = OrderStatus::where('name', $newOrderStatusName)->value('id');
            $order->update(['status_id' => $newOrderStatusId]);

            Log::info('Manual Stripe refund detected - updated statuses manually', [
                'order_id' => $order->id,
                'refund_amount' => $charge->amount_refunded / 100,
                'source' => 'stripe_dashboard_manual',
                'payment_status' => $newPaymentStatus,
                'order_status' => $newOrderStatusName
            ]);

            $this->createManualRefundRecord($order, $charge->amount_refunded, $isFullRefund);
        }

        return $this->ok('Charge refund webhook processed.');
    }

    private function createManualRefundRecord(Order $order, int $amountRefunded, bool $isFullRefund): void
    {
        try {
            OrderRefund::create([
                'order_id' => $order->id,
                'amount' => $amountRefunded,
                'order_refund_status_id' => OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id'),
                'processed_at' => now(),
                'notes' => 'Manual refund processed via Stripe Dashboard',
                'source' => 'stripe_webhook',
                'is_manual' => true,
            ]);

            Log::info('Manual refund record created', [
                'order_id' => $order->id,
                'amount' => $amountRefunded / 100,
                'type' => $isFullRefund ? 'full' : 'partial'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create manual refund record', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
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

    private function handleRefundUpdated($refund)
    {
        Log::info('Processing refund.updated webhook', [
            'refund_id' => $refund->id,
            'charge_id' => $refund->charge,
            'status' => $refund->status,
            'amount' => $refund->amount,
            'reason' => $refund->reason ?? 'none'
        ]);

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
                    'charge_id' => $refund->charge,
                    'payment_intent' => $charge->payment_intent
                ]);
                return $this->ok('Payment not found, but webhook processed.');
            }

            $order = $payment->order;

            $chargeRefunds = Charge::retrieve($charge->id)->refunds;
            $remainingRefundAmount = 0;

            foreach ($chargeRefunds->data as $chargeRefund) {
                if ($chargeRefund->status === 'succeeded') {
                    $remainingRefundAmount += $chargeRefund->amount;
                }
            }

            if ($remainingRefundAmount === 0) {
                $payment->update(['status' => PaymentStatuses::PAID]);

                $confirmedStatusId = OrderStatus::where('name', OrderStatuses::CONFIRMED)->value('id');
                $order->update(['status_id' => $confirmedStatusId]);

                Log::info('Refund canceled - restored to paid status', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id
                ]);
            } else {
                $isFullRefund = $remainingRefundAmount >= $charge->amount;

                $newPaymentStatus = $isFullRefund
                    ? PaymentStatuses::REFUNDED
                    : PaymentStatuses::PARTIALLY_REFUNDED;

                $payment->update(['status' => $newPaymentStatus]);

                $newOrderStatusName = $isFullRefund
                    ? OrderStatuses::REFUNDED
                    : OrderStatuses::PARTIALLY_REFUNDED;

                $newOrderStatusId = OrderStatus::where('name', $newOrderStatusName)->value('id');
                $order->update(['status_id' => $newOrderStatusId]);

                Log::info('Refund canceled - updated to partial refund status', [
                    'order_id' => $order->id,
                    'remaining_refund_amount' => $remainingRefundAmount / 100,
                    'new_status' => $newPaymentStatus
                ]);
            }

            $this->handleInternalRefundCancellation($order, $refund);

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

            $failedStatusId = OrderRefundStatus::where('name', RefundStatuses::FAILED)->value('id');

            OrderRefund::where('order_id', $order->id)
                ->where('order_refund_status_id', OrderRefundStatus::where('name', RefundStatuses::PENDING)->value('id'))
                ->update([
                    'order_refund_status_id' => $failedStatusId,
                    'notes' => 'Refund failed in Stripe: ' . ($refund->failure_reason ?? 'unknown reason')
                ]);

            Log::info('Marked internal refund records as failed', [
                'order_id' => $order->id,
                'refund_id' => $refund->id,
                'failure_reason' => $refund->failure_reason ?? 'unknown'
            ]);

            return $this->ok('Failed refund processed successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to process failed refund', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to process failed refund', 500);
        }
    }

    private function handleInternalRefundCancellation(Order $order, $stripeRefund): void
    {
        try {
            $refundAmount = $stripeRefund->amount;

            $matchingRefunds = OrderRefund::where('order_id', $order->id)
                ->where('amount', $refundAmount)
                ->where('created_at', '>=', now()->subHours(24))
                ->get();

            if ($matchingRefunds->isNotEmpty()) {
                $canceledStatusId = OrderRefundStatus::where('name', RefundStatuses::CANCELLED)->value('id');

                $refundToCancel = $matchingRefunds->sortByDesc('created_at')->first();
                $refundToCancel->update([
                    'order_refund_status_id' => $canceledStatusId,
                    'notes' => 'Refund canceled in Stripe dashboard'
                ]);

                if ($refundToCancel->order_return_id) {
                    $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');

                    OrderReturn::where('id', $refundToCancel->order_return_id)
                        ->update(['order_return_status_id' => $approvedStatusId]);
                }

                Log::info('Internal refund record canceled', [
                    'order_id' => $order->id,
                    'refund_record_id' => $refundToCancel->id,
                    'amount' => $refundAmount / 100
                ]);
            } else {
                Log::info('No matching internal refund record found for cancellation', [
                    'order_id' => $order->id,
                    'stripe_refund_amount' => $refundAmount / 100
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to handle internal refund cancellation', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

<?php

namespace App\Services\V1\Refunds;

use App\Constants\PaymentStatuses;
use App\Constants\RefundStatuses;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRefund;
use App\Models\OrderRefundStatus;
use Illuminate\Support\Facades\Log;
use Stripe\Refund as StripeRefund;
use Stripe\Stripe;

class ManualStripeRefund implements PaymentGatewayRefundInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe_secret'));
    }

    public function refund(Order $order, ?OrderItem $orderItem = null): bool
    {
        $order->load('payments');

        $payment = $order->payments->whereIn('status', [
            PaymentStatuses::PAID,
            PaymentStatuses::PARTIALLY_REFUNDED,
        ])->first();

        if (!$payment) {
            Log::warning("No valid payment found for refund processing", [
                'order_id' => $order->id,
                'payments_count' => $order->payments->count(),
                'payment_statuses' => $order->payments->pluck('status')->toArray(),
                'required_statuses' => [PaymentStatuses::PAID, PaymentStatuses::PARTIALLY_REFUNDED],
            ]);
            return false;
        }

        Log::info('Processing Stripe refund', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'payment_intent' => $payment->transaction_reference,
            'payment_amount_pennies' => $payment->getAmountInPennies(),
            'payment_amount_pounds' => $payment->getAmountInPounds(),
            'refund_type' => $orderItem ? 'single_item_return' : 'bulk_approved_returns',
            'order_item_id' => $orderItem?->id,
            'return_id' => $orderItem?->orderReturn?->id
        ]);

        try {
            if ($orderItem) {
                return $this->processSingleItemRefund($order, $orderItem, $payment);
            }

            Log::warning('ManualStripeRefund called without orderItem - should use BulkStripeRefund instead', [
                'order_id' => $order->id
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Stripe refund failed', [
                'order_id' => $order->id,
                'order_item_id' => $orderItem?->id,
                'return_id' => $orderItem?->orderReturn?->id,
                'payment_intent' => $payment->transaction_reference,
                'payment_status' => $payment->status,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            return false;
        }
    }

    private function processSingleItemRefund(Order $order, OrderItem $orderItem, $payment): bool
    {
        if (!$orderItem->orderReturn || !$orderItem->orderReturn->isApproved()) {
            Log::warning("Order item does not have an approved return", [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'has_return' => (bool) $orderItem->orderReturn,
                'return_status' => $orderItem->orderReturn?->status ?? 'no_return'
            ]);
            return false;
        }

        $refundAmountInPennies = $orderItem->refundAmount();

        if ($refundAmountInPennies <= 0) {
            Log::warning("Invalid refund amount for single item", [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'return_id' => $orderItem->orderReturn->id,
                'calculated_amount_pennies' => $refundAmountInPennies
            ]);
            return false;
        }

        $remainingRefundableInPennies = $this->calculateRemainingRefundableAmount($order, $payment);

        if ($refundAmountInPennies > $remainingRefundableInPennies) {
            Log::warning("Refund amount exceeds remaining refundable amount", [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'return_id' => $orderItem->orderReturn->id,
                'requested_amount_pennies' => $refundAmountInPennies,
                'requested_amount_pounds' => $refundAmountInPennies / 100,
                'remaining_refundable_pennies' => $remainingRefundableInPennies,
                'remaining_refundable_pounds' => $remainingRefundableInPennies / 100,
                'payment_status' => $payment->status
            ]);
            return false;
        }

        $stripeRefund = StripeRefund::create([
            'payment_intent' => $payment->transaction_reference,
            'amount' => $refundAmountInPennies,
            'metadata' => [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'return_id' => $orderItem->orderReturn->id,
                'refund_type' => 'single_item_return',
                'processed_via' => 'manual_gateway',
                'payment_status' => $payment->status
            ]
        ]);

        Log::info('Single item Stripe refund created', [
            'order_id' => $order->id,
            'order_item_id' => $orderItem->id,
            'return_id' => $orderItem->orderReturn->id,
            'stripe_refund_id' => $stripeRefund->id,
            'amount_pennies' => $refundAmountInPennies,
            'amount_pounds' => $refundAmountInPennies / 100,
            'status' => $stripeRefund->status,
            'remaining_after_refund_pennies' => ($remainingRefundableInPennies - $refundAmountInPennies),
            'remaining_after_refund_pounds' => ($remainingRefundableInPennies - $refundAmountInPennies) / 100
        ]);

        return true;
    }

    private function calculateRemainingRefundableAmount(Order $order, $payment): int
    {
        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

        $totalAlreadyRefundedInPennies = OrderRefund::whereHas('orderReturn', function ($q) use ($order) {
            $q->whereHas('orderItem', function ($sq) use ($order) {
                $sq->where('order_id', $order->id);
            });
        })
            ->where('order_refund_status_id', $refundedStatusId)
            ->sum('amount');

        $paymentAmountInPennies = $payment->getAmountInPennies();
        $remainingRefundableInPennies = $paymentAmountInPennies - $totalAlreadyRefundedInPennies;

        Log::info('Calculated remaining refundable amount', [
            'order_id' => $order->id,
            'payment_amount_pennies' => $paymentAmountInPennies,
            'payment_amount_pounds' => $paymentAmountInPennies / 100,
            'total_already_refunded_pennies' => $totalAlreadyRefundedInPennies,
            'total_already_refunded_pounds' => $totalAlreadyRefundedInPennies / 100,
            'remaining_refundable_pennies' => $remainingRefundableInPennies,
            'remaining_refundable_pounds' => $remainingRefundableInPennies / 100,
            'payment_status' => $payment->status
        ]);

        return max(0, $remainingRefundableInPennies);
    }
}

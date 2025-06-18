<?php

namespace App\Services\V1\Orders\Refunds;

use App\Constants\RefundStatuses;
use App\Models\Order;
use App\Models\OrderItem;
use App\Constants\PaymentStatuses;
use App\Models\OrderRefund;
use App\Models\OrderRefundStatus;
use Stripe\Refund as StripeRefund;
use Stripe\Stripe;
use Illuminate\Support\Facades\Log;

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
                'available_payments' => $order->payments->toArray(),
                'required_statuses' => [PaymentStatuses::PAID, PaymentStatuses::PARTIALLY_REFUNDED],
            ]);
            return false;
        }

        Log::info('Processing Stripe refund', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'payment_status' => $payment->status,
            'payment_intent' => $payment->transaction_reference,
            'refund_type' => $orderItem ? 'single_item_return' : 'bulk_approved_returns',
            'order_item_id' => $orderItem?->id,
            'return_id' => $orderItem?->orderReturn?->id
        ]);

        try {
            if ($orderItem) {
                return $this->processSingleItemRefund($order, $orderItem, $payment);
            }

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
        // Validate that this order item has an approved return
        if (!$orderItem->orderReturn || !$orderItem->orderReturn->isApproved()) {
            Log::warning("Order item does not have an approved return", [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'has_return' => (bool) $orderItem->orderReturn,
                'return_status' => $orderItem->orderReturn?->status ?? 'no_return'
            ]);
            return false;
        }

        $refundAmount = $orderItem->refundAmount();

        if ($refundAmount <= 0) {
            Log::warning("Invalid refund amount for single item", [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'return_id' => $orderItem->orderReturn->id,
                'calculated_amount' => $refundAmount
            ]);
            return false;
        }

        $remainingRefundable = $this->calculateRemainingRefundableAmount($order, $payment);
        if ($refundAmount > $remainingRefundable) {
            Log::warning("Refund amount exceeds remaining refundable amount", [
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'return_id' => $orderItem->orderReturn->id,
                'requested_amount' => $refundAmount / 100,
                'remaining_refundable' => $remainingRefundable / 100,
                'payment_status' => $payment->status
            ]);
            return false;
        }

        $stripeRefund = StripeRefund::create([
            'payment_intent' => $payment->transaction_reference,
            'amount' => $refundAmount,
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
            'amount' => $refundAmount / 100,
            'status' => $stripeRefund->status,
            'remaining_after_refund' => ($remainingRefundable - $refundAmount) / 100
        ]);

        return true;
    }

    private function calculateRemainingRefundableAmount(Order $order, $payment): int
    {
        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

        $totalAlreadyRefunded = OrderRefund::whereHas('orderReturn', function ($q) use ($order) {
            $q->whereHas('orderItem', function ($sq) use ($order) {
                $sq->where('order_id', $order->id);
            });
        })
            ->where('order_refund_status_id', $refundedStatusId)
            ->sum('amount');

        $remainingRefundable = $payment->amount - $totalAlreadyRefunded;

        Log::info('Calculated remaining refundable amount', [
            'order_id' => $order->id,
            'payment_amount' => $payment->amount / 100,
            'total_already_refunded' => $totalAlreadyRefunded / 100,
            'remaining_refundable' => $remainingRefundable / 100,
            'payment_status' => $payment->status
        ]);

        return max(0, $remainingRefundable);
    }
}

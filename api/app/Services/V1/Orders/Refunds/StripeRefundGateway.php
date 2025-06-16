<?php

namespace App\Services\V1\Orders\Refunds;

use App\Models\Order;
use App\Models\OrderItem;
use App\Constants\PaymentStatuses;
use Stripe\Refund as StripeRefund;
use Stripe\Stripe;
use Illuminate\Support\Facades\Log;

class StripeRefundGateway implements PaymentGatewayRefundInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe_secret'));
    }

    public function refund(Order $order, ?OrderItem $orderItem = null): bool
    {
        $payment = $order->payments->firstWhere('status', PaymentStatuses::PAID);

        if (!$payment) {
            Log::warning("No valid payment found for order ID: {$order->id}");
            return false;
        }

        if ($orderItem) {
            $refundAmount = $orderItem->refundAmount();

            StripeRefund::create([
                'payment_intent' => $payment->transaction_reference,
                'amount' => $refundAmount,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                    'refund_type' => 'single_item'
                ]
            ]);
        } else {
            $approvedItems = $order->orderItems
                ->filter(fn($item) => $item->orderReturn && $item->orderReturn->isApproved());

            $totalRefundAmount = $approvedItems->sum(fn($item) => $item->refundAmount());

            if ($totalRefundAmount <= 0) {
                Log::warning("No refund amount calculated for bulk refund", ['order_id' => $order->id]);
                return false;
            }

            StripeRefund::create([
                'payment_intent' => $payment->transaction_reference,
                'amount' => $totalRefundAmount,
                'metadata' => [
                    'order_id' => $order->id,
                    'refund_type' => 'bulk',
                    'items_count' => $approvedItems->count()
                ]
            ]);
        }

        return true;
    }
}

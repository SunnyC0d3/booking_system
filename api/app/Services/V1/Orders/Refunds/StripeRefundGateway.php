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

        try {
            if ($orderItem) {
                StripeRefund::create([
                    'payment_intent' => $payment->transaction_reference,
                    'amount' => $orderItem->refundAmount(),
                ]);
            } else {
                $totalRefundAmount = $order->orderItems
                    ->filter(fn ($item) => $item->orderReturn && $item->orderReturn->isApproved())
                    ->sum(fn ($item) => $item->refundAmount());

                StripeRefund::create([
                    'payment_intent' => $payment->transaction_reference,
                    'amount' => $totalRefundAmount,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Stripe refund failed: " . $e->getMessage());
            return false;
        }
    }
}

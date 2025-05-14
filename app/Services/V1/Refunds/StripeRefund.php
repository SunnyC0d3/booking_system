<?php

namespace App\Services\V1\Refunds;

use App\Constants\OrderStatuses;
use App\Constants\PaymentStatuses;
use App\Constants\RefundStatuses;
use App\Models\OrderRefund;
use App\Models\OrderReturn;
use App\Models\OrderRefundStatus;
use App\Models\OrderStatus;
use App\Traits\V1\ApiResponses;
use Stripe\Refund;
use Stripe\Stripe;

class StripeRefund implements RefundHandler
{
    use ApiResponses;

    protected $secret;

    public function __construct()
    {
        $this->secret = config('services.stripe_secret');
        Stripe::setApiKey($this->secret);
    }

    public function refund(int $orderReturnId)
    {
        $orderReturn = OrderReturn::with(['orderItem.order.user'])->findOrFail($orderReturnId);
        $orderItem = $orderReturn->orderItem;
        $order = $orderReturn->order;

        $payment = $order->payments->where('status', PaymentStatuses::PAID)->firstOrFail();

        Refund::create([
            'payment_intent' => $payment->transaction_reference,
            'amount' => $orderItem->refundAmount(),
        ]);

        $refundStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

        OrderRefund::create([
            'order_return_id' => $orderReturn->id,
            'amount' => $orderItem->refundAmount(),
            'order_refund_status_id' => $refundStatusId,
            'processed_at' => now(),
        ]);

        $refundedStatusId = OrderStatus::where('name', OrderStatuses::REFUNDED)->value('id');
        $order->status_id = $refundedStatusId;
        $order->save();

        $payment->status = PaymentStatuses::REFUNDED;
        $payment->save();

        return $this->ok('Refund processed successfully.');
    }
}

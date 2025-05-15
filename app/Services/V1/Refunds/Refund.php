<?php

namespace App\Services\V1\Refunds;

use App\Models\OrderReturnStatus;
use App\Constants\OrderStatuses;
use App\Constants\PaymentStatuses;
use App\Constants\RefundStatuses;
use App\Constants\ReturnStatuses;
use App\Models\OrderRefund;
use App\Models\OrderReturn;
use App\Models\OrderRefundStatus;
use App\Models\OrderStatus;
use App\Traits\V1\ApiResponses;

class Refund
{
    use ApiResponses;

    protected $orderReturn;
    protected $orderItem;

    protected $order;

    public function __construct() {}

    protected function getOrders(int $orderReturnId) {
        $this->orderReturn = OrderReturn::with(['orderItem.order.user'])->findOrFail($orderReturnId);
        $this->orderItem = $this->orderReturn->orderItem;
        $this->order = $this->orderReturn->order;
    }

    protected function setState() {
        $refundStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

        OrderRefund::create([
            'order_return_id' => $this->orderReturn->id,
            'amount' => $this->orderItem->refundAmount(),
            'order_refund_status_id' => $refundStatusId,
            'processed_at' => now(),
        ]);

        $refundedStatusId = OrderStatus::where('name', OrderStatuses::REFUNDED)->value('id');
        $this->order->status_id = $refundedStatusId;
        $this->order->save();

        $this->payment->status = PaymentStatuses::REFUNDED;
        $this->payment->save();

        $this->orderReturn->order_return_status_id = OrderReturnStatus::where('name', ReturnStatuses::COMPLETED)->value('id');
        $this->orderReturn->save();
    }
}

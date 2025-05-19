<?php

namespace App\Services\V1\Orders\Refunds;

use App\Constants\OrderStatuses;
use App\Constants\PaymentStatuses;
use App\Constants\RefundStatuses;
use App\Constants\ReturnStatuses;
use App\Models\OrderRefund;
use App\Models\OrderRefundStatus;
use App\Models\OrderReturn;
use App\Models\OrderReturnStatus;
use App\Models\OrderStatus;
use App\Traits\V1\ApiResponses;
use \Exception;

class Refund
{
    use ApiResponses;

    protected $orderReturn;
    protected $orderItem;

    protected $order;

    protected $payment;

    protected $webhookEnabled;

    public function __construct()
    {
        $this->webhookEnabled = false;
    }

    protected function getOrders(int $orderReturnId)
    {
        $this->orderReturn = OrderReturn::with(['orderItem.order.user'])->findOrFail($orderReturnId);
        $this->orderItem = $this->orderReturn->orderItem;
        $this->order = $this->orderItem->order;

        $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');

        if ($this->webhookEnabled) {
            $this->orderReturn->order_return_status_id = $approvedStatusId;
            $this->orderReturn->save();
        }

        if ($this->orderReturn->order_return_status_id !== $approvedStatusId) {
            throw new Exception('This return has not been approved for refund.', 400);
        }
    }

    protected function setState()
    {
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

    protected function refundMarkedAsFailed(string $reason)
    {
        $failedStatusId = OrderRefundStatus::where('name', RefundStatuses::FAILED)->value('id');

        OrderRefund::create([
            'order_return_id' => $this->orderReturn->id,
            'amount' => $this->orderItem->refundAmount(),
            'order_refund_status_id' => $failedStatusId,
            'processed_at' => now(),
            'notes' => $reason,
        ]);
    }

    public function enableWebhook()
    {
        $this->webhookEnabled = true;
    }

    public function disableWebhook()
    {
        $this->webhookEnabled = false;
    }
}

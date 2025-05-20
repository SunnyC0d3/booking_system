<?php

namespace App\Services\V1\Orders\Refunds;

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
use App\Traits\V1\ApiResponses;
use \Exception;

class Refund
{
    use ApiResponses;

    protected $orderReturn;

    protected $orderItems;
    protected $orderItem;

    protected $order;

    protected $payment;

    protected $webhookEnabled;

    protected $approvedCount = 0;

    public function __construct()
    {
        $this->webhookEnabled = false;
    }

    protected function getOrders(int $id)
    {
        if(!$this->webhookEnabled) {
            $this->orderReturn = OrderReturn::with(['orderItem.order.user'])->findOrFail($id);
            $this->orderItem = $this->orderReturn->orderItem;
            $this->order = $this->orderItem->order;

            $this->approvedCount = !$this->orderReturn->isApproved() && $this->orderReturn->orderReturn ? 0 : 1;

            if ($this->approvedCount < 1) {
                throw new Exception('This return has not been approved for refund.', 400);
            }
        } else {
            $this->order = Order::with(['orderItems.orderReturn'])->findOrFail($id);
            $this->orderItems = $this->order->orderItems;

            foreach($this->orderItems as $orderItem) {
                if ($orderItem->orderReturn && $orderItem->orderReturn->isApproved()) {
                    $this->approvedCount++;
                }
            }

            if($this->approvedCount < 1) {
                throw new Exception('One or more items have not been approved for refund.', 400);
            }
        }
    }

    protected function setState()
    {
        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

        if(!empty($this->orderItem)) {
            OrderRefund::create([
                'order_return_id' => $this->orderReturn->id,
                'amount' => $this->orderItem->refundAmount(),
                'order_refund_status_id' => $refundedStatusId,
                'processed_at' => now(),
            ]);
        } else if(!empty($this->orderItems)) {
            foreach($this->orderItems as $orderItem) {
                OrderRefund::create([
                    'order_return_id' => $orderItem->id,
                    'amount' => $orderItem->refundAmount(),
                    'order_refund_status_id' => $refundedStatusId,
                    'processed_at' => now(),
                ]);
            }
        }

        $refundedStatusId = $this->approvedCount === count($this->orderItems) && !empty($this->orderItems) ? OrderStatus::where('name', OrderStatuses::REFUNDED)->value('id') : OrderStatus::where('name', OrderStatuses::PARTIALLY_REFUNDED)->value('id');
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

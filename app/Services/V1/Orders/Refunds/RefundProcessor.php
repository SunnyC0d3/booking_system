<?php

namespace App\Services\V1\Orders\Refunds;

use App\Constants\PaymentStatuses;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Constants\OrderStatuses;
use App\Constants\RefundStatuses;
use App\Constants\ReturnStatuses;
use App\Models\OrderRefund;
use App\Models\OrderRefundStatus;
use App\Models\OrderReturnStatus;
use App\Models\OrderStatus;

class RefundProcessor implements RefundHandlerInterface
{
    use ApiResponses;

    protected ?OrderReturn $orderReturn = null;
    protected $orderItems;
    protected $orderItem;
    protected ?Order $order = null;
    protected $payment;
    protected bool $webhookEnabled = false;
    protected int $approvedCount = 0;

    protected PaymentGatewayRefundInterface $gateway;

    public function __construct(PaymentGatewayRefundInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    public function enableWebhook(): void
    {
        $this->webhookEnabled = true;
    }

    public function disableWebhook(): void
    {
        $this->webhookEnabled = false;
    }

    protected function getOrderReturnWithRelations(int $id): void
    {
        $this->orderReturn = OrderReturn::with(['orderItem.order.user'])->findOrFail($id);
        $this->orderItem = $this->orderReturn->orderItem;
        $this->order = $this->orderItem->order;
    }

    protected function getOrderWithItems(int $id): void
    {
        $this->order = Order::with(['orderItems.orderReturn'])->findOrFail($id);
        $this->orderItems = $this->order->orderItems;
    }

    protected function validateApprovalStatus(): void
    {
        if (!$this->webhookEnabled) {
            $this->approvedCount = ($this->orderReturn && $this->orderReturn->isApproved()) ? 1 : 0;

            if ($this->approvedCount < 1) {
                throw new Exception('This return has not been approved for refund.', 400);
            }
        } else {
            $this->approvedCount = 0;

            foreach ($this->orderItems as $item) {
                if ($item->orderReturn && $item->orderReturn->isApproved()) {
                    $this->approvedCount++;
                }
            }

            if ($this->approvedCount < 1) {
                throw new Exception('One or more items have not been approved for refund.', 400);
            }
        }
    }

    protected function createRefundRecords(): void
    {
        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

        if (!empty($this->orderItem)) {
            OrderRefund::create([
                'order_return_id' => $this->orderReturn->id,
                'amount' => $this->orderItem->refundAmount(),
                'order_refund_status_id' => $refundedStatusId,
                'processed_at' => now(),
            ]);
        } elseif (!empty($this->orderItems)) {
            foreach ($this->orderItems as $item) {
                if ($item->orderReturn && $item->orderReturn->isApproved()) {
                    OrderRefund::create([
                        'order_return_id' => $item->orderReturn->id,
                        'amount' => $item->refundAmount(),
                        'order_refund_status_id' => $refundedStatusId,
                        'processed_at' => now(),
                    ]);
                }
            }
        }
    }

    protected function updateOrderAndPaymentStatus(): void
    {
        $refundedStatusId = ($this->approvedCount === count($this->orderItems) && !empty($this->orderItems))
            ? OrderStatus::where('name', OrderStatuses::REFUNDED)->value('id')
            : OrderStatus::where('name', OrderStatuses::PARTIALLY_REFUNDED)->value('id');

        $this->order->update(['status_id' => $refundedStatusId]);
        $this->payment->update(['status' => PaymentStatuses::REFUNDED]);
    }

    protected function markReturnAsCompleted(): void
    {
        $completedStatusId = OrderReturnStatus::where('name', ReturnStatuses::COMPLETED)->value('id');
        $this->orderReturn->update(['order_return_status_id' => $completedStatusId]);
    }

    protected function markRefundAsFailed(string $reason): void
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

    protected function initializeRefundContext(int $id): void
    {
        if (!$this->webhookEnabled) {
            $this->getOrderReturnWithRelations($id);
        } else {
            $this->getOrderWithItems($id);
        }

        $this->validateApprovalStatus();
    }

    protected function finalizeRefund(): void
    {
        $this->createRefundRecords();
        $this->updateOrderAndPaymentStatus();
        $this->markReturnAsCompleted();
    }

    public function refund(Request $request, int $id)
    {
        if (!$this->webhookEnabled && !$this->hasPermission($request)) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $this->initializeRefundContext($id);

            if(!$this->webhookEnabled) {
                $refundSuccessful = $this->gateway->refund($this->order, $this->orderItem);

                if (!$refundSuccessful) {
                    $this->markRefundAsFailed('Refund failed via Stripe. Please check Stripe.');
                    return $this->error('Refund failed via Stripe. Please try again later.', 422);
                }
            }

            $this->finalizeRefund();

            return $this->ok('Refund processed successfully.');
        } catch (Exception $e) {
            Log::error('Refund Error: ' . $e->getMessage());
            return $this->error('An error occurred while processing the refund.', 500);
        }
    }

    private function hasPermission(Request $request): bool
    {
        return $request->user()?->hasPermission('manage_refunds') ?? false;
    }
}





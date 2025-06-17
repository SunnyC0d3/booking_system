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
    protected bool $skipGatewayProcessing = false;
    protected int $approvedCount = 0;
    protected ?string $refundSource = null;
    protected ?string $refundNotes = null;

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

    public function skipGatewayProcessing(): void
    {
        $this->skipGatewayProcessing = true;
    }

    public function setRefundSource(string $source): void
    {
        $this->refundSource = $source;
    }

    public function setRefundNotes(string $notes): void
    {
        $this->refundNotes = $notes;
    }

    protected function getOrderReturnWithRelations(int $id): void
    {
        $this->orderReturn = OrderReturn::with(['orderItem.order.user'])->findOrFail($id);
        $this->orderItem = $this->orderReturn->orderItem;
        $this->order = $this->orderItem->order;
        $this->payment = $this->order->payments->first();
    }

    protected function getOrderWithItems(int $id): void
    {
        $this->order = Order::with(['orderItems.orderReturn'])->findOrFail($id);
        $this->orderItems = $this->order->orderItems;
        $this->payment = $this->order->payments->first();
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

        $items = $this->orderItem
            ? collect([$this->orderItem])
            : $this->orderItems->filter(fn ($item) => $item->orderReturn && $item->orderReturn->isApproved());

        $items->each(function ($item) use ($refundedStatusId) {
            $refundData = [
                'order_return_id' => $item->orderReturn->id,
                'order_id' => $item->order_id,
                'amount' => $item->refundAmount(),
                'order_refund_status_id' => $refundedStatusId,
                'processed_at' => now(),
            ];

            if ($this->refundSource) {
                $refundData['source'] = $this->refundSource;
            }

            if ($this->refundNotes) {
                $refundData['notes'] = $this->refundNotes;
            }

            OrderRefund::create($refundData);

            Log::info('Refund record created', [
                'order_item_id' => $item->id,
                'return_id' => $item->orderReturn->id,
                'amount' => $item->refundAmount() / 100,
                'source' => $this->refundSource ?? 'api'
            ]);
        });
    }

    protected function updateOrderAndPaymentStatus(): void
    {
        $isFullRefund = (!empty($this->orderItems) && $this->approvedCount === count($this->orderItems));

        $refundedStatusId = $isFullRefund
            ? OrderStatus::where('name', OrderStatuses::REFUNDED)->value('id')
            : OrderStatus::where('name', OrderStatuses::PARTIALLY_REFUNDED)->value('id');

        $paymentStatus = $isFullRefund
            ? PaymentStatuses::REFUNDED
            : PaymentStatuses::PARTIALLY_REFUNDED;

        $this->order->update(['status_id' => $refundedStatusId]);
        $this->payment->update(['status' => $paymentStatus]);

        Log::info('Updated order and payment status', [
            'order_id' => $this->order->id,
            'is_full_refund' => $isFullRefund,
            'order_status' => $isFullRefund ? 'REFUNDED' : 'PARTIALLY_REFUNDED',
            'payment_status' => $paymentStatus
        ]);
    }

    protected function markReturnAsCompleted(): void
    {
        $completedStatusId = OrderReturnStatus::where('name', ReturnStatuses::COMPLETED)->value('id');

        if ($this->orderReturn) {
            $this->orderReturn->update(['order_return_status_id' => $completedStatusId]);

            Log::info('Return marked as completed', [
                'return_id' => $this->orderReturn->id,
                'order_id' => $this->order->id
            ]);
        } else {
            $approvedReturns = $this->orderItems->filter(fn ($item) => $item->orderReturn && $item->orderReturn->isApproved());

            foreach ($approvedReturns as $item) {
                $item->orderReturn->update(['order_return_status_id' => $completedStatusId]);

                Log::info('Return marked as completed', [
                    'return_id' => $item->orderReturn->id,
                    'order_item_id' => $item->id
                ]);
            }
        }
    }

    protected function markRefundAsFailed(string $reason): void
    {
        $failedStatusId = OrderRefundStatus::where('name', RefundStatuses::FAILED)->value('id');

        OrderRefund::create([
            'order_return_id' => $this->orderReturn->id,
            'order_id' => $this->order->id,
            'amount' => $this->orderItem->refundAmount(),
            'order_refund_status_id' => $failedStatusId,
            'processed_at' => now(),
            'notes' => $reason,
            'source' => $this->refundSource ?? 'api'
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

    public function cancelRefund(int $orderId, int $refundAmount): bool
    {
        try {
            $order = Order::findOrFail($orderId);

            $matchingRefunds = OrderRefund::where('order_id', $orderId)
                ->where('amount', $refundAmount)
                ->where('created_at', '>=', now()->subHours(24))
                ->get();

            if ($matchingRefunds->isEmpty()) {
                Log::info('No matching refund record found for cancellation', [
                    'order_id' => $orderId,
                    'refund_amount' => $refundAmount / 100
                ]);
                return false;
            }

            $canceledStatusId = OrderRefundStatus::where('name', RefundStatuses::CANCELLED)->value('id');
            $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');

            $refundToCancel = $matchingRefunds->sortByDesc('created_at')->first();

            $refundToCancel->update([
                'order_refund_status_id' => $canceledStatusId,
                'notes' => ($refundToCancel->notes ?? '') . ' | Refund canceled in payment gateway'
            ]);

            if ($refundToCancel->order_return_id) {
                OrderReturn::where('id', $refundToCancel->order_return_id)
                    ->update(['order_return_status_id' => $approvedStatusId]);

                Log::info('Reverted return status back to approved due to refund cancellation', [
                    'return_id' => $refundToCancel->order_return_id,
                    'order_id' => $orderId
                ]);
            }

            $this->recalculateOrderStatus($order);

            Log::info('Refund cancellation processed', [
                'order_id' => $orderId,
                'refund_record_id' => $refundToCancel->id,
                'amount' => $refundAmount / 100
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to process refund cancellation', [
                'order_id' => $orderId,
                'amount' => $refundAmount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function failRefund(int $orderId, string $reason): bool
    {
        try {
            $order = Order::findOrFail($orderId);

            $failedStatusId = OrderRefundStatus::where('name', RefundStatuses::FAILED)->value('id');
            $pendingStatusId = OrderRefundStatus::where('name', RefundStatuses::PENDING)->value('id');
            $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');

            $updatedRefunds = OrderRefund::where('order_id', $orderId)
                ->where('order_refund_status_id', $pendingStatusId)
                ->update([
                    'order_refund_status_id' => $failedStatusId,
                    'notes' => $reason
                ]);

            if ($updatedRefunds > 0) {
                $failedRefunds = OrderRefund::where('order_id', $orderId)
                    ->where('order_refund_status_id', $failedStatusId)
                    ->whereNotNull('order_return_id')
                    ->get();

                foreach ($failedRefunds as $failedRefund) {
                    if ($failedRefund->order_return_id) {
                        OrderReturn::where('id', $failedRefund->order_return_id)
                            ->update(['order_return_status_id' => $approvedStatusId]);

                        Log::info('Reverted return status due to failed refund', [
                            'return_id' => $failedRefund->order_return_id,
                            'order_id' => $orderId
                        ]);
                    }
                }
            }

            Log::info('Refund failure processed', [
                'order_id' => $orderId,
                'updated_refunds_count' => $updatedRefunds,
                'reason' => $reason
            ]);

            return $updatedRefunds > 0;

        } catch (\Exception $e) {
            Log::error('Failed to process refund failure', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function createManualRefund(int $orderId, int $amount, string $notes = '', string $source = 'manual'): bool
    {
        try {
            $order = Order::with('orderItems.orderReturn')->findOrFail($orderId);

            $approvedReturns = $order->orderItems()
                ->whereHas('orderReturn', function ($q) {
                    $q->where('status', ReturnStatuses::APPROVED);
                })
                ->get();

            $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

            if ($approvedReturns->isNotEmpty()) {
                $this->createRefundRecordsForApprovedReturns($order, $approvedReturns, $amount, $notes, $source);
            } else {
                OrderRefund::create([
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'order_refund_status_id' => $refundedStatusId,
                    'processed_at' => now(),
                    'notes' => $notes ?: 'Manual refund processed externally',
                    'source' => $source,
                    'is_manual' => true,
                ]);
            }

            $this->recalculateOrderStatus($order);

            Log::info('Manual refund created', [
                'order_id' => $orderId,
                'amount' => $amount / 100,
                'source' => $source,
                'linked_returns' => $approvedReturns->count()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create manual refund', [
                'order_id' => $orderId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    protected function createRefundRecordsForApprovedReturns(Order $order, $approvedReturns, int $totalAmount, string $notes = '', string $source = 'webhook'): void
    {
        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');
        $completedStatusId = OrderReturnStatus::where('name', ReturnStatuses::COMPLETED)->value('id');

        $returnCount = $approvedReturns->count();
        $remainingAmount = $totalAmount;

        $approvedReturns->each(function ($orderItem, $index) use ($refundedStatusId, $completedStatusId, &$remainingAmount, $returnCount, $notes, $source) {
            $itemRefundAmount = ($index === $returnCount - 1)
                ? $remainingAmount
                : intval($orderItem->refundAmount());

            OrderRefund::create([
                'order_return_id' => $orderItem->orderReturn->id,
                'order_id' => $orderItem->order_id,
                'amount' => $itemRefundAmount,
                'order_refund_status_id' => $refundedStatusId,
                'processed_at' => now(),
                'notes' => $notes ?: 'Refund processed via payment gateway',
                'source' => $source,
            ]);

            $orderItem->orderReturn->update([
                'order_return_status_id' => $completedStatusId
            ]);

            $remainingAmount -= $itemRefundAmount;

            Log::info('Created refund record for approved return', [
                'order_item_id' => $orderItem->id,
                'return_id' => $orderItem->orderReturn->id,
                'refund_amount' => $itemRefundAmount / 100
            ]);
        });
    }

    protected function recalculateOrderStatus(Order $order): void
    {
        $payment = $order->payments->first();
        if (!$payment) return;

        $totalRefunded = OrderRefund::where('order_id', $order->id)
            ->whereIn('order_refund_status_id', [
                OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id')
            ])
            ->sum('amount');

        $isFullRefund = $totalRefunded >= $payment->amount;
        $isPartialRefund = $totalRefunded > 0 && $totalRefunded < $payment->amount;

        if ($isFullRefund) {
            $orderStatusId = OrderStatus::where('name', OrderStatuses::REFUNDED)->value('id');
            $paymentStatus = PaymentStatuses::REFUNDED;
        } elseif ($isPartialRefund) {
            $orderStatusId = OrderStatus::where('name', OrderStatuses::PARTIALLY_REFUNDED)->value('id');
            $paymentStatus = PaymentStatuses::PARTIALLY_REFUNDED;
        } else {
            $orderStatusId = OrderStatus::where('name', OrderStatuses::CONFIRMED)->value('id');
            $paymentStatus = PaymentStatuses::PAID;
        }

        $order->update(['status_id' => $orderStatusId]);
        $payment->update(['status' => $paymentStatus]);

        Log::info('Recalculated order status', [
            'order_id' => $order->id,
            'total_refunded' => $totalRefunded / 100,
            'payment_amount' => $payment->amount / 100,
            'new_status' => $isFullRefund ? 'REFUNDED' : ($isPartialRefund ? 'PARTIALLY_REFUNDED' : 'CONFIRMED')
        ]);
    }

    public function refund(Request $request, int $id)
    {
        if (!$this->webhookEnabled && !$this->hasPermission($request)) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $this->initializeRefundContext($id);

            if (!$this->skipGatewayProcessing && !$this->webhookEnabled) {
                $refundSuccessful = $this->gateway->refund($this->order, $this->orderItem);

                if (!$refundSuccessful) {
                    $this->markRefundAsFailed('Refund failed.');
                    return $this->error('Refund failed. Please try again later.', 422);
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

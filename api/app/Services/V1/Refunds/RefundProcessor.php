<?php

namespace App\Services\V1\Refunds;

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
use App\Services\V1\Emails\Email;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
    protected Email $emailService;

    public function __construct(PaymentGatewayRefundInterface $gateway, Email $emailService = null)
    {
        $this->gateway = $gateway;
        $this->emailService = $emailService ?? app(Email::class);
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
            $refundAmountInPennies = $item->refundAmount();

            $refundData = [
                'order_return_id' => $item->orderReturn->id,
                'amount' => $refundAmountInPennies,
                'order_refund_status_id' => $refundedStatusId,
                'processed_at' => now(),
            ];

            if ($this->refundNotes) {
                $refundData['notes'] = $this->refundNotes;
            }

            OrderRefund::create($refundData);

            Log::info('Refund record created', [
                'order_item_id' => $item->id,
                'return_id' => $item->orderReturn->id,
                'amount_pennies' => $refundAmountInPennies,
                'amount_pounds' => $refundAmountInPennies / 100,
                'source' => $this->refundSource ?? 'api'
            ]);
        });
    }

    protected function updateOrderAndPaymentStatus(): void
    {
        $this->recalculateOrderStatus($this->order);
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
        $refundAmountInPennies = $this->orderItem->refundAmount();

        OrderRefund::create([
            'order_return_id' => $this->orderReturn->id,
            'amount' => $refundAmountInPennies,
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

        $this->sendRefundProcessedEmail();
    }

    public function cancelRefund(int $orderId, int $refundAmountInPennies): bool
    {
        try {
            $order = Order::with(['payments', 'orderItems.orderReturn'])->findOrFail($orderId);
            $payment = $order->payments->first();

            if (!$payment) {
                Log::error('No payment found for order', ['order_id' => $orderId]);
                return false;
            }

            Log::info('Starting refund cancellation', [
                'order_id' => $orderId,
                'refund_amount_pennies' => $refundAmountInPennies,
                'refund_amount_pounds' => $refundAmountInPennies / 100,
                'current_payment_status' => $payment->status,
                'payment_amount_pennies' => $payment->getAmountInPennies(),
                'payment_amount_pounds' => $payment->getAmountInPounds()
            ]);

            $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');
            $pendingStatusId = OrderRefundStatus::where('name', RefundStatuses::PENDING)->value('id');
            $canceledStatusId = OrderRefundStatus::where('name', RefundStatuses::CANCELLED)->value('id');

            $matchingRefunds = OrderRefund::where('amount', $refundAmountInPennies)
                ->where('created_at', '>=', now()->subHours(48))
                ->whereHas('orderReturn', function ($q) use ($orderId) {
                    $q->whereHas('orderItem', function ($sq) use ($orderId) {
                        $sq->where('order_id', $orderId);
                    });
                })
                ->whereIn('order_refund_status_id', [$refundedStatusId, $pendingStatusId])
                ->get();

            if ($matchingRefunds->isEmpty()) {
                Log::info('No exact amount match, looking for recent refunds', [
                    'order_id' => $orderId,
                    'refund_amount_pennies' => $refundAmountInPennies
                ]);

                $matchingRefunds = OrderRefund::where('created_at', '>=', now()->subHours(48))
                    ->whereHas('orderReturn', function ($q) use ($orderId) {
                        $q->whereHas('orderItem', function ($sq) use ($orderId) {
                            $sq->where('order_id', $orderId);
                        });
                    })
                    ->whereIn('order_refund_status_id', [$refundedStatusId, $pendingStatusId])
                    ->orderByDesc('created_at')
                    ->get();
            }

            if ($matchingRefunds->isEmpty()) {
                Log::warning('No refund records found to cancel', [
                    'order_id' => $orderId,
                    'refund_amount_pennies' => $refundAmountInPennies,
                    'note' => 'This refund may never have been recorded in our database'
                ]);

                $this->recalculateOrderStatus($order);
                return false;
            }

            $refundToCancel = $matchingRefunds->sortByDesc('created_at')->first();

            Log::info('Found refund to cancel', [
                'refund_id' => $refundToCancel->id,
                'refund_amount_pennies' => $refundToCancel->getAmountInPennies(),
                'refund_amount_pounds' => $refundToCancel->getAmountInPounds(),
                'requested_amount_pennies' => $refundAmountInPennies,
                'created_at' => $refundToCancel->created_at,
                'current_status' => $refundToCancel->orderRefundStatus->name ?? 'unknown'
            ]);

            $originalStatus = $refundToCancel->orderRefundStatus->name ?? 'unknown';
            $refundToCancel->update([
                'order_refund_status_id' => $canceledStatusId,
                'notes' => ($refundToCancel->notes ?? '') . ' | Refund canceled in payment gateway on ' . now()->format('Y-m-d H:i:s')
            ]);

            if ($refundToCancel->order_return_id) {
                $orderReturn = OrderReturn::find($refundToCancel->order_return_id);

                if ($orderReturn) {
                    $isDummyReturn = str_contains($orderReturn->reason ?? '', 'Stripe Dashboard refund') ||
                        str_contains($orderReturn->reason ?? '', 'external refund') ||
                        str_contains($orderReturn->reason ?? '', 'External Stripe refund');

                    $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');
                    $pendingReturnStatusId = OrderReturnStatus::where('name', ReturnStatuses::PENDING)->value('id');

                    $newStatusId = $isDummyReturn ? $pendingReturnStatusId : $approvedStatusId;

                    $orderReturn->update(['order_return_status_id' => $newStatusId]);

                    Log::info('Reverted return status due to refund cancellation', [
                        'return_id' => $refundToCancel->order_return_id,
                        'order_id' => $orderId,
                        'new_status' => $isDummyReturn ? 'PENDING' : 'APPROVED',
                        'was_dummy_return' => $isDummyReturn
                    ]);
                }
            }

            $originalPaymentStatus = $payment->status;
            $this->recalculateOrderStatus($order);

            $payment->refresh();
            $order->refresh();

            Log::info('Refund cancellation completed successfully', [
                'order_id' => $orderId,
                'canceled_refund_id' => $refundToCancel->id,
                'canceled_amount_pennies' => $refundToCancel->getAmountInPennies(),
                'canceled_amount_pounds' => $refundToCancel->getAmountInPounds(),
                'original_refund_status' => $originalStatus,
                'original_payment_status' => $originalPaymentStatus,
                'new_payment_status' => $payment->status,
                'payment_status_changed' => $originalPaymentStatus !== $payment->status
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to process refund cancellation', [
                'order_id' => $orderId,
                'amount_pennies' => $refundAmountInPennies,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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

            $updatedRefunds = OrderRefund::whereHas('orderReturn', function ($q) use ($orderId) {
                $q->whereHas('orderItem', function ($sq) use ($orderId) {
                    $sq->where('order_id', $orderId);
                });
            })
                ->where('order_refund_status_id', $pendingStatusId)
                ->update([
                    'order_refund_status_id' => $failedStatusId,
                    'notes' => $reason
                ]);

            if ($updatedRefunds > 0) {
                $failedRefunds = OrderRefund::whereHas('orderReturn', function ($q) use ($orderId) {
                    $q->whereHas('orderItem', function ($sq) use ($orderId) {
                        $sq->where('order_id', $orderId);
                    });
                })
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

    public function createManualRefund(int $orderId, int $amountInPennies, string $notes = '', string $source = 'manual'): bool
    {
        try {
            $order = Order::with(['orderItems.orderReturn', 'payments'])->findOrFail($orderId);

            Log::info('Debug: Order loaded for manual refund', [
                'order_id' => $orderId,
                'payments_count' => $order->payments->count(),
                'amount_pennies' => $amountInPennies,
                'amount_pounds' => $amountInPennies / 100
            ]);

            $approvedStatusId = OrderReturnStatus::where('name', ReturnStatuses::APPROVED)->value('id');

            $approvedReturns = $order->orderItems()
                ->whereHas('orderReturn', function ($q) use ($approvedStatusId) {
                    $q->where('order_return_status_id', $approvedStatusId);
                })
                ->get();

            if ($approvedReturns->isNotEmpty()) {
                $this->createRefundRecordsForApprovedReturns($order, $approvedReturns, $amountInPennies, $notes, $source);
            } else {
                Log::warning('Cannot create manual refund without approved returns - order_return_id is required', [
                    'order_id' => $orderId,
                    'amount_pennies' => $amountInPennies
                ]);
                return false;
            }

            $this->recalculateOrderStatus($order);

            Log::info('Manual refund created', [
                'order_id' => $orderId,
                'amount_pennies' => $amountInPennies,
                'amount_pounds' => $amountInPennies / 100,
                'source' => $source,
                'linked_returns' => $approvedReturns->count()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to create manual refund', [
                'order_id' => $orderId,
                'amount_pennies' => $amountInPennies,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    protected function createRefundRecordsForApprovedReturns(Order $order, $approvedReturns, int $totalAmountInPennies, string $notes = '', string $source = 'webhook'): void
    {
        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');
        $completedStatusId = OrderReturnStatus::where('name', ReturnStatuses::COMPLETED)->value('id');

        $returnCount = $approvedReturns->count();
        $remainingAmount = $totalAmountInPennies;

        $approvedReturns->each(function ($orderItem, $index) use ($refundedStatusId, $completedStatusId, &$remainingAmount, $returnCount, $notes, $source) {
            $itemRefundAmount = ($index === $returnCount - 1)
                ? $remainingAmount
                : $orderItem->refundAmount();

            $refund = OrderRefund::create([
                'order_return_id' => $orderItem->orderReturn->id,
                'amount' => $itemRefundAmount,
                'order_refund_status_id' => $refundedStatusId,
                'processed_at' => now(),
                'notes' => $notes ?: 'Refund processed via payment gateway',
            ]);

            $orderItem->orderReturn->update([
                'order_return_status_id' => $completedStatusId
            ]);

            $remainingAmount -= $itemRefundAmount;

            Log::info('Created refund record for approved return', [
                'order_item_id' => $orderItem->id,
                'return_id' => $orderItem->orderReturn->id,
                'refund_amount_pennies' => $itemRefundAmount,
                'refund_amount_pounds' => $itemRefundAmount / 100
            ]);

            if ($index === 0) {
                $this->sendRefundProcessedEmailForRefund($refund);
            }
        });
    }

    public function recalculateOrderStatus(Order $order): void
    {
        $order->load('payments');

        $payment = $order->payments->first();
        if (!$payment) {
            Log::warning('No valid payment found for order', ['order_id' => $order->id]);
            return;
        }

        $originalPaymentStatus = $payment->status;
        $originalOrderStatus = $order->status->name ?? 'unknown';

        Log::info('Recalculating order status', [
            'order_id' => $order->id,
            'payment_amount_pennies' => $payment->getAmountInPennies(),
            'payment_amount_pounds' => $payment->getAmountInPounds(),
            'current_payment_status' => $payment->status,
            'current_order_status' => $originalOrderStatus
        ]);

        $refundedStatusId = OrderRefundStatus::where('name', RefundStatuses::REFUNDED)->value('id');

        $totalActiveRefunds = OrderRefund::whereHas('orderReturn', function ($q) use ($order) {
            $q->whereHas('orderItem', function ($sq) use ($order) {
                $sq->where('order_id', $order->id);
            });
        })
            ->where('order_refund_status_id', $refundedStatusId)
            ->sum('amount');

        $totalOrderItems = $order->orderItems()->count();
        $itemsWithActiveRefunds = $order->orderItems()
            ->whereHas('orderReturn.orderRefund', function ($q) use ($refundedStatusId) {
                $q->where('order_refund_status_id', $refundedStatusId);
            })
            ->count();

        $paymentAmountInPennies = $payment->getAmountInPennies();
        $isFullRefundByAmount = $totalActiveRefunds >= $paymentAmountInPennies;
        $isFullRefundByItems = $itemsWithActiveRefunds >= $totalOrderItems;
        $isFullRefund = $isFullRefundByAmount || $isFullRefundByItems;
        $isPartialRefund = $totalActiveRefunds > 0 && !$isFullRefund;

        if ($isFullRefund) {
            $orderStatusId = OrderStatus::where('name', OrderStatuses::REFUNDED)->value('id');
            $paymentStatus = PaymentStatuses::REFUNDED;
            $statusName = 'REFUNDED';
        } elseif ($isPartialRefund) {
            $orderStatusId = OrderStatus::where('name', OrderStatuses::PARTIALLY_REFUNDED)->value('id');
            $paymentStatus = PaymentStatuses::PARTIALLY_REFUNDED;
            $statusName = 'PARTIALLY_REFUNDED';
        } else {
            $orderStatusId = OrderStatus::where('name', OrderStatuses::CONFIRMED)->value('id');
            $paymentStatus = PaymentStatuses::PAID;
            $statusName = 'PAID';
        }

        $order->update(['status_id' => $orderStatusId]);
        $payment->update(['status' => $paymentStatus]);

        Log::info('Order status recalculation completed', [
            'order_id' => $order->id,
            'total_active_refunds_pennies' => $totalActiveRefunds,
            'total_active_refunds_pounds' => $totalActiveRefunds / 100,
            'payment_amount_pennies' => $paymentAmountInPennies,
            'payment_amount_pounds' => $paymentAmountInPennies / 100,
            'total_items' => $totalOrderItems,
            'items_with_active_refunds' => $itemsWithActiveRefunds,
            'original_payment_status' => $originalPaymentStatus,
            'new_payment_status' => $paymentStatus,
            'original_order_status' => $originalOrderStatus,
            'new_order_status' => $statusName,
            'status_changed' => $originalPaymentStatus !== $paymentStatus
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

    private function sendRefundProcessedEmail(): void
    {
        try {
            if ($this->orderReturn && $this->orderReturn->orderRefund) {
                $this->sendRefundProcessedEmailForRefund($this->orderReturn->orderRefund);
            } elseif ($this->orderItems) {
                foreach ($this->orderItems as $item) {
                    if ($item->orderReturn && $item->orderReturn->orderRefund) {
                        $this->sendRefundProcessedEmailForRefund($item->orderReturn->orderRefund);
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to send refund processed email', [
                'order_id' => $this->order?->id,
                'return_id' => $this->orderReturn?->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function sendRefundProcessedEmailForRefund(OrderRefund $refund): void
    {
        try {
            $refund->load(['orderReturn.orderItem.order.user']);
            $refundData = $this->emailService->formatRefundData($refund);
            $customerEmail = $refund->orderReturn->orderItem->order->user->email;

            $this->emailService->sendRefundProcessed($refundData, $customerEmail);
        } catch (\Exception $e) {
            Log::error('Failed to send individual refund processed email', [
                'refund_id' => $refund->id,
                'return_id' => $refund->order_return_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

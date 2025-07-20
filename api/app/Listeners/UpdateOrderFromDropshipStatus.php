<?php

namespace App\Listeners;

use App\Events\DropshipOrderStatusChanged;
use App\Models\DropshipOrder;
use App\Models\Order;
use App\Constants\DropshipStatuses;
use App\Constants\OrderStatuses;
use App\Constants\FulfillmentStatuses;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateOrderFromDropshipStatus implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'order_updates';

    public function handle(DropshipOrderStatusChanged $event): void
    {
        $dropshipOrder = $event->dropshipOrder;
        $oldStatus = $event->oldStatus;
        $newStatus = $event->newStatus;

        try {
            Log::info('Processing dropship order status change', [
                'dropship_order_id' => $dropshipOrder->id,
                'order_id' => $dropshipOrder->order_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            $order = $dropshipOrder->order;
            $this->updateOrderStatus($order, $dropshipOrder);
            $this->updateFulfillmentStatus($order);
            $this->updateTrackingInformation($order, $dropshipOrder);
            $this->handleSpecialStatusChanges($dropshipOrder, $oldStatus, $newStatus);

        } catch (Exception $e) {
            Log::error('Failed to update order from dropship status change', [
                'dropship_order_id' => $dropshipOrder->id,
                'order_id' => $dropshipOrder->order_id,
                'error' => $e->getMessage()
            ]);

            $this->failed($e);
        }
    }

    public function failed(Exception $exception): void
    {
        Log::critical('UpdateOrderFromDropshipStatus listener failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    private function updateOrderStatus(Order $order, DropshipOrder $dropshipOrder): void
    {
        $allDropshipOrders = $order->dropshipOrders;
        $newOrderStatus = $this->determineOrderStatus($allDropshipOrders, $dropshipOrder);

        if ($newOrderStatus && $order->status->name !== $newOrderStatus) {
            $statusId = \App\Models\OrderStatus::where('name', $newOrderStatus)->value('id');

            if ($statusId) {
                $order->update(['status_id' => $statusId]);

                Log::info('Order status updated from dropship status change', [
                    'order_id' => $order->id,
                    'old_status' => $order->status->name,
                    'new_status' => $newOrderStatus,
                    'trigger_dropship_order_id' => $dropshipOrder->id
                ]);
            }
        }
    }

    private function determineOrderStatus($allDropshipOrders, DropshipOrder $triggerOrder): ?string
    {
        $statusCounts = $allDropshipOrders->groupBy('status')->map->count();
        $totalOrders = $allDropshipOrders->count();

        if ($statusCounts->get(DropshipStatuses::DELIVERED, 0) === $totalOrders) {
            return OrderStatuses::DELIVERED;
        }

        if ($statusCounts->get(DropshipStatuses::CANCELLED, 0) === $totalOrders) {
            return OrderStatuses::CANCELLED;
        }

        if ($statusCounts->get(DropshipStatuses::DELIVERED, 0) > 0 &&
            $statusCounts->get(DropshipStatuses::CANCELLED, 0) > 0 &&
            ($statusCounts->get(DropshipStatuses::DELIVERED, 0) + $statusCounts->get(DropshipStatuses::CANCELLED, 0)) === $totalOrders) {
            return OrderStatuses::DELIVERED;
        }

        if ($allDropshipOrders->some(fn($order) => in_array($order->status, [
            DropshipStatuses::SHIPPED_BY_SUPPLIER,
            DropshipStatuses::DELIVERED
        ]))) {
            return OrderStatuses::SHIPPED;
        }

        if ($allDropshipOrders->some(fn($order) => $order->status === DropshipStatuses::OUT_FOR_DELIVERY)) {
            return OrderStatuses::OUT_FOR_DELIVERY;
        }

        if ($allDropshipOrders->some(fn($order) => in_array($order->status, [
            DropshipStatuses::CONFIRMED_BY_SUPPLIER,
            DropshipStatuses::PROCESSING
        ]))) {
            return OrderStatuses::PROCESSING;
        }

        if ($allDropshipOrders->every(fn($order) => in_array($order->status, [
            DropshipStatuses::REJECTED_BY_SUPPLIER,
            DropshipStatuses::CANCELLED
        ]))) {
            return OrderStatuses::FAILED;
        }

        if ($triggerOrder->status === DropshipStatuses::ON_HOLD) {
            return OrderStatuses::ON_HOLD;
        }

        return null;
    }

    private function updateFulfillmentStatus(Order $order): void
    {
        try {
            $allDropshipOrders = $order->dropshipOrders;
            $fulfillmentStatus = $this->determineFulfillmentStatus($allDropshipOrders);

            if ($fulfillmentStatus) {
                $order->update(['fulfillment_status' => $fulfillmentStatus]);

                Log::debug('Order fulfillment status updated', [
                    'order_id' => $order->id,
                    'fulfillment_status' => $fulfillmentStatus
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to update fulfillment status', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function determineFulfillmentStatus($allDropshipOrders): ?string
    {
        $totalOrders = $allDropshipOrders->count();
        $deliveredCount = $allDropshipOrders->where('status', DropshipStatuses::DELIVERED)->count();
        $shippedCount = $allDropshipOrders->whereIn('status', [
            DropshipStatuses::SHIPPED_BY_SUPPLIER,
            DropshipStatuses::DELIVERED
        ])->count();

        if ($deliveredCount === $totalOrders) {
            return FulfillmentStatuses::DELIVERED;
        }

        if ($deliveredCount > 0) {
            return FulfillmentStatuses::PARTIALLY_DELIVERED;
        }

        if ($shippedCount === $totalOrders) {
            return FulfillmentStatuses::SHIPPED;
        }

        if ($shippedCount > 0) {
            return FulfillmentStatuses::PARTIALLY_SHIPPED;
        }

        $fulfilledCount = $allDropshipOrders->whereIn('status', [
            DropshipStatuses::CONFIRMED_BY_SUPPLIER,
            DropshipStatuses::PROCESSING,
            DropshipStatuses::SHIPPED_BY_SUPPLIER
        ])->count();

        if ($fulfilledCount === $totalOrders) {
            return FulfillmentStatuses::FULFILLED;
        }

        if ($fulfilledCount > 0) {
            return FulfillmentStatuses::PARTIALLY_FULFILLED;
        }

        $cancelledCount = $allDropshipOrders->where('status', DropshipStatuses::CANCELLED)->count();
        if ($cancelledCount === $totalOrders) {
            return FulfillmentStatuses::CANCELLED;
        }

        return FulfillmentStatuses::UNFULFILLED;
    }

    private function updateTrackingInformation(Order $order, DropshipOrder $dropshipOrder): void
    {
        if (!$dropshipOrder->tracking_number || !$dropshipOrder->isShipped()) {
            return;
        }

        try {
            $trackingData = [
                'tracking_number' => $dropshipOrder->tracking_number,
                'carrier' => $dropshipOrder->carrier,
                'shipped_at' => $dropshipOrder->shipped_by_supplier_at,
                'estimated_delivery' => $dropshipOrder->estimated_delivery,
                'supplier_id' => $dropshipOrder->supplier_id,
                'dropship_order_id' => $dropshipOrder->id
            ];

            $existingTracking = $order->tracking_numbers ?? [];
            $existingTracking[] = $trackingData;

            $order->update(['tracking_numbers' => $existingTracking]);

            Log::info('Order tracking information updated', [
                'order_id' => $order->id,
                'tracking_number' => $dropshipOrder->tracking_number,
                'carrier' => $dropshipOrder->carrier
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to update tracking information', [
                'order_id' => $order->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleSpecialStatusChanges(DropshipOrder $dropshipOrder, string $oldStatus, string $newStatus): void
    {
        switch ($newStatus) {
            case DropshipStatuses::DELIVERED:
                $this->handleDeliveryComplete($dropshipOrder);
                break;

            case DropshipStatuses::CANCELLED:
                $this->handleDropshipCancellation($dropshipOrder, $oldStatus);
                break;

            case DropshipStatuses::REJECTED_BY_SUPPLIER:
                $this->handleSupplierRejection($dropshipOrder);
                break;

            case DropshipStatuses::SHIPPED_BY_SUPPLIER:
                $this->handleShipmentStarted($dropshipOrder);
                break;
        }
    }

    private function handleDeliveryComplete(DropshipOrder $dropshipOrder): void
    {
        try {
            $order = $dropshipOrder->order;

            Log::info('Dropship order delivered', [
                'dropship_order_id' => $dropshipOrder->id,
                'order_id' => $order->id,
                'delivered_at' => $dropshipOrder->delivered_at,
                'processing_time' => $dropshipOrder->getProcessingTimeFormatted()
            ]);

            $this->updateInventoryAfterDelivery($dropshipOrder);

        } catch (Exception $e) {
            Log::error('Failed to handle delivery completion', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleDropshipCancellation(DropshipOrder $dropshipOrder, string $oldStatus): void
    {
        Log::warning('Dropship order cancelled', [
            'dropship_order_id' => $dropshipOrder->id,
            'order_id' => $dropshipOrder->order_id,
            'previous_status' => $oldStatus,
            'cancellation_reason' => $dropshipOrder->notes
        ]);

        $this->restoreInventoryAfterCancellation($dropshipOrder);
    }

    private function handleSupplierRejection(DropshipOrder $dropshipOrder): void
    {
        Log::warning('Dropship order rejected by supplier', [
            'dropship_order_id' => $dropshipOrder->id,
            'supplier_id' => $dropshipOrder->supplier_id,
            'rejection_reason' => $dropshipOrder->supplier_notes,
            'can_retry' => $dropshipOrder->canRetry()
        ]);
    }

    private function handleShipmentStarted(DropshipOrder $dropshipOrder): void
    {
        Log::info('Dropship order shipped by supplier', [
            'dropship_order_id' => $dropshipOrder->id,
            'tracking_number' => $dropshipOrder->tracking_number,
            'carrier' => $dropshipOrder->carrier,
            'estimated_delivery' => $dropshipOrder->estimated_delivery
        ]);
    }

    private function updateInventoryAfterDelivery(DropshipOrder $dropshipOrder): void
    {
        foreach ($dropshipOrder->dropshipOrderItems as $item) {
            if ($item->supplierProduct && $item->supplierProduct->productMapping) {
                $mapping = $item->supplierProduct->productMapping;

                if ($mapping->canUpdateStock()) {
                    $currentStock = $item->supplierProduct->stock_quantity;
                    $newStock = max(0, $currentStock - $item->quantity);

                    $item->supplierProduct->updateStock($newStock);
                    $mapping->updateStock($newStock);
                }
            }
        }
    }

    private function restoreInventoryAfterCancellation(DropshipOrder $dropshipOrder): void
    {
        foreach ($dropshipOrder->dropshipOrderItems as $item) {
            $product = $item->orderItem->product;

            if ($product && !$product->is_virtual) {
                $product->increment('quantity', $item->quantity);

                Log::debug('Inventory restored after dropship cancellation', [
                    'product_id' => $product->id,
                    'quantity_restored' => $item->quantity
                ]);
            }
        }
    }

    public function viaQueue(): string
    {
        return 'order_updates';
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(15);
    }
}

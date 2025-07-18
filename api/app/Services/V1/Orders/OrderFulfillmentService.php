<?php

namespace App\Services\V1\Orders;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\OrderStatus;
use App\Constants\OrderStatuses;
use App\Constants\FulfillmentStatuses;
use App\Constants\ShippingStatuses;
use App\Services\V1\Shipping\ShippingService;
use App\Services\V1\Emails\Email;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderFulfillmentService
{
    protected ShippingService $shippingService;
    protected Email $emailService;

    public function __construct(ShippingService $shippingService, Email $emailService)
    {
        $this->shippingService = $shippingService;
        $this->emailService = $emailService;
    }

    public function processOrderStatusChange(Order $order, string $newStatus, string $oldStatus = null): void
    {
        Log::info('Processing order status change', [
            'order_id' => $order->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'requires_shipping' => $order->requiresShipping(),
        ]);

        try {
            switch ($newStatus) {
                case OrderStatuses::CONFIRMED:
                    $this->handleOrderConfirmed($order);
                    break;
                case OrderStatuses::PROCESSING:
                    $this->handleOrderProcessing($order);
                    break;
                case OrderStatuses::SHIPPED:
                    $this->handleOrderShipped($order);
                    break;
                case OrderStatuses::DELIVERED:
                    $this->handleOrderDelivered($order);
                    break;
                case OrderStatuses::CANCELLED:
                    $this->handleOrderCancelled($order);
                    break;
            }
        } catch (Exception $e) {
            Log::error('Failed to process order status change', [
                'order_id' => $order->id,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function handleOrderConfirmed(Order $order): void
    {
        if (!$order->requiresShipping()) {
            // For virtual products, mark as fulfilled immediately
            $order->update(['fulfillment_status' => FulfillmentStatuses::FULFILLED]);
            return;
        }

        // Set fulfillment status to unfulfilled for physical products
        $order->update(['fulfillment_status' => FulfillmentStatuses::UNFULFILLED]);

        // Auto-transition to processing if conditions are met
        if ($this->shouldAutoTransitionToProcessing($order)) {
            $this->transitionOrderToProcessing($order);
        }
    }

    protected function handleOrderProcessing(Order $order): void
    {
        if (!$order->requiresShipping()) {
            return;
        }

        // Create shipment if one doesn't exist
        if (!$order->hasActiveShipment()) {
            $this->createShipmentForOrder($order);
        }

        // Update fulfillment status
        $order->update(['fulfillment_status' => FulfillmentStatuses::FULFILLED]);
    }

    protected function handleOrderShipped(Order $order): void
    {
        $order->update([
            'fulfillment_status' => FulfillmentStatuses::SHIPPED,
            'shipped_at' => now(),
        ]);

        // Send shipping notification email
        $this->sendShippingNotification($order);
    }

    protected function handleOrderDelivered(Order $order): void
    {
        $order->update(['fulfillment_status' => FulfillmentStatuses::DELIVERED]);

        // Send delivery confirmation email
        $this->sendDeliveryConfirmation($order);
    }

    protected function handleOrderCancelled(Order $order): void
    {
        // Cancel any active shipments
        $activeShipment = $order->getActiveShipment();
        if ($activeShipment && !$activeShipment->isShipped()) {
            $this->shippingService->cancelShipment($activeShipment, 'Order cancelled');
        }

        $order->update(['fulfillment_status' => FulfillmentStatuses::CANCELLED]);
    }

    protected function createShipmentForOrder(Order $order): Shipment
    {
        try {
            $shipment = $this->shippingService->createShipment($order, [
                'notes' => 'Auto-created during order processing',
                'auto_purchase_label' => $this->shouldAutoPurchaseLabel($order),
            ]);

            Log::info('Shipment created for order', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'auto_purchase_label' => $this->shouldAutoPurchaseLabel($order),
            ]);

            return $shipment;

        } catch (Exception $e) {
            Log::error('Failed to create shipment for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function processShipmentStatusChange(Shipment $shipment, string $newStatus, string $oldStatus = null): void
    {
        Log::info('Processing shipment status change', [
            'shipment_id' => $shipment->id,
            'order_id' => $shipment->order_id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        $order = $shipment->order;

        try {
            switch ($newStatus) {
                case ShippingStatuses::SHIPPED:
                    $this->handleShipmentShipped($shipment, $order);
                    break;
                case ShippingStatuses::IN_TRANSIT:
                    $this->handleShipmentInTransit($shipment, $order);
                    break;
                case ShippingStatuses::DELIVERED:
                    $this->handleShipmentDelivered($shipment, $order);
                    break;
                case ShippingStatuses::FAILED:
                case ShippingStatuses::RETURNED:
                    $this->handleShipmentFailed($shipment, $order);
                    break;
            }

            // Update overall fulfillment status
            $order->updateFulfillmentStatus();

        } catch (Exception $e) {
            Log::error('Failed to process shipment status change', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'new_status' => $newStatus,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function handleShipmentShipped(Shipment $shipment, Order $order): void
    {
        // Update order tracking number if not set
        if (!$order->tracking_number && $shipment->tracking_number) {
            $order->update(['tracking_number' => $shipment->tracking_number]);
        }

        // Update order status to shipped if not already
        $shippedStatusId = OrderStatus::where('name', OrderStatuses::SHIPPED)->value('id');
        if ($order->status_id !== $shippedStatusId) {
            $order->update(['status_id' => $shippedStatusId]);
        }

        // Send shipping notification
        $this->sendShippingNotification($order);
    }

    protected function handleShipmentInTransit(Shipment $shipment, Order $order): void
    {
        // Update to out for delivery if appropriate
        if ($this->isNearDelivery($shipment)) {
            $outForDeliveryStatusId = OrderStatus::where('name', OrderStatuses::OUT_FOR_DELIVERY)->value('id');
            $order->update(['status_id' => $outForDeliveryStatusId]);
        }
    }

    protected function handleShipmentDelivered(Shipment $shipment, Order $order): void
    {
        $deliveredStatusId = OrderStatus::where('name', OrderStatuses::DELIVERED)->value('id');
        $order->update(['status_id' => $deliveredStatusId]);

        // Send delivery confirmation
        $this->sendDeliveryConfirmation($order);
    }

    protected function handleShipmentFailed(Shipment $shipment, Order $order): void
    {
        $onHoldStatusId = OrderStatus::where('name', OrderStatuses::ON_HOLD)->value('id');
        $order->update(['status_id' => $onHoldStatusId]);

        if ($this->shouldSendShippingAlert($shipment->status)) {
            $this->sendShippingIssueNotification($order, $shipment);
        }
    }

    protected function shouldAutoTransitionToProcessing(Order $order): bool
    {
        // Auto-transition if all items are in stock and shipping info is complete
        return $order->shippingMethod &&
            $order->shippingAddress &&
            $this->allItemsInStock($order);
    }

    protected function allItemsInStock(Order $order): bool
    {
        foreach ($order->orderItems as $item) {
            $product = $item->product;

            if ($item->product_variant_id) {
                $variant = $item->productVariant;
                if (!$variant || $variant->quantity < $item->quantity) {
                    return false;
                }
            } else {
                if ($product->quantity < $item->quantity) {
                    return false;
                }
            }
        }

        return true;
    }

    protected function transitionOrderToProcessing(Order $order): void
    {
        $processingStatusId = OrderStatus::where('name', OrderStatuses::PROCESSING)->value('id');
        $order->update(['status_id' => $processingStatusId]);

        Log::info('Order auto-transitioned to processing', [
            'order_id' => $order->id,
        ]);
    }

    protected function shouldAutoPurchaseLabel(Order $order): bool
    {
        // You can add business logic here
        // For now, auto-purchase for orders over £50
        return $order->getTotalAmountInPounds() >= 50;
    }

    protected function isNearDelivery(Shipment $shipment): bool
    {
        if (!$shipment->estimated_delivery) {
            return false;
        }

        // Consider "near delivery" if within 24 hours of estimated delivery
        return now()->diffInHours($shipment->estimated_delivery) <= 24;
    }

    protected function sendShippingNotification(Order $order): void
    {
        try {
            $shipment = $order->getActiveShipment();
            if ($shipment) {
                $shippingData = $this->emailService->formatShippingData($shipment);
                $this->emailService->sendShippingConfirmation($shippingData, $order->user->email);
            }
        } catch (Exception $e) {
            Log::error('Failed to send shipping notification', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendDeliveryConfirmation(Order $order): void
    {
        try {
            $orderData = $this->emailService->formatOrderData($order);
            $this->emailService->sendDeliveryConfirmation($orderData, $order->user->email);
        } catch (Exception $e) {
            Log::error('Failed to send delivery confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendShippingIssueNotification(Order $order, Shipment $shipment): void
    {
        try {
            // Format the shipment data for the email
            $shipmentData = $this->emailService->formatShipmentData($shipment);

            // Get admin/customer service email(s)
            $adminEmails = $this->getAdminNotificationEmails();

            // Send to each admin email
            foreach ($adminEmails as $email) {
                $this->emailService->sendShippingIssueAlert($shipmentData, $email);
            }

            // Also send to customer service if different from admin
            $customerServiceEmail = config('mail.customer_service_email');
            if ($customerServiceEmail && !in_array($customerServiceEmail, $adminEmails)) {
                $this->emailService->sendShippingIssueAlert($shipmentData, $customerServiceEmail);
            }

            Log::info('Shipping issue notifications sent', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'shipment_status' => $shipment->status,
                'recipients_count' => count($adminEmails) + (isset($customerServiceEmail) ? 1 : 0)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send shipping issue notifications', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function getAdminNotificationEmails(): array
    {
        $emails = [];

        if ($adminEmail = config('mail.admin_email')) {
            $emails[] = $adminEmail;
        }

        if ($shippingEmail = config('mail.shipping_manager_email')) {
            $emails[] = $shippingEmail;
        }

        if ($operationsEmail = config('mail.operations_email')) {
            $emails[] = $operationsEmail;
        }

        if (empty($emails)) {
            $emails[] = config('mail.from.address');
        }

        return array_unique($emails);
    }

    protected function shouldSendShippingAlert(string $status): bool
    {
        return in_array($status, [
            ShippingStatuses::FAILED,
            ShippingStatuses::RETURNED,
            ShippingStatuses::EXCEPTION,
            ShippingStatuses::LOST,
            ShippingStatuses::DAMAGED,
        ]);
    }

    public function processOverdueShipments(): int
    {
        $overdueShipments = Shipment::where('estimated_delivery', '<', now())
            ->whereNotIn('status', [ShippingStatuses::DELIVERED, ShippingStatuses::CANCELLED])
            ->with('order')
            ->get();

        $processedCount = 0;

        foreach ($overdueShipments as $shipment) {
            try {
                $this->handleOverdueShipment($shipment);
                $processedCount++;
            } catch (Exception $e) {
                Log::error('Failed to process overdue shipment', [
                    'shipment_id' => $shipment->id,
                    'order_id' => $shipment->order_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processedCount;
    }

    protected function handleOverdueShipment(Shipment $shipment): void
    {
        try {
            $this->shippingService->updateTrackingStatus($shipment);
        } catch (Exception $e) {
            Log::warning('Could not update tracking for overdue shipment', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($shipment->estimated_delivery < now() && !$shipment->isDelivered()) {
            $this->sendDelayNotification($shipment);
        }
    }

    protected function sendDelayNotification(Shipment $shipment): void
    {
        try {
            $orderData = $this->emailService->formatOrderData($shipment->order);
            $this->emailService->sendShippingDelayNotification($orderData, $shipment->order->user->email);

            Log::info('Shipping delay notification sent', [
                'order_id' => $shipment->order_id,
                'shipment_id' => $shipment->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send delay notification', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

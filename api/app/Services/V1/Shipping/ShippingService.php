<?php

namespace App\Services\V1\Shipping;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingAddress;
use App\Constants\ShippingStatuses;
use App\Constants\FulfillmentStatuses;
use App\Mail\ShippingConfirmationMail;
use App\Mail\DeliveryConfirmationMail;
use App\Mail\ShippingDelayNotificationMail;
use App\Mail\TrackingUpdateMail;
use App\Mail\ShippingIssueAlertMail;
use App\Services\V1\Emails\Email;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingService
{
    protected ShippoProvider $shippoProvider;
    protected ShippingCalculator $calculator;
    protected Email $emailService;

    public function __construct(ShippoProvider $shippoProvider, ShippingCalculator $calculator, Email $emailService)
    {
        $this->shippoProvider = $shippoProvider;
        $this->calculator = $calculator;
        $this->emailService = $emailService;
    }

    public function createShipment(Order $order, array $options = []): Shipment
    {
        if (!$order->canShip()) {
            throw new Exception('Order cannot be shipped');
        }

        try {
            $shipment = $order->createShipment([
                'status' => ShippingStatuses::PROCESSING,
                'notes' => $options['notes'] ?? null,
            ]);

            if ($options['auto_purchase_label'] ?? false) {
                $this->purchaseLabel($shipment);
            }

            // Send processing notification if requested
            if ($options['send_processing_notification'] ?? false) {
                $this->sendProcessingNotification($shipment);
            }

            Log::info('Shipment created successfully', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'auto_purchase_label' => $options['auto_purchase_label'] ?? false,
            ]);

            return $shipment;

        } catch (Exception $e) {
            Log::error('Failed to create shipment', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            // Send failure notification to admins
            $this->sendShipmentCreationFailureAlert($order, $e->getMessage());

            throw new Exception('Failed to create shipment: ' . $e->getMessage());
        }
    }

    public function purchaseLabel(Shipment $shipment): array
    {
        try {
            $order = $shipment->order;
            $shippingAddress = $order->shippingAddress;
            $shippingMethod = $order->shippingMethod;

            if (!$shippingAddress || !$shippingMethod) {
                throw new Exception('Missing shipping address or method');
            }

            $labelData = $this->shippoProvider->createShipment([
                'address_from' => $this->getFromAddress($order),
                'address_to' => $shippingAddress->toShippoFormat(),
                'parcels' => $this->buildParcels($order),
                'shipment_method' => $shippingMethod->service_code ?? $shippingMethod->name,
                'carrier' => $shippingMethod->carrier,
                'metadata' => [
                    'order_id' => $order->id,
                    'shipment_id' => $shipment->id,
                ],
            ]);

            $shipment->update([
                'tracking_number' => $labelData['tracking_number'],
                'label_url' => $labelData['label_url'],
                'tracking_url' => $labelData['tracking_url'],
                'carrier_data' => $labelData,
                'status' => ShippingStatuses::READY_TO_SHIP,
            ]);

            Log::info('Shipping label purchased successfully', [
                'shipment_id' => $shipment->id,
                'tracking_number' => $labelData['tracking_number'],
                'carrier' => $shippingMethod->carrier,
            ]);

            return $labelData;

        } catch (Exception $e) {
            $shipment->update(['status' => ShippingStatuses::FAILED]);

            Log::error('Failed to purchase shipping label', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            // Send label purchase failure alert
            $this->sendLabelPurchaseFailureAlert($shipment, $e->getMessage());

            throw new Exception('Failed to purchase label: ' . $e->getMessage());
        }
    }

    public function shipOrder(Order $order, array $options = []): Shipment
    {
        $shipment = $this->createShipment($order, $options);

        if ($options['purchase_label'] ?? true) {
            $this->purchaseLabel($shipment);
        }

        $this->markAsShipped($shipment, $options);

        return $shipment;
    }

    public function markAsShipped(Shipment $shipment, array $options = []): void
    {
        $trackingNumber = $options['tracking_number'] ?? $shipment->tracking_number;

        if (!$trackingNumber) {
            throw new Exception('Tracking number is required to mark as shipped');
        }

        try {
            $shipment->markAsShipped($trackingNumber, $options['label_url'] ?? null);

            // Send shipping confirmation email
            if ($options['send_notification'] ?? true) {
                $this->sendShippingConfirmation($shipment);
            }

            Log::info('Order marked as shipped', [
                'order_id' => $shipment->order_id,
                'shipment_id' => $shipment->id,
                'tracking_number' => $trackingNumber,
                'notification_sent' => $options['send_notification'] ?? true,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to mark shipment as shipped', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            // Send failure alert
            $this->sendShippingMarkFailureAlert($shipment, $e->getMessage());
            throw $e;
        }
    }

    public function markAsDelivered(Shipment $shipment, array $options = []): void
    {
        try {
            $shipment->update([
                'status' => ShippingStatuses::DELIVERED,
                'delivered_at' => $options['delivered_at'] ?? now(),
            ]);

            // Send delivery confirmation email
            if ($options['send_notification'] ?? true) {
                $this->sendDeliveryConfirmation($shipment);
            }

            Log::info('Shipment marked as delivered', [
                'shipment_id' => $shipment->id,
                'order_id' => $shipment->order_id,
                'delivered_at' => $shipment->delivered_at,
                'notification_sent' => $options['send_notification'] ?? true,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to mark shipment as delivered', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to mark as delivered: ' . $e->getMessage());
        }
    }

    public function reportShippingDelay(Shipment $shipment, array $delayInfo): void
    {
        try {
            $shipment->update([
                'carrier_data' => array_merge($shipment->carrier_data ?? [], [
                    'delay_reported' => now()->toISOString(),
                    'delay_reason' => $delayInfo['reason'] ?? 'Unknown',
                    'original_estimated_delivery' => $delayInfo['original_delivery'] ?? null,
                    'new_estimated_delivery' => $delayInfo['new_delivery'] ?? null,
                ]),
                'estimated_delivery' => $delayInfo['new_delivery'] ?? $shipment->estimated_delivery,
            ]);

            // Send delay notification email
            $this->sendShippingDelayNotification($shipment, $delayInfo);

            Log::info('Shipping delay reported', [
                'shipment_id' => $shipment->id,
                'order_id' => $shipment->order_id,
                'delay_reason' => $delayInfo['reason'] ?? 'Unknown',
                'days_delayed' => $delayInfo['days_delayed'] ?? null,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to report shipping delay', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to report delay: ' . $e->getMessage());
        }
    }

    public function reportShippingIssue(Shipment $shipment, array $issueData): void
    {
        try {
            $issueType = $issueData['type'] ?? 'unknown';
            $severity = $issueData['severity'] ?? 'medium';

            $shipment->update([
                'status' => $this->getStatusForIssueType($issueType),
                'carrier_data' => array_merge($shipment->carrier_data ?? [], [
                    'issue_reported' => now()->toISOString(),
                    'issue_type' => $issueType,
                    'issue_severity' => $severity,
                    'issue_description' => $issueData['description'] ?? '',
                ]),
            ]);

            // Send issue alert
            $this->sendShippingIssueAlert($shipment, $issueData);

            Log::warning('Shipping issue reported', [
                'shipment_id' => $shipment->id,
                'order_id' => $shipment->order_id,
                'issue_type' => $issueType,
                'severity' => $severity,
                'description' => $issueData['description'] ?? '',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to report shipping issue', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to report issue: ' . $e->getMessage());
        }
    }

    public function updateTrackingStatus(Shipment $shipment): array
    {
        try {
            if (!$shipment->tracking_number) {
                throw new Exception('No tracking number available');
            }

            $trackingData = $this->shippoProvider->getTrackingInfo($shipment->tracking_number);

            $statusMapping = [
                'UNKNOWN' => ShippingStatuses::PENDING,
                'PRE_TRANSIT' => ShippingStatuses::PROCESSING,
                'TRANSIT' => ShippingStatuses::IN_TRANSIT,
                'DELIVERED' => ShippingStatuses::DELIVERED,
                'RETURNED' => ShippingStatuses::RETURNED,
                'FAILURE' => ShippingStatuses::FAILED,
            ];

            $oldStatus = $shipment->status;
            $newStatus = $statusMapping[$trackingData['status']] ?? $shipment->status;

            $updateData = [
                'status' => $newStatus,
                'carrier_data' => array_merge($shipment->carrier_data ?? [], [
                    'tracking_history' => $trackingData['tracking_history'] ?? [],
                    'last_updated' => now()->toISOString(),
                ]),
            ];

            if ($newStatus === ShippingStatuses::DELIVERED && !$shipment->delivered_at) {
                $updateData['delivered_at'] = $trackingData['delivered_at'] ?? now();
            }

            $shipment->update($updateData);

            // Send appropriate notifications based on status change
            if ($oldStatus !== $newStatus) {
                $this->handleStatusChangeNotifications($shipment, $oldStatus, $newStatus, $trackingData);
            }

            Log::info('Tracking status updated', [
                'shipment_id' => $shipment->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'tracking_number' => $shipment->tracking_number,
            ]);

            return $trackingData;

        } catch (Exception $e) {
            Log::error('Failed to update tracking status', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            // Send tracking update failure alert
            $this->sendTrackingUpdateFailureAlert($shipment, $e->getMessage());

            throw new Exception('Failed to update tracking: ' . $e->getMessage());
        }
    }

    public function cancelShipment(Shipment $shipment, string $reason = ''): void
    {
        try {
            if ($shipment->isShipped()) {
                throw new Exception('Cannot cancel shipped order');
            }

            if ($shipment->hasLabel()) {
                $this->shippoProvider->cancelShipment($shipment->carrier_data['shipment_id'] ?? null);
            }

            $shipment->update([
                'status' => ShippingStatuses::CANCELLED,
                'notes' => $shipment->notes . "\nCancelled: " . $reason,
            ]);

            $shipment->order->update(['fulfillment_status' => FulfillmentStatuses::UNFULFILLED]);

            // Send cancellation notification
            $this->sendShipmentCancellationNotification($shipment, $reason);

            Log::info('Shipment cancelled', [
                'shipment_id' => $shipment->id,
                'reason' => $reason,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to cancel shipment', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to cancel shipment: ' . $e->getMessage());
        }
    }

    public function getRealTimeRates(ShippingAddress $fromAddress, ShippingAddress $toAddress, array $parcels): array
    {
        try {
            return $this->shippoProvider->getRates([
                'address_from' => $fromAddress->toShippoFormat(),
                'address_to' => $toAddress->toShippoFormat(),
                'parcels' => $parcels,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get real-time rates', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function validateAddress(ShippingAddress $address): array
    {
        try {
            return $this->shippoProvider->validateAddress($address->toShippoFormat());

        } catch (Exception $e) {
            Log::error('Failed to validate address', [
                'address_id' => $address->id,
                'error' => $e->getMessage(),
            ]);

            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    // Mail notification methods

    protected function sendShippingConfirmation(Shipment $shipment): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            if (!$user || !$user->email) {
                Log::warning('Cannot send shipping confirmation - no user email', [
                    'shipment_id' => $shipment->id,
                    'order_id' => $order->id,
                ]);
                return;
            }

            $emailData = $this->emailService->formatShippingData($shipment);

            Mail::to($user->email)->send(new ShippingConfirmationMail($emailData));

            Log::info('Shipping confirmation email sent', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'customer_email' => $user->email,
                'tracking_number' => $shipment->tracking_number,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send shipping confirmation email', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendDeliveryConfirmation(Shipment $shipment): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            if (!$user || !$user->email) {
                Log::warning('Cannot send delivery confirmation - no user email', [
                    'shipment_id' => $shipment->id,
                    'order_id' => $order->id,
                ]);
                return;
            }

            $emailData = $this->emailService->formatShippingData($shipment);

            Mail::to($user->email)->send(new DeliveryConfirmationMail($emailData));

            Log::info('Delivery confirmation email sent', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'customer_email' => $user->email,
                'delivered_at' => $shipment->delivered_at,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send delivery confirmation email', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendShippingDelayNotification(Shipment $shipment, array $delayInfo): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            if (!$user || !$user->email) {
                Log::warning('Cannot send delay notification - no user email', [
                    'shipment_id' => $shipment->id,
                    'order_id' => $order->id,
                ]);
                return;
            }

            $emailData = $this->emailService->formatDelayData($shipment, $delayInfo['reason'] ?? null, $delayInfo['new_delivery'] ?? null);

            Mail::to($user->email)->send(new ShippingDelayNotificationMail($emailData));

            Log::info('Shipping delay notification sent', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'customer_email' => $user->email,
                'delay_reason' => $delayInfo['reason'] ?? 'Unknown',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send shipping delay notification', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendTrackingUpdate(Shipment $shipment, array $trackingData): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            if (!$user || !$user->email) {
                Log::warning('Cannot send tracking update - no user email', [
                    'shipment_id' => $shipment->id,
                    'order_id' => $order->id,
                ]);
                return;
            }

            $emailData = $this->emailService->formatTrackingData($shipment, $trackingData);

            Mail::to($user->email)->send(new TrackingUpdateMail($emailData));

            Log::info('Tracking update email sent', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'customer_email' => $user->email,
                'tracking_status' => $trackingData['status'] ?? 'unknown',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send tracking update email', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendShippingIssueAlert(Shipment $shipment, array $issueData): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            // Send to customer if email exists
            if ($user && $user->email) {
                $emailData = $this->emailService->formatShipmentData($shipment);
                $emailData['issue'] = $issueData;

                Mail::to($user->email)->send(new ShippingIssueAlertMail($emailData));

                Log::info('Shipping issue alert sent to customer', [
                    'shipment_id' => $shipment->id,
                    'customer_email' => $user->email,
                    'issue_type' => $issueData['type'] ?? 'unknown',
                ]);
            }

            // Send to admins for high severity issues
            if (($issueData['severity'] ?? 'medium') === 'high') {
                $this->sendShippingIssueAdminAlert($shipment, $issueData);
            }

        } catch (Exception $e) {
            Log::error('Failed to send shipping issue alert', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendShippingIssueAdminAlert(Shipment $shipment, array $issueData): void
    {
        try {
            $adminEmails = $this->getAdminEmails();

            if (empty($adminEmails)) {
                Log::warning('No admin emails configured for shipping issue alerts');
                return;
            }

            $emailData = $this->emailService->formatShipmentData($shipment);
            $emailData['issue'] = $issueData;
            $emailData['admin_alert'] = true;

            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new ShippingIssueAlertMail($emailData));
            }

            Log::info('Shipping issue admin alert sent', [
                'shipment_id' => $shipment->id,
                'admin_count' => count($adminEmails),
                'issue_type' => $issueData['type'] ?? 'unknown',
                'severity' => $issueData['severity'] ?? 'unknown',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send shipping issue admin alert', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendProcessingNotification(Shipment $shipment): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            if (!$user || !$user->email) {
                return;
            }

            $emailData = $this->emailService->formatShippingData($shipment);
            $emailData['status_message'] = 'Your order is being prepared for shipment';

            // Use tracking update mail for processing notifications
            Mail::to($user->email)->send(new TrackingUpdateMail($emailData));

            Log::info('Processing notification sent', [
                'shipment_id' => $shipment->id,
                'customer_email' => $user->email,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send processing notification', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendShipmentCreationFailureAlert(Order $order, string $errorMessage): void
    {
        try {
            $adminEmails = $this->getAdminEmails();

            if (empty($adminEmails)) {
                return;
            }

            $emailData = [
                'order' => [
                    'id' => $order->id,
                    'total_formatted' => $order->getTotalFormattedAttribute(),
                    'created_at' => $order->created_at->format('M j, Y g:i A'),
                ],
                'customer' => [
                    'name' => $order->user->name ?? 'Guest',
                    'email' => $order->user->email ?? 'N/A',
                ],
                'shipment' => [
                    'status' => 'creation_failed',
                    'priority_level' => 'high',
                ],
                'error' => [
                    'type' => 'shipment_creation_failure',
                    'message' => $errorMessage,
                    'occurred_at' => now()->format('M j, Y g:i A'),
                ],
            ];

            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new ShippingIssueAlertMail($emailData));
            }

            Log::info('Shipment creation failure alert sent to admins', [
                'order_id' => $order->id,
                'admin_count' => count($adminEmails),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send shipment creation failure alert', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendLabelPurchaseFailureAlert(Shipment $shipment, string $errorMessage): void
    {
        try {
            $adminEmails = $this->getAdminEmails();

            if (empty($adminEmails)) {
                return;
            }

            $emailData = $this->emailService->formatShipmentData($shipment);
            $emailData['error'] = [
                'type' => 'label_purchase_failure',
                'message' => $errorMessage,
                'occurred_at' => now()->format('M j, Y g:i A'),
            ];
            $emailData['shipment']['priority_level'] = 'high';

            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new ShippingIssueAlertMail($emailData));
            }

            Log::info('Label purchase failure alert sent to admins', [
                'shipment_id' => $shipment->id,
                'admin_count' => count($adminEmails),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send label purchase failure alert', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendShippingMarkFailureAlert(Shipment $shipment, string $errorMessage): void
    {
        try {
            $adminEmails = $this->getAdminEmails();

            if (empty($adminEmails)) {
                return;
            }

            $emailData = $this->emailService->formatShipmentData($shipment);
            $emailData['error'] = [
                'type' => 'shipping_mark_failure',
                'message' => $errorMessage,
                'occurred_at' => now()->format('M j, Y g:i A'),
            ];
            $emailData['shipment']['priority_level'] = 'medium';

            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new ShippingIssueAlertMail($emailData));
            }

            Log::info('Shipping mark failure alert sent to admins', [
                'shipment_id' => $shipment->id,
                'admin_count' => count($adminEmails),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send shipping mark failure alert', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendTrackingUpdateFailureAlert(Shipment $shipment, string $errorMessage): void
    {
        try {
            $adminEmails = $this->getAdminEmails();

            if (empty($adminEmails)) {
                return;
            }

            $emailData = $this->emailService->formatShipmentData($shipment);
            $emailData['error'] = [
                'type' => 'tracking_update_failure',
                'message' => $errorMessage,
                'occurred_at' => now()->format('M j, Y g:i A'),
            ];
            $emailData['shipment']['priority_level'] = 'low';

            foreach ($adminEmails as $adminEmail) {
                Mail::to($adminEmail)->send(new ShippingIssueAlertMail($emailData));
            }

            Log::info('Tracking update failure alert sent to admins', [
                'shipment_id' => $shipment->id,
                'admin_count' => count($adminEmails),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send tracking update failure alert', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendShipmentCancellationNotification(Shipment $shipment, string $reason): void
    {
        try {
            $order = $shipment->order;
            $user = $order->user;

            if (!$user || !$user->email) {
                return;
            }

            $emailData = $this->emailService->formatShipmentData($shipment);
            $emailData['cancellation'] = [
                'reason' => $reason,
                'cancelled_at' => now()->format('M j, Y g:i A'),
                'refund_info' => 'If applicable, refunds will be processed within 5-7 business days.',
            ];

            // Use shipping issue alert for cancellation notifications
            Mail::to($user->email)->send(new ShippingIssueAlertMail($emailData));

            Log::info('Shipment cancellation notification sent', [
                'shipment_id' => $shipment->id,
                'customer_email' => $user->email,
                'reason' => $reason,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send shipment cancellation notification', [
                'shipment_id' => $shipment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // Helper methods

    protected function handleStatusChangeNotifications(Shipment $shipment, string $oldStatus, string $newStatus, array $trackingData): void
    {
        // Send tracking update for significant status changes
        if ($this->shouldSendTrackingEmail($oldStatus, $newStatus)) {
            $this->sendTrackingUpdate($shipment, $trackingData);
        }

        // Send delivery confirmation
        if ($newStatus === ShippingStatuses::DELIVERED) {
            $this->sendDeliveryConfirmation($shipment);
        }

        // Send issue alerts for problem statuses
        if (in_array($newStatus, [ShippingStatuses::FAILED, ShippingStatuses::EXCEPTION, ShippingStatuses::RETURNED])) {
            $this->sendShippingIssueAlert($shipment, [
                'type' => $newStatus,
                'severity' => 'high',
                'description' => "Shipment status changed to {$newStatus}",
                'tracking_data' => $trackingData,
            ]);
        }
    }

    protected function shouldSendTrackingEmail(string $oldStatus, string $newStatus): bool
    {
        $emailableStatuses = [
            ShippingStatuses::IN_TRANSIT,
            ShippingStatuses::OUT_FOR_DELIVERY,
            ShippingStatuses::EXCEPTION,
            ShippingStatuses::FAILED
        ];

        return in_array($newStatus, $emailableStatuses) && $oldStatus !== $newStatus;
    }

    protected function getStatusForIssueType(string $issueType): string
    {
        return match($issueType) {
            'failed', 'lost', 'damaged' => ShippingStatuses::FAILED,
            'returned' => ShippingStatuses::RETURNED,
            'delayed' => ShippingStatuses::IN_TRANSIT,
            'exception' => ShippingStatuses::EXCEPTION,
            default => ShippingStatuses::EXCEPTION,
        };
    }

    protected function getAdminEmails(): array
    {
        // This should be configured in your environment or database
        return config('mail.admin_emails', []);
    }

    protected function getFromAddress(Order $order): array
    {
        $vendor = $order->orderItems->first()?->product?->vendor;

        return [
            'name' => $vendor?->name ?? config('app.name'),
            'company' => $vendor?->name ?? config('app.name'),
            'street1' => config('shipping.from_address.line1'),
            'street2' => config('shipping.from_address.line2', ''),
            'city' => config('shipping.from_address.city'),
            'state' => config('shipping.from_address.county', ''),
            'zip' => config('shipping.from_address.postcode'),
            'country' => config('shipping.from_address.country', 'GB'),
            'phone' => config('shipping.from_address.phone', ''),
            'email' => config('shipping.from_address.email', config('mail.from.address')),
        ];
    }

    protected function buildParcels(Order $order): array
    {
        $totalWeight = $order->getShippingWeight();
        $dimensions = $this->calculateOrderDimensions($order);

        return [[
            'length' => $dimensions['length'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'distance_unit' => 'cm',
            'weight' => $totalWeight,
            'mass_unit' => 'kg',
        ]];
    }

    protected function calculateOrderDimensions(Order $order): array
    {
        $maxLength = 0;
        $maxWidth = 0;
        $totalHeight = 0;

        foreach ($order->orderItems as $item) {
            $product = $item->product;
            $quantity = $item->quantity;

            $maxLength = max($maxLength, $product->length ?? 0);
            $maxWidth = max($maxWidth, $product->width ?? 0);
            $totalHeight += ($product->height ?? 0) * $quantity;
        }

        return [
            'length' => max($maxLength, 10),
            'width' => max($maxWidth, 10),
            'height' => max($totalHeight, 5),
        ];
    }
}

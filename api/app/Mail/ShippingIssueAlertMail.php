<?php

namespace App\Mail;

class ShippingIssueAlertMail extends BaseSystemMail
{
    protected string $templateName = 'shipping-issue-alert';

    protected function getSubject(): string
    {
        $orderId = $this->emailData['order']['id'];
        $shipmentId = $this->emailData['shipment']['id'];
        $status = $this->emailData['shipment']['status'];
        $priority = $this->emailData['shipment']['priority_level'];

        $priorityPrefix = match ($priority) {
            'high' => '🚨 URGENT',
            'medium' => '⚠️',
            'low' => '📦',
            default => '⚠️'
        };

        $statusText = match ($status) {
            'failed' => 'Delivery Failed',
            'returned' => 'Package Returned',
            'exception' => 'Shipping Exception',
            'lost' => 'Package Lost',
            'damaged' => 'Package Damaged',
            'delayed' => 'Shipment Delayed',
            default => 'Shipping Issue'
        };

        return "{$priorityPrefix} {$statusText} - Order #{$orderId} (Shipment #{$shipmentId})";
    }
}

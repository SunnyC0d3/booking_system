<?php

namespace App\Mail;

class SupplierPerformanceAlertMail extends BaseSystemMail
{
    protected string $templateName = 'supplier-performance-alert';

    protected function getSubject(): string
    {
        $supplierName = $this->emailData['supplier']['name'];
        $metric = $this->emailData['performance']['metric'];
        return "⚠️ Supplier Performance Alert - {$supplierName} - {$metric}";
    }
}

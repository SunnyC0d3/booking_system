<?php

namespace App\Mail;

class DropshipSupplierAlertMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-supplier-alert';

    protected function getSubject(): string
    {
        $status = $this->emailData['issue']['severity'];
        $supplierName = $this->emailData['supplier']['name'];

        $prefix = match ($status) {
            'high' => '🚨 URGENT',
            'medium' => '⚠️',
            'low' => '📋',
            default => '⚠️'
        };

        return "{$prefix} Dropship Supplier Issue - {$supplierName}";
    }
}

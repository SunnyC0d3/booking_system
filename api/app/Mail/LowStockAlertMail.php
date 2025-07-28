<?php

namespace App\Mail;
class LowStockAlertMail extends BaseSystemMail
{
    protected string $templateName = 'low-stock-alert';

    protected function getSubject(): string
    {
        $count = $this->emailData['total_items'];
        return "Inventory Alert: {$count} items need restocking";
    }
}

<?php

namespace App\Mail;

class SupplierOrderNotification extends BaseSystemMail
{
    protected string $templateName = 'supplier-order-notification';

    protected function getSubject(): string
    {
        return "New Dropship Order #{$this->emailData['dropship_order']['id']} - Action Required";
    }
}

<?php

namespace App\Mail;

class DropshipOrderConfirmedMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-order-confirmed';

    protected function getSubject(): string
    {
        return "Dropship Order Confirmed - #{$this->emailData['dropship_order']['id']}";
    }
}

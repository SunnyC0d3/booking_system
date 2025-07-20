<?php

namespace App\Mail;

class DropshipOrderRejectedMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-order-rejected';

    protected function getSubject(): string
    {
        return "Order Issue - Supplier Unable to Fulfill - Order #{$this->emailData['order']['id']}";
    }
}

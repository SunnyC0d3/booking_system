<?php

namespace App\Mail;

class OrderConfirmationMail extends BaseSystemMail
{
    protected string $templateName = 'order-confirmation';

    protected function getSubject(): string
    {
        return "Order Confirmation #{$this->emailData['order']['id']}";
    }
}

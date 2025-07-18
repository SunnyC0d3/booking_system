<?php

namespace App\Mail;

class ShippingConfirmationMail extends BaseSystemMail
{
    protected string $templateName = 'shipping-confirmation';

    protected function getSubject(): string
    {
        return "Your order has shipped! - Order #{$this->emailData['order']['id']}";
    }
}

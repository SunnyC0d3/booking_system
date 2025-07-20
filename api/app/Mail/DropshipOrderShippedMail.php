<?php

namespace App\Mail;

class DropshipOrderShippedMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-order-shipped';

    protected function getSubject(): string
    {
        return "Your order has shipped via dropshipping - Order #{$this->emailData['order']['id']}";
    }
}

<?php

namespace App\Mail;

class ShippingDelayNotificationMail extends BaseSystemMail
{
    protected string $templateName = 'shipping-delay-notification';

    protected function getSubject(): string
    {
        return "Shipping Update - Order #{$this->emailData['order']['id']}";
    }
}

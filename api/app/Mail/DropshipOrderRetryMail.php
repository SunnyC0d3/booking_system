<?php

namespace App\Mail;

class DropshipOrderRetryMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-order-retry';

    protected function getSubject(): string
    {
        $attempt = $this->emailData['retry']['attempt'];
        return "Order Retry Attempt #{$attempt} - Order #{$this->emailData['order']['id']}";
    }
}

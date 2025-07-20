<?php

namespace App\Mail;

class DropshipOrderDelayedMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-order-delayed';

    protected function getSubject(): string
    {
        return "Delivery Update - Order #{$this->emailData['order']['id']}";
    }
}

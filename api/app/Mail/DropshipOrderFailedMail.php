<?php

namespace App\Mail;

class DropshipOrderFailedMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-order-failed';

    protected function getSubject(): string
    {
        return "URGENT: Dropship Order #{$this->emailData['dropship_order']['id']} Failed";
    }
}

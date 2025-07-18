<?php

namespace App\Mail;

class DeliveryConfirmationMail extends BaseSystemMail
{
    protected string $templateName = 'delivery-confirmation';

    protected function getSubject(): string
    {
        return "Package delivered! - Order #{$this->emailData['order']['id']}";
    }
}

<?php

namespace App\Mail;

class RefundProcessedMail extends BaseSystemMail
{
    protected string $templateName = 'refund-processed';

    protected function getSubject(): string
    {
        $orderId = $this->emailData['order']['id'];
        return "Refund Processed - Order #{$orderId}";
    }
}

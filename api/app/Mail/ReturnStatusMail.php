<?php

namespace App\Mail;

class ReturnStatusMail extends BaseSystemMail
{
    protected string $templateName = 'return-status';

    protected function getSubject(): string
    {
        $status = $this->emailData['return']['status'];
        $orderId = $this->emailData['order']['id'];

        return match ($status) {
            'Approved' => "Return Request Approved - Order #{$orderId}",
            'Rejected' => "Return Request Update - Order #{$orderId}",
            default => "Return Request Update - Order #{$orderId}"
        };
    }
}

<?php

namespace App\Mail;

class PaymentStatusMail extends BaseSystemMail
{
    protected string $templateName = 'payment-status';

    protected function getSubject(): string
    {
        $status = $this->emailData['payment']['status'];
        $orderId = $this->emailData['order']['id'];

        return match ($status) {
            'failed' => "Payment Failed - Order #{$orderId}",
            'succeeded' => "Payment Confirmed - Order #{$orderId}",
            default => "Payment Update - Order #{$orderId}"
        };
    }
}

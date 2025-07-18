<?php

namespace App\Mail;

class TrackingUpdateMail extends BaseSystemMail
{
    protected string $templateName = 'tracking-update';

    protected function getSubject(): string
    {
        $status = $this->emailData['tracking']['status_label'] ?? 'Update';
        return "Tracking Update: {$status} - Order #{$this->emailData['order']['id']}";
    }
}

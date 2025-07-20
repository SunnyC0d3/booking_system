<?php

namespace App\Mail;

class DropshipIntegrationFailedMail extends BaseSystemMail
{
    protected string $templateName = 'dropship-integration-failed';

    protected function getSubject(): string
    {
        $supplierName = $this->emailData['supplier']['name'];
        return "🚨 Dropship Integration Failed - {$supplierName}";
    }
}

<?php

namespace App\Mail;

class SupplierIntegrationFailedMail extends BaseSystemMail
{
    protected string $templateName = 'supplier-integration-failed';

    protected function getSubject(): string
    {
        return "Supplier Integration Failure: {$this->emailData['supplier']['name']}";
    }
}

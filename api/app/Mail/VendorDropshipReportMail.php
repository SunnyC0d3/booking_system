<?php

namespace App\Mail;

class VendorDropshipReportMail extends BaseSystemMail
{
    protected string $templateName = 'vendor-dropship-report';

    protected function getSubject(): string
    {
        $period = $this->emailData['report']['period'];
        return "Weekly Dropshipping Report - {$period} - {$this->emailData['vendor']['name']}";
    }
}

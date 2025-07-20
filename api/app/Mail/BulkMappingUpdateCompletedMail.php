<?php

namespace App\Mail;

class BulkMappingUpdateCompletedMail extends BaseSystemMail
{
    protected string $templateName = 'bulk-mapping-update-completed';

    protected function getSubject(): string
    {
        $operationType = ucfirst(str_replace('_', ' ', $this->emailData['operation']['type']));
        return "Bulk {$operationType} Completed - {$this->emailData['operation']['affected_count']} Mappings Affected";
    }
}

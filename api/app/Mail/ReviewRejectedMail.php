<?php

namespace App\Mail;

class ReviewRejectedMail extends BaseSystemMail
{
    protected string $templateName = 'review-rejected';

    protected function getSubject(): string
    {
        return "Review Update - {$this->emailData['review']['product']['name']}";
    }
}

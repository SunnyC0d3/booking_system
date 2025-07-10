<?php

namespace App\Mail;

class ReviewReportedMail extends BaseSystemMail
{
    protected string $templateName = 'review-reported';

    protected function getSubject(): string
    {
        return "Review Reported - Action Required";
    }
}

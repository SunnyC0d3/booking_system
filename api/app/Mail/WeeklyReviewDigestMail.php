<?php

namespace App\Mail;

class WeeklyReviewDigestMail extends BaseSystemMail
{
    protected string $templateName = 'weekly-review-digest';

    protected function getSubject(): string
    {
        $count = count($this->emailData['reviews']);
        return "Weekly Review Digest - {$count} new reviews for {$this->emailData['vendor']['name']}";
    }
}

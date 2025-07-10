<?php

namespace App\Mail;

class ReviewApprovedMail extends BaseSystemMail
{
    protected string $templateName = 'review-approved';

    protected function getSubject(): string
    {
        return "Your review has been approved!";
    }
}

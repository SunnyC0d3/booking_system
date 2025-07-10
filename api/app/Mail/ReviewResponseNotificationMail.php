<?php

namespace App\Mail;

class ReviewResponseNotificationMail extends BaseSystemMail
{
    protected string $templateName = 'review-response-notification';

    protected function getSubject(): string
    {
        return "{$this->emailData['response']['vendor']['name']} responded to your review";
    }
}

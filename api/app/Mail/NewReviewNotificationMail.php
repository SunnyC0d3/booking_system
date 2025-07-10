<?php

namespace App\Mail;

class NewReviewNotificationMail extends BaseSystemMail
{
    protected string $templateName = 'new-review-notification';

    protected function getSubject(): string
    {
        $rating = $this->emailData['review']['rating'];
        $stars = str_repeat('⭐', $rating);
        return "New {$rating}-star review for {$this->emailData['review']['product']['name']} {$stars}";
    }
}

<?php

namespace App\Mail;

class ReviewHelpfulMail extends BaseSystemMail
{
    protected string $templateName = 'review-helpful';

    protected function getSubject(): string
    {
        $votes = $this->emailData['review']['helpful_votes'];
        return "Your review reached {$votes} helpful votes! 👍";
    }
}

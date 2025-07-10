<?php

namespace App\Mail;

class ReviewFeaturedMail extends BaseSystemMail
{
    protected string $templateName = 'review-featured';

    protected function getSubject(): string
    {
        return "Your review has been featured! 🌟";
    }
}

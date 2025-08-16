<?php

namespace App\Constants;

class BookingSources
{
    public const WEBSITE = 'website';
    public const PHONE = 'phone';
    public const EMAIL = 'email';
    public const REFERRAL = 'referral';
    public const WALK_IN = 'walk_in';
    public const SOCIAL_MEDIA = 'social_media';

    public const ALL = [
        self::WEBSITE,
        self::PHONE,
        self::EMAIL,
        self::REFERRAL,
        self::WALK_IN,
        self::SOCIAL_MEDIA,
    ];
}

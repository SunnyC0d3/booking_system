<?php

namespace App\Constants;

class CalendarProviders
{
    public const GOOGLE = 'google';
    public const OUTLOOK = 'outlook';
    public const APPLE = 'apple';
    public const ICAL = 'ical';

    public const ALL = [
        self::GOOGLE,
        self::OUTLOOK,
        self::APPLE,
        self::ICAL,
    ];

    public static function getDisplayName(string $provider): string
    {
        return match ($provider) {
            self::GOOGLE => 'Google Calendar',
            self::OUTLOOK => 'Microsoft Outlook',
            self::APPLE => 'Apple Calendar',
            self::ICAL => 'iCal',
            default => ucfirst($provider)
        };
    }
}

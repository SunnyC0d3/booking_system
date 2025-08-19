<?php

namespace App\Constants;

class ConsultationFormats
{
    public const PHONE = 'phone';
    public const VIDEO = 'video';
    public const IN_PERSON = 'in_person';
    public const SITE_VISIT = 'site_visit';

    public const ALL = [
        self::PHONE,
        self::VIDEO,
        self::IN_PERSON,
        self::SITE_VISIT,
    ];

    public const VIRTUAL = [
        self::PHONE,
        self::VIDEO,
    ];

    public const PHYSICAL = [
        self::IN_PERSON,
        self::SITE_VISIT,
    ];

    public static function getDisplayName(string $format): string
    {
        return match ($format) {
            self::PHONE => 'Phone Call',
            self::VIDEO => 'Video Call',
            self::IN_PERSON => 'In-Person Meeting',
            self::SITE_VISIT => 'Site Visit',
            default => ucfirst(str_replace('_', ' ', $format))
        };
    }

    public static function getIcon(string $format): string
    {
        return match ($format) {
            self::PHONE => 'phone',
            self::VIDEO => 'video',
            self::IN_PERSON => 'user',
            self::SITE_VISIT => 'map-pin',
            default => 'calendar'
        };
    }

    public static function requiresLocation(string $format): bool
    {
        return in_array($format, self::PHYSICAL);
    }

    public static function requiresMeetingLink(string $format): bool
    {
        return $format === self::VIDEO;
    }
}

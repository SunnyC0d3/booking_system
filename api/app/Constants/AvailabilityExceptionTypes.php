<?php

namespace App\Constants;

class AvailabilityExceptionTypes
{
    public const BLOCKED = 'blocked';
    public const CUSTOM_HOURS = 'custom_hours';
    public const SPECIAL_PRICING = 'special_pricing';

    public const ALL = [
        self::BLOCKED,
        self::CUSTOM_HOURS,
        self::SPECIAL_PRICING,
    ];

    public static function getDisplayName(string $type): string
    {
        return match ($type) {
            self::BLOCKED => 'Blocked',
            self::CUSTOM_HOURS => 'Custom Hours',
            self::SPECIAL_PRICING => 'Special Pricing',
            default => ucfirst(str_replace('_', ' ', $type))
        };
    }
}

<?php

namespace App\Constants;

class ServiceAvailabilityTypes
{
    public const REGULAR = 'regular';
    public const EXCEPTION = 'exception';
    public const SPECIAL_HOURS = 'special_hours';
    public const BLOCKED = 'blocked';

    public const ALL = [
        self::REGULAR,
        self::EXCEPTION,
        self::SPECIAL_HOURS,
        self::BLOCKED,
    ];
}

<?php

namespace App\Constants;

class ServiceAvailabilityPatterns
{
    public const WEEKLY = 'weekly';
    public const DAILY = 'daily';
    public const DATE_RANGE = 'date_range';
    public const SPECIFIC_DATE = 'specific_date';

    public const ALL = [
        self::WEEKLY,
        self::DAILY,
        self::DATE_RANGE,
        self::SPECIFIC_DATE,
    ];
}

<?php

namespace App\Constants;

class DaysOfWeek
{
    public const SUNDAY = 0;
    public const MONDAY = 1;
    public const TUESDAY = 2;
    public const WEDNESDAY = 3;
    public const THURSDAY = 4;
    public const FRIDAY = 5;
    public const SATURDAY = 6;

    public const ALL = [
        self::SUNDAY => 'Sunday',
        self::MONDAY => 'Monday',
        self::TUESDAY => 'Tuesday',
        self::WEDNESDAY => 'Wednesday',
        self::THURSDAY => 'Thursday',
        self::FRIDAY => 'Friday',
        self::SATURDAY => 'Saturday',
    ];

    public const WEEKDAYS = [
        self::MONDAY,
        self::TUESDAY,
        self::WEDNESDAY,
        self::THURSDAY,
        self::FRIDAY,
    ];

    public const WEEKENDS = [
        self::SATURDAY,
        self::SUNDAY,
    ];
}

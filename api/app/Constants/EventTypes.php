<?php

namespace App\Constants;

class EventTypes
{
    public const WEDDING = 'wedding';
    public const BIRTHDAY = 'birthday';
    public const ANNIVERSARY = 'anniversary';
    public const CORPORATE = 'corporate';
    public const BABY_SHOWER = 'baby_shower';
    public const GRADUATION = 'graduation';
    public const HOLIDAY = 'holiday';
    public const OTHER = 'other';

    public const ALL = [
        self::WEDDING,
        self::BIRTHDAY,
        self::ANNIVERSARY,
        self::CORPORATE,
        self::BABY_SHOWER,
        self::GRADUATION,
        self::HOLIDAY,
        self::OTHER,
    ];
}

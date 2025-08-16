<?php

namespace App\Constants;

class PriceModifierTypes
{
    public const FIXED = 'fixed';
    public const PERCENTAGE = 'percentage';

    public const ALL = [
        self::FIXED,
        self::PERCENTAGE,
    ];
}

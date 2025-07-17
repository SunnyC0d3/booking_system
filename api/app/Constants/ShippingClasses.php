<?php

namespace App\Constants;

class ShippingClasses
{
    public const STANDARD = 'standard';
    public const EXPRESS = 'express';
    public const OVERNIGHT = 'overnight';
    public const FRAGILE = 'fragile';
    public const HEAVY = 'heavy';
    public const OVERSIZED = 'oversized';
    public const DANGEROUS = 'dangerous';
    public const REFRIGERATED = 'refrigerated';

    public static function all(): array
    {
        return [
            self::STANDARD,
            self::EXPRESS,
            self::OVERNIGHT,
            self::FRAGILE,
            self::HEAVY,
            self::OVERSIZED,
            self::DANGEROUS,
            self::REFRIGERATED,
        ];
    }
}

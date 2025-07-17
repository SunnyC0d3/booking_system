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

    public static function getClassLabel(string $class): string
    {
        return match($class) {
            self::STANDARD => 'Standard',
            self::EXPRESS => 'Express',
            self::OVERNIGHT => 'Overnight',
            self::FRAGILE => 'Fragile',
            self::HEAVY => 'Heavy',
            self::OVERSIZED => 'Oversized',
            self::DANGEROUS => 'Dangerous Goods',
            self::REFRIGERATED => 'Refrigerated',
            default => ucfirst($class),
        };
    }
}

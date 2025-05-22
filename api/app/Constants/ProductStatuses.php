<?php

namespace App\Constants;

class ProductStatuses
{
    public const ACTIVE = 'Active';
    public const INACTIVE = 'Inactive';
    public const OUT_OF_STOCK = 'Out of Stock';
    public const DISCONTINUED = 'Discontinued';
    public const COMING_SOON = 'Coming Soon';

    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
            self::OUT_OF_STOCK,
            self::DISCONTINUED,
            self::COMING_SOON,
        ];
    }
}

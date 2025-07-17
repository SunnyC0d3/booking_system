<?php

namespace App\Constants;

class FulfillmentStatuses
{
    public const UNFULFILLED = 'unfulfilled';
    public const PARTIALLY_FULFILLED = 'partially_fulfilled';
    public const FULFILLED = 'fulfilled';
    public const SHIPPED = 'shipped';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::UNFULFILLED,
            self::PARTIALLY_FULFILLED,
            self::FULFILLED,
            self::SHIPPED,
            self::DELIVERED,
            self::CANCELLED,
        ];
    }
}

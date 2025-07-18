<?php

namespace App\Constants;

class FulfillmentStatuses
{
    public const UNFULFILLED = 'unfulfilled';
    public const PARTIALLY_FULFILLED = 'partially_fulfilled';
    public const FULFILLED = 'fulfilled';
    public const SHIPPED = 'shipped';
    public const PARTIALLY_SHIPPED = 'partially_shipped';
    public const DELIVERED = 'delivered';
    public const PARTIALLY_DELIVERED = 'partially_delivered';
    public const RETURNED = 'returned';

    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::UNFULFILLED,
            self::PARTIALLY_FULFILLED,
            self::FULFILLED,
            self::SHIPPED,
            self::PARTIALLY_SHIPPED,
            self::DELIVERED,
            self::PARTIALLY_DELIVERED,
            self::RETURNED,
            self::CANCELLED,
        ];
    }

    public static function labels(): array
    {
        return [
            self::UNFULFILLED => 'Unfulfilled',
            self::PARTIALLY_FULFILLED => 'Partially Fulfilled',
            self::FULFILLED => 'Fulfilled',
            self::SHIPPED => 'Shipped',
            self::PARTIALLY_SHIPPED => 'Partially Shipped',
            self::DELIVERED => 'Delivered',
            self::PARTIALLY_DELIVERED => 'Partially Delivered',
            self::RETURNED => 'Returned',
            self::CANCELLED => 'Cancelled',
        ];
    }
}

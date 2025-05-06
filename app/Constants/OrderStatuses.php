<?php

namespace App\Constants;

class OrderStatuses
{
    public const PENDING_PAYMENT = 'Pending Payment';
    public const PROCESSING = 'Processing';
    public const CONFIRMED = 'Confirmed';
    public const SHIPPED = 'Shipped';
    public const OUT_FOR_DELIVERY = 'Out for Delivery';
    public const DELIVERED = 'Delivered';
    public const CANCELLED = 'Cancelled';
    public const REFUNDED = 'Refunded';
    public const FAILED = 'Failed';
    public const ON_HOLD = 'On Hold';

    public static function all(): array
    {
        return [
            self::PENDING_PAYMENT,
            self::PROCESSING,
            self::CONFIRMED,
            self::SHIPPED,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::CANCELLED,
            self::REFUNDED,
            self::FAILED,
            self::ON_HOLD,
        ];
    }
}

<?php

namespace App\Constants;

class ShippingStatuses
{
    public const PENDING = 'pending';
    public const PROCESSING = 'processing';
    public const SHIPPED = 'shipped';
    public const IN_TRANSIT = 'in_transit';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const RETURNED = 'returned';
    public const READY_TO_SHIP = 'ready_to_ship';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::SHIPPED,
            self::IN_TRANSIT,
            self::OUT_FOR_DELIVERY,
            self::DELIVERED,
            self::FAILED,
            self::CANCELLED,
            self::RETURNED,
            self::READY_TO_SHIP,
        ];
    }
}

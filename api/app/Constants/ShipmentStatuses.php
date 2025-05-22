<?php

namespace App\Constants;

class ShipmentStatuses
{
    public const PENDING = 'Pending';
    public const SHIPPED = 'Shipped';
    public const IN_TRANSIT = 'In Transit';
    public const DELIVERED = 'Delivered';
    public const RETURNED = 'Returned';
    public const CANCELLED = 'Cancelled';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::SHIPPED,
            self::IN_TRANSIT,
            self::DELIVERED,
            self::RETURNED,
            self::CANCELLED,
        ];
    }
}

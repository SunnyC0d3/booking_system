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

    public const LOST = 'lost';

    public const DAMAGED = 'damaged';
    public const EXCEPTION = 'exception';
    public const UNKNOWN = 'unknown';

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
            self::LOST,
            self::DAMAGED,
            self::EXCEPTION,
            self::UNKNOWN,
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return match($status) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::IN_TRANSIT => 'In Transit',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::RETURNED => 'Returned',
            self::READY_TO_SHIP => 'Ready to Ship',
            self::LOST => 'Lost',
            self::DAMAGED => 'Damaged',
            self::EXCEPTION => 'Exception',
            self::UNKNOWN => 'Unknown',
            default => ucfirst($status),
        };
    }

    public static function getStatusColor(string $status): string
    {
        return match($status) {
            self::PENDING => 'yellow',
            self::PROCESSING => 'blue',
            self::SHIPPED, self::IN_TRANSIT => 'indigo',
            self::OUT_FOR_DELIVERY => 'purple',
            self::DELIVERED => 'green',
            self::FAILED, self::CANCELLED => 'red',
            self::RETURNED => 'orange',
            self::READY_TO_SHIP => 'cyan',
            self::LOST => 'red',
            self::DAMAGED => 'red',
            self::EXCEPTION => 'red',
            self::UNKNOWN => 'gray',
            default => 'gray',
        };
    }
}

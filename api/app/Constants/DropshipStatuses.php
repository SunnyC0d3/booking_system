<?php

namespace App\Constants;

class DropshipStatuses
{
    public const PENDING = 'pending';
    public const SENT_TO_SUPPLIER = 'sent_to_supplier';
    public const CONFIRMED_BY_SUPPLIER = 'confirmed_by_supplier';
    public const PROCESSING = 'processing';
    public const SHIPPED_BY_SUPPLIER = 'shipped_by_supplier';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';
    public const REJECTED_BY_SUPPLIER = 'rejected_by_supplier';
    public const REFUNDED = 'refunded';
    public const ON_HOLD = 'on_hold';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::SENT_TO_SUPPLIER,
            self::CONFIRMED_BY_SUPPLIER,
            self::PROCESSING,
            self::SHIPPED_BY_SUPPLIER,
            self::DELIVERED,
            self::CANCELLED,
            self::REJECTED_BY_SUPPLIER,
            self::REFUNDED,
            self::ON_HOLD,
        ];
    }

    public static function labels(): array
    {
        return [
            self::PENDING => 'Pending',
            self::SENT_TO_SUPPLIER => 'Sent to Supplier',
            self::CONFIRMED_BY_SUPPLIER => 'Confirmed by Supplier',
            self::PROCESSING => 'Processing',
            self::SHIPPED_BY_SUPPLIER => 'Shipped by Supplier',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::REJECTED_BY_SUPPLIER => 'Rejected by Supplier',
            self::REFUNDED => 'Refunded',
            self::ON_HOLD => 'On Hold',
        ];
    }

    public static function getActiveStatuses(): array
    {
        return [
            self::PENDING,
            self::SENT_TO_SUPPLIER,
            self::CONFIRMED_BY_SUPPLIER,
            self::PROCESSING,
            self::SHIPPED_BY_SUPPLIER,
        ];
    }

    public static function getCompletedStatuses(): array
    {
        return [
            self::DELIVERED,
            self::CANCELLED,
            self::REFUNDED,
        ];
    }

    public static function getFailedStatuses(): array
    {
        return [
            self::REJECTED_BY_SUPPLIER,
            self::CANCELLED,
        ];
    }
}

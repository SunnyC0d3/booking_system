<?php

namespace App\Constants;

class RefundStatuses
{
    public const PENDING = 'Pending';
    public const PROCESSING = 'Processing';
    public const REFUNDED = 'Refunded';
    public const PARTIALLY_REFUNDED = 'Partially Refunded';
    public const FAILED = 'Failed';
    public const CANCELLED = 'Cancelled';
    public const DECLINED = 'Declined';

    public static function all(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
            self::FAILED,
            self::CANCELLED,
            self::DECLINED,
        ];
    }
}

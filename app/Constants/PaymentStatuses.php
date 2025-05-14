<?php

namespace App\Constants;

class PaymentStatuses
{
    public const PAID = 'Paid';
    public const PENDING = 'Pending';
    public const CANCELED = 'Canceled';
    public const FAILED = 'Failed';

    public const REFUNDED = 'Refunded';

    public static function all(): array
    {
        return [
            self::PAID,
            self::PENDING,
            self::CANCELED,
            self::FAILED,
            self::REFUNDED,
        ];
    }
}

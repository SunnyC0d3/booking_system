<?php

namespace App\Constants;

class PaymentStatuses
{
    public const DEPOSIT_PAID = 'deposit_paid';

    public const PENDING = 'pending';
    public const PARTIAL = 'partial';
    public const PAID = 'paid';
    public const REFUNDED = 'refunded';
    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const ALL = [
        self::DEPOSIT_PAID,
        self::PENDING,
        self::PARTIAL,
        self::PAID,
        self::REFUNDED,
        self::FAILED,
        self::CANCELLED,
    ];

    public static function getDisplayName(string $status): string
    {
        return match ($status) {
            self::DEPOSIT_PAID => 'Deposit Paid',
            self::PENDING => 'Payment Pending',
            self::PARTIAL => 'Partially Paid',
            self::PAID => 'Fully Paid',
            self::REFUNDED => 'Refunded',
            self::FAILED => 'Payment Failed',
            self::CANCELLED => 'Payment Cancelled',
            default => ucfirst(str_replace('_', ' ', $status))
        };
    }

    public static function getColor(string $status): string
    {
        return match ($status) {
            self::DEPOSIT_PAID => 'yellow',
            self::PENDING => 'yellow',
            self::PARTIAL => 'orange',
            self::PAID => 'green',
            self::REFUNDED => 'blue',
            self::FAILED => 'red',
            self::CANCELLED => 'gray',
            default => 'gray'
        };
    }

    public static function isComplete(string $status): bool
    {
        return in_array($status, [self::PAID, self::REFUNDED]);
    }

    public static function requiresAction(string $status): bool
    {
        return in_array($status, [self::PENDING, self::FAILED]);
    }
}

<?php

namespace App\Constants;

class ConsultationPaymentStatuses
{
    public const FREE = 'free';
    public const UNPAID = 'unpaid';
    public const PAID = 'paid';
    public const REFUNDED = 'refunded';
    public const WAIVED = 'waived';

    public const ALL = [
        self::FREE,
        self::UNPAID,
        self::PAID,
        self::REFUNDED,
        self::WAIVED,
    ];

    public static function getDisplayName(string $status): string
    {
        return match ($status) {
            self::FREE => 'Free Consultation',
            self::UNPAID => 'Payment Required',
            self::PAID => 'Paid',
            self::REFUNDED => 'Refunded',
            self::WAIVED => 'Fee Waived',
            default => ucfirst($status)
        };
    }

    public static function getColor(string $status): string
    {
        return match ($status) {
            self::FREE => 'green',
            self::UNPAID => 'orange',
            self::PAID => 'green',
            self::REFUNDED => 'gray',
            self::WAIVED => 'blue',
            default => 'gray'
        };
    }
}

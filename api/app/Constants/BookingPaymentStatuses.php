<?php

namespace App\Constants;

class BookingPaymentStatuses
{
    public const PENDING = 'pending';
    public const DEPOSIT_PAID = 'deposit_paid';
    public const FULLY_PAID = 'fully_paid';
    public const REFUNDED = 'refunded';
    public const PARTIALLY_REFUNDED = 'partially_refunded';

    public const ALL = [
        self::PENDING,
        self::DEPOSIT_PAID,
        self::FULLY_PAID,
        self::REFUNDED,
        self::PARTIALLY_REFUNDED,
    ];

    public const PAID_STATUSES = [
        self::DEPOSIT_PAID,
        self::FULLY_PAID,
    ];
}

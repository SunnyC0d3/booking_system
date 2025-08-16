<?php

namespace App\Constants;

class BookingStatuses
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW = 'no_show';
    public const RESCHEDULED = 'rescheduled';

    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::IN_PROGRESS,
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
        self::RESCHEDULED,
    ];

    public const ACTIVE_STATUSES = [
        self::PENDING,
        self::CONFIRMED,
        self::IN_PROGRESS,
    ];

    public const COMPLETED_STATUSES = [
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
        self::RESCHEDULED,
    ];

    public const CANCELLABLE_STATUSES = [
        self::PENDING,
        self::CONFIRMED,
    ];

    public const RESCHEDULABLE_STATUSES = [
        self::PENDING,
        self::CONFIRMED,
    ];
}

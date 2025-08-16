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

    public const ACTIVE = [
        self::PENDING,
        self::CONFIRMED,
        self::IN_PROGRESS,
    ];

    public const COMPLETED_STATES = [
        self::COMPLETED,
    ];

    public const CANCELLED_STATES = [
        self::CANCELLED,
        self::NO_SHOW,
    ];

    public const FINISHED = [
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
        self::RESCHEDULED,
    ];

    public static function getDisplayName(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending Confirmation',
            self::CONFIRMED => 'Confirmed',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
            self::RESCHEDULED => 'Rescheduled',
            default => ucfirst($status)
        };
    }

    public static function getColor(string $status): string
    {
        return match ($status) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'blue',
            self::IN_PROGRESS => 'orange',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::NO_SHOW => 'gray',
            self::RESCHEDULED => 'purple',
            default => 'gray'
        };
    }

    public static function getDescription(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Booking submitted and awaiting confirmation',
            self::CONFIRMED => 'Booking confirmed and scheduled',
            self::IN_PROGRESS => 'Service is currently being delivered',
            self::COMPLETED => 'Service has been successfully completed',
            self::CANCELLED => 'Booking was cancelled',
            self::NO_SHOW => 'Client did not show up for the appointment',
            self::RESCHEDULED => 'Booking was moved to a different time',
            default => 'Unknown status'
        };
    }

    public static function canTransitionTo(string $from, string $to): bool
    {
        $allowedTransitions = [
            self::PENDING => [self::CONFIRMED, self::CANCELLED, self::RESCHEDULED],
            self::CONFIRMED => [self::IN_PROGRESS, self::CANCELLED, self::NO_SHOW, self::RESCHEDULED],
            self::IN_PROGRESS => [self::COMPLETED, self::CANCELLED],
            self::COMPLETED => [], // Final state
            self::CANCELLED => [], // Final state
            self::NO_SHOW => [], // Final state
            self::RESCHEDULED => [], // Final state - new booking created
        ];

        return in_array($to, $allowedTransitions[$from] ?? []);
    }
}

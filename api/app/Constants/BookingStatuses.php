<?php

namespace App\Constants;

class BookingStatuses
{
    // Core booking statuses
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW = 'no_show';
    public const RESCHEDULED = 'rescheduled';

    // Consultation-specific statuses
    public const CONSULTATION_REQUIRED = 'consultation_required';
    public const CONSULTATION_SCHEDULED = 'consultation_scheduled';
    public const CONSULTATION_COMPLETED = 'consultation_completed';

    public const ALL = [
        self::PENDING,
        self::CONFIRMED,
        self::IN_PROGRESS,
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
        self::RESCHEDULED,
        self::CONSULTATION_REQUIRED,
        self::CONSULTATION_SCHEDULED,
        self::CONSULTATION_COMPLETED,
    ];

    // Active statuses (not cancelled or completed)
    public const ACTIVE = [
        self::PENDING,
        self::CONFIRMED,
        self::IN_PROGRESS,
        self::CONSULTATION_REQUIRED,
        self::CONSULTATION_SCHEDULED,
        self::CONSULTATION_COMPLETED,
    ];

    // Statuses that block time slots
    public const BLOCKS_SLOT = [
        self::CONFIRMED,
        self::IN_PROGRESS,
        self::CONSULTATION_SCHEDULED,
    ];

    // Statuses that can be cancelled
    public const CANCELLABLE = [
        self::PENDING,
        self::CONFIRMED,
        self::CONSULTATION_REQUIRED,
        self::CONSULTATION_SCHEDULED,
    ];

    // Statuses that can be rescheduled
    public const RESCHEDULABLE = [
        self::PENDING,
        self::CONFIRMED,
        self::CONSULTATION_REQUIRED,
        self::CONSULTATION_SCHEDULED,
    ];

    // Final statuses (cannot be changed)
    public const FINAL = [
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
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
            self::CONSULTATION_REQUIRED => 'Consultation Required',
            self::CONSULTATION_SCHEDULED => 'Consultation Scheduled',
            self::CONSULTATION_COMPLETED => 'Consultation Completed',
            default => ucfirst(str_replace('_', ' ', $status))
        };
    }

    public static function getColor(string $status): string
    {
        return match ($status) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'blue',
            self::IN_PROGRESS => 'purple',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::NO_SHOW => 'red',
            self::RESCHEDULED => 'orange',
            self::CONSULTATION_REQUIRED => 'yellow',
            self::CONSULTATION_SCHEDULED => 'blue',
            self::CONSULTATION_COMPLETED => 'green',
            default => 'gray'
        };
    }

    public static function getDescription(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Booking submitted and awaiting confirmation',
            self::CONFIRMED => 'Booking confirmed and scheduled',
            self::IN_PROGRESS => 'Service is currently being performed',
            self::COMPLETED => 'Service has been completed successfully',
            self::CANCELLED => 'Booking has been cancelled',
            self::NO_SHOW => 'Customer did not show up for the appointment',
            self::RESCHEDULED => 'Booking has been moved to a different time',
            self::CONSULTATION_REQUIRED => 'Initial consultation needed before service',
            self::CONSULTATION_SCHEDULED => 'Consultation appointment scheduled',
            self::CONSULTATION_COMPLETED => 'Consultation completed, ready for service',
            default => 'Unknown status'
        };
    }

    public static function isActive(string $status): bool
    {
        return in_array($status, self::ACTIVE);
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, self::FINAL);
    }

    public static function canCancel(string $status): bool
    {
        return in_array($status, self::CANCELLABLE);
    }

    public static function canReschedule(string $status): bool
    {
        return in_array($status, self::RESCHEDULABLE);
    }

    public static function blocksSlot(string $status): bool
    {
        return in_array($status, self::BLOCKS_SLOT);
    }

    public static function getNextPossibleStatuses(string $currentStatus): array
    {
        return match ($currentStatus) {
            self::PENDING => [
                self::CONFIRMED,
                self::CANCELLED,
                self::CONSULTATION_REQUIRED,
            ],
            self::CONFIRMED => [
                self::IN_PROGRESS,
                self::CANCELLED,
                self::NO_SHOW,
                self::RESCHEDULED,
            ],
            self::IN_PROGRESS => [
                self::COMPLETED,
                self::CANCELLED,
            ],
            self::CONSULTATION_REQUIRED => [
                self::CONSULTATION_SCHEDULED,
                self::CANCELLED,
            ],
            self::CONSULTATION_SCHEDULED => [
                self::CONSULTATION_COMPLETED,
                self::CANCELLED,
                self::NO_SHOW,
                self::RESCHEDULED,
            ],
            self::CONSULTATION_COMPLETED => [
                self::CONFIRMED,
                self::CANCELLED,
            ],
            default => []
        };
    }
}

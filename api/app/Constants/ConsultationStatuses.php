<?php

namespace App\Constants;

class ConsultationStatuses
{
    public const SCHEDULED = 'scheduled';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW = 'no_show';

    public const ALL = [
        self::SCHEDULED,
        self::IN_PROGRESS,
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
    ];

    public const ACTIVE = [
        self::SCHEDULED,
        self::IN_PROGRESS,
    ];

    public const FINISHED = [
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
    ];

    public static function getDisplayName(string $status): string
    {
        return match ($status) {
            self::SCHEDULED => 'Scheduled',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
            default => ucfirst($status)
        };
    }

    public static function getColor(string $status): string
    {
        return match ($status) {
            self::SCHEDULED => 'blue',
            self::IN_PROGRESS => 'orange',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::NO_SHOW => 'gray',
            default => 'gray'
        };
    }
}

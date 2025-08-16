<?php

namespace App\Constants;

class ConsultationStatuses
{
    public const NOT_REQUIRED = 'not_required';
    public const REQUIRED = 'required';
    public const SCHEDULED = 'scheduled';
    public const IN_PROGRESS = 'in_progress';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    public const NO_SHOW = 'no_show';

    public const ALL = [
        self::NOT_REQUIRED,
        self::REQUIRED,
        self::SCHEDULED,
        self::IN_PROGRESS,
        self::COMPLETED,
        self::CANCELLED,
        self::NO_SHOW,
    ];

    public static function getDisplayName(string $status): string
    {
        return match ($status) {
            self::NOT_REQUIRED => 'Not Required',
            self::REQUIRED => 'Required',
            self::SCHEDULED => 'Scheduled',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::NO_SHOW => 'No Show',
            default => ucfirst(str_replace('_', ' ', $status))
        };
    }

    public static function isComplete(string $status): bool
    {
        return in_array($status, [self::COMPLETED, self::NOT_REQUIRED]);
    }
}

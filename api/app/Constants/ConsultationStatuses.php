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

class ConsultationTypes
{
    public const PRE_BOOKING = 'pre_booking';
    public const DESIGN = 'design';
    public const PLANNING = 'planning';
    public const TECHNICAL = 'technical';
    public const FOLLOW_UP = 'follow_up';

    public const ALL = [
        self::PRE_BOOKING,
        self::DESIGN,
        self::PLANNING,
        self::TECHNICAL,
        self::FOLLOW_UP,
    ];

    public static function getDisplayName(string $type): string
    {
        return match ($type) {
            self::PRE_BOOKING => 'Pre-Booking Consultation',
            self::DESIGN => 'Design Consultation',
            self::PLANNING => 'Planning Session',
            self::TECHNICAL => 'Technical Consultation',
            self::FOLLOW_UP => 'Follow-Up Meeting',
            default => ucfirst(str_replace('_', ' ', $type))
        };
    }
}

class ConsultationFormats
{
    public const PHONE = 'phone';
    public const VIDEO = 'video';
    public const IN_PERSON = 'in_person';
    public const SITE_VISIT = 'site_visit';

    public const ALL = [
        self::PHONE,
        self::VIDEO,
        self::IN_PERSON,
        self::SITE_VISIT,
    ];

    public const VIRTUAL = [
        self::PHONE,
        self::VIDEO,
    ];

    public const PHYSICAL = [
        self::IN_PERSON,
        self::SITE_VISIT,
    ];

    public static function getDisplayName(string $format): string
    {
        return match ($format) {
            self::PHONE => 'Phone Call',
            self::VIDEO => 'Video Call',
            self::IN_PERSON => 'In-Person Meeting',
            self::SITE_VISIT => 'Site Visit',
            default => ucfirst(str_replace('_', ' ', $format))
        };
    }

    public static function getIcon(string $format): string
    {
        return match ($format) {
            self::PHONE => 'phone',
            self::VIDEO => 'video',
            self::IN_PERSON => 'user',
            self::SITE_VISIT => 'map-pin',
            default => 'calendar'
        };
    }

    public static function requiresLocation(string $format): bool
    {
        return in_array($format, self::PHYSICAL);
    }

    public static function requiresMeetingLink(string $format): bool
    {
        return $format === self::VIDEO;
    }
}

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

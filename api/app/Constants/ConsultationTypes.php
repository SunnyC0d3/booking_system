<?php

namespace App\Constants;

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

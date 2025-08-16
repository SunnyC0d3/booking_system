<?php

namespace App\Constants;

class NotificationTypes
{
    public const BOOKING_CREATED = 'booking_created';
    public const BOOKING_CONFIRMED = 'booking_confirmed';
    public const BOOKING_CANCELLED = 'booking_cancelled';
    public const BOOKING_RESCHEDULED = 'booking_rescheduled';
    public const CONSULTATION_REMINDER = 'consultation_reminder';
    public const BOOKING_REMINDER = 'booking_reminder';
    public const PAYMENT_REMINDER = 'payment_reminder';
    public const FOLLOW_UP = 'follow_up';

    public const ALL = [
        self::BOOKING_CREATED,
        self::BOOKING_CONFIRMED,
        self::BOOKING_CANCELLED,
        self::BOOKING_RESCHEDULED,
        self::CONSULTATION_REMINDER,
        self::BOOKING_REMINDER,
        self::PAYMENT_REMINDER,
        self::FOLLOW_UP,
    ];

    public static function getDisplayName(string $type): string
    {
        return match ($type) {
            self::BOOKING_CREATED => 'Booking Created',
            self::BOOKING_CONFIRMED => 'Booking Confirmed',
            self::BOOKING_CANCELLED => 'Booking Cancelled',
            self::BOOKING_RESCHEDULED => 'Booking Rescheduled',
            self::CONSULTATION_REMINDER => 'Consultation Reminder',
            self::BOOKING_REMINDER => 'Booking Reminder',
            self::PAYMENT_REMINDER => 'Payment Reminder',
            self::FOLLOW_UP => 'Follow Up',
            default => ucfirst(str_replace('_', ' ', $type))
        };
    }
}

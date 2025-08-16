<?php

namespace App\Constants;

class NotificationChannels
{
    public const EMAIL = 'email';
    public const SMS = 'sms';
    public const PUSH = 'push';
    public const IN_APP = 'in_app';

    public const ALL = [
        self::EMAIL,
        self::SMS,
        self::PUSH,
        self::IN_APP,
    ];

    public static function getDisplayName(string $channel): string
    {
        return match ($channel) {
            self::EMAIL => 'Email',
            self::SMS => 'SMS',
            self::PUSH => 'Push Notification',
            self::IN_APP => 'In-App Notification',
            default => ucfirst($channel)
        };
    }
}

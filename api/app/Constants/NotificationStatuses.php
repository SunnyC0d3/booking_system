<?php

namespace App\Constants;

class NotificationStatuses
{
    // Core notification statuses
    public const PENDING = 'pending';
    public const QUEUED = 'queued';
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const DELIVERED = 'delivered';
    public const READ = 'read';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';
    public const SKIPPED = 'skipped';

    // SMS specific statuses
    public const SMS_QUEUED = 'sms_queued';
    public const SMS_SENT = 'sms_sent';
    public const SMS_DELIVERED = 'sms_delivered';
    public const SMS_FAILED = 'sms_failed';
    public const SMS_UNDELIVERED = 'sms_undelivered';

    // Push notification specific statuses
    public const PUSH_QUEUED = 'push_queued';
    public const PUSH_SENT = 'push_sent';
    public const PUSH_DELIVERED = 'push_delivered';
    public const PUSH_FAILED = 'push_failed';
    public const PUSH_CLICKED = 'push_clicked';

    // Email specific statuses
    public const EMAIL_QUEUED = 'email_queued';
    public const EMAIL_SENT = 'email_sent';
    public const EMAIL_DELIVERED = 'email_delivered';
    public const EMAIL_OPENED = 'email_opened';
    public const EMAIL_CLICKED = 'email_clicked';
    public const EMAIL_BOUNCED = 'email_bounced';
    public const EMAIL_COMPLAINED = 'email_complained';

    // All statuses array
    public const ALL = [
        self::PENDING,
        self::QUEUED,
        self::SENDING,
        self::SENT,
        self::DELIVERED,
        self::READ,
        self::FAILED,
        self::CANCELLED,
        self::EXPIRED,
        self::SKIPPED,
        self::SMS_QUEUED,
        self::SMS_SENT,
        self::SMS_DELIVERED,
        self::SMS_FAILED,
        self::SMS_UNDELIVERED,
        self::PUSH_QUEUED,
        self::PUSH_SENT,
        self::PUSH_DELIVERED,
        self::PUSH_FAILED,
        self::PUSH_CLICKED,
        self::EMAIL_QUEUED,
        self::EMAIL_SENT,
        self::EMAIL_DELIVERED,
        self::EMAIL_OPENED,
        self::EMAIL_CLICKED,
        self::EMAIL_BOUNCED,
        self::EMAIL_COMPLAINED,
    ];

    // Status groups
    public const PENDING_STATUSES = [
        self::PENDING,
        self::QUEUED,
        self::SENDING,
        self::SMS_QUEUED,
        self::PUSH_QUEUED,
        self::EMAIL_QUEUED,
    ];

    public const SUCCESSFUL_STATUSES = [
        self::SENT,
        self::DELIVERED,
        self::READ,
        self::SMS_SENT,
        self::SMS_DELIVERED,
        self::PUSH_SENT,
        self::PUSH_DELIVERED,
        self::PUSH_CLICKED,
        self::EMAIL_SENT,
        self::EMAIL_DELIVERED,
        self::EMAIL_OPENED,
        self::EMAIL_CLICKED,
    ];

    public const FAILED_STATUSES = [
        self::FAILED,
        self::SMS_FAILED,
        self::SMS_UNDELIVERED,
        self::PUSH_FAILED,
        self::EMAIL_BOUNCED,
        self::EMAIL_COMPLAINED,
    ];

    public const TERMINAL_STATUSES = [
        self::DELIVERED,
        self::READ,
        self::FAILED,
        self::CANCELLED,
        self::EXPIRED,
        self::SKIPPED,
        self::SMS_DELIVERED,
        self::SMS_FAILED,
        self::SMS_UNDELIVERED,
        self::PUSH_DELIVERED,
        self::PUSH_FAILED,
        self::PUSH_CLICKED,
        self::EMAIL_DELIVERED,
        self::EMAIL_OPENED,
        self::EMAIL_CLICKED,
        self::EMAIL_BOUNCED,
        self::EMAIL_COMPLAINED,
    ];

    public const ACTIONABLE_STATUSES = [
        self::DELIVERED,
        self::READ,
        self::PUSH_CLICKED,
        self::EMAIL_OPENED,
        self::EMAIL_CLICKED,
    ];

    /**
     * Get display name for status
     */
    public static function getDisplayName(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Pending',
            self::QUEUED => 'Queued',
            self::SENDING => 'Sending',
            self::SENT => 'Sent',
            self::DELIVERED => 'Delivered',
            self::READ => 'Read',
            self::FAILED => 'Failed',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::SKIPPED => 'Skipped',
            self::SMS_QUEUED => 'SMS Queued',
            self::SMS_SENT => 'SMS Sent',
            self::SMS_DELIVERED => 'SMS Delivered',
            self::SMS_FAILED => 'SMS Failed',
            self::SMS_UNDELIVERED => 'SMS Undelivered',
            self::PUSH_QUEUED => 'Push Queued',
            self::PUSH_SENT => 'Push Sent',
            self::PUSH_DELIVERED => 'Push Delivered',
            self::PUSH_FAILED => 'Push Failed',
            self::PUSH_CLICKED => 'Push Clicked',
            self::EMAIL_QUEUED => 'Email Queued',
            self::EMAIL_SENT => 'Email Sent',
            self::EMAIL_DELIVERED => 'Email Delivered',
            self::EMAIL_OPENED => 'Email Opened',
            self::EMAIL_CLICKED => 'Email Clicked',
            self::EMAIL_BOUNCED => 'Email Bounced',
            self::EMAIL_COMPLAINED => 'Email Complained',
            default => ucfirst(str_replace('_', ' ', $status))
        };
    }

    /**
     * Get status color for UI display
     */
    public static function getColor(string $status): string
    {
        return match ($status) {
            self::PENDING, self::QUEUED, self::SMS_QUEUED, self::PUSH_QUEUED, self::EMAIL_QUEUED => 'yellow',
            self::SENDING,=> 'blue',
            self::SENT, self::SMS_SENT, self::PUSH_SENT, self::EMAIL_SENT => 'blue',
            self::DELIVERED, self::SMS_DELIVERED, self::PUSH_DELIVERED, self::EMAIL_DELIVERED => 'green',
            self::READ, self::PUSH_CLICKED, self::EMAIL_OPENED, self::EMAIL_CLICKED => 'green',
            self::FAILED, self::SMS_FAILED, self::SMS_UNDELIVERED, self::PUSH_FAILED,
            self::EMAIL_BOUNCED, self::EMAIL_COMPLAINED => 'red',
            self::CANCELLED => 'gray',
            self::EXPIRED => 'orange',
            self::SKIPPED => 'purple',
            default => 'gray'
        };
    }

    /**
     * Get status description
     */
    public static function getDescription(string $status): string
    {
        return match ($status) {
            self::PENDING => 'Notification is waiting to be processed',
            self::QUEUED => 'Notification has been queued for sending',
            self::SENDING => 'Notification is currently being sent',
            self::SENT => 'Notification has been sent successfully',
            self::DELIVERED => 'Notification was delivered to recipient',
            self::READ => 'Notification has been read by recipient',
            self::FAILED => 'Notification failed to send',
            self::CANCELLED => 'Notification was cancelled before sending',
            self::EXPIRED => 'Notification expired before it could be sent',
            self::SKIPPED => 'Notification was skipped due to user preferences',
            self::SMS_QUEUED => 'SMS is queued for sending',
            self::SMS_SENT => 'SMS has been sent to carrier',
            self::SMS_DELIVERED => 'SMS was delivered to phone',
            self::SMS_FAILED => 'SMS failed to send',
            self::SMS_UNDELIVERED => 'SMS could not be delivered',
            self::PUSH_QUEUED => 'Push notification is queued',
            self::PUSH_SENT => 'Push notification has been sent',
            self::PUSH_DELIVERED => 'Push notification was delivered to device',
            self::PUSH_FAILED => 'Push notification failed to send',
            self::PUSH_CLICKED => 'Push notification was clicked by user',
            self::EMAIL_QUEUED => 'Email is queued for sending',
            self::EMAIL_SENT => 'Email has been sent',
            self::EMAIL_DELIVERED => 'Email was delivered to inbox',
            self::EMAIL_OPENED => 'Email was opened by recipient',
            self::EMAIL_CLICKED => 'Link in email was clicked',
            self::EMAIL_BOUNCED => 'Email bounced back (invalid address)',
            self::EMAIL_COMPLAINED => 'Email was marked as spam',
            default => 'Unknown status'
        };
    }

    /**
     * Check if status indicates success
     */
    public static function isSuccessful(string $status): bool
    {
        return in_array($status, self::SUCCESSFUL_STATUSES);
    }

    /**
     * Check if status indicates failure
     */
    public static function isFailed(string $status): bool
    {
        return in_array($status, self::FAILED_STATUSES);
    }

    /**
     * Check if status is terminal (no further updates expected)
     */
    public static function isTerminal(string $status): bool
    {
        return in_array($status, self::TERMINAL_STATUSES);
    }

    /**
     * Check if status is pending
     */
    public static function isPending(string $status): bool
    {
        return in_array($status, self::PENDING_STATUSES);
    }

    /**
     * Check if status indicates user action
     */
    public static function isActionable(string $status): bool
    {
        return in_array($status, self::ACTIONABLE_STATUSES);
    }

    /**
     * Get next expected status
     */
    public static function getNextExpectedStatus(string $currentStatus): ?string
    {
        return match ($currentStatus) {
            self::PENDING => self::QUEUED,
            self::QUEUED => self::SENDING,
            self::SENDING => self::SENT,
            self::SENT => self::DELIVERED,
            self::SMS_QUEUED => self::SMS_SENT,
            self::SMS_SENT => self::SMS_DELIVERED,
            self::PUSH_QUEUED => self::PUSH_SENT,
            self::PUSH_SENT => self::PUSH_DELIVERED,
            self::EMAIL_QUEUED => self::EMAIL_SENT,
            self::EMAIL_SENT => self::EMAIL_DELIVERED,
            self::EMAIL_DELIVERED => self::EMAIL_OPENED,
            default => null
        };
    }

    /**
     * Get all statuses for a specific channel
     */
    public static function getChannelStatuses(string $channel): array
    {
        return match ($channel) {
            'sms' => [
                self::SMS_QUEUED,
                self::SMS_SENT,
                self::SMS_DELIVERED,
                self::SMS_FAILED,
                self::SMS_UNDELIVERED,
            ],
            'push' => [
                self::PUSH_QUEUED,
                self::PUSH_SENT,
                self::PUSH_DELIVERED,
                self::PUSH_FAILED,
                self::PUSH_CLICKED,
            ],
            'email', 'mail' => [
                self::EMAIL_QUEUED,
                self::EMAIL_SENT,
                self::EMAIL_DELIVERED,
                self::EMAIL_OPENED,
                self::EMAIL_CLICKED,
                self::EMAIL_BOUNCED,
                self::EMAIL_COMPLAINED,
            ],
            default => [
                self::PENDING,
                self::QUEUED,
                self::SENDING,
                self::SENT,
                self::DELIVERED,
                self::READ,
                self::FAILED,
                self::CANCELLED,
                self::EXPIRED,
                self::SKIPPED,
            ]
        };
    }

    /**
     * Get priority for status ordering
     */
    public static function getPriority(string $status): int
    {
        return match ($status) {
            self::FAILED, self::SMS_FAILED, self::PUSH_FAILED,
            self::EMAIL_BOUNCED, self::EMAIL_COMPLAINED => 1,
            self::PENDING, self::QUEUED, self::SENDING,
            self::SMS_QUEUED, self::PUSH_QUEUED, self::EMAIL_QUEUED => 2,
            self::SENT, self::SMS_SENT, self::PUSH_SENT, self::EMAIL_SENT => 3,
            self::DELIVERED, self::SMS_DELIVERED, self::PUSH_DELIVERED, self::EMAIL_DELIVERED => 4,
            self::READ, self::PUSH_CLICKED, self::EMAIL_OPENED, self::EMAIL_CLICKED => 5,
            self::CANCELLED, self::EXPIRED, self::SKIPPED => 6,
            default => 7
        };
    }
}

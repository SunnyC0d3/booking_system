<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | This option controls the default notification channels that will be
    | used when sending notifications. You can disable channels here.
    |
    */

    'channels' => [
        'mail' => [
            'enabled' => env('NOTIFICATIONS_MAIL_ENABLED', true),
            'queue' => env('NOTIFICATIONS_MAIL_QUEUE', 'emails'),
        ],
        'database' => [
            'enabled' => env('NOTIFICATIONS_DATABASE_ENABLED', true),
            'queue' => env('NOTIFICATIONS_DATABASE_QUEUE', 'notifications'),
        ],
        'sms' => [
            'enabled' => env('NOTIFICATIONS_SMS_ENABLED', false),
            'queue' => env('NOTIFICATIONS_SMS_QUEUE', 'sms'),
            'provider' => env('SMS_PROVIDER', 'twilio'), // twilio, vonage, aws
        ],
        'push' => [
            'enabled' => env('NOTIFICATIONS_PUSH_ENABLED', false),
            'queue' => env('NOTIFICATIONS_PUSH_QUEUE', 'push'),
            'provider' => env('PUSH_PROVIDER', 'fcm'), // fcm, apn, onesignal
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminder Schedules
    |--------------------------------------------------------------------------
    |
    | Configure when reminders should be sent before scheduled events.
    | Times are in hours before the event.
    |
    */

    'reminder_schedules' => [
        'booking' => [
            'enabled' => env('BOOKING_REMINDERS_ENABLED', true),
            'times' => [
                24, // 24 hours before
                2,  // 2 hours before
            ],
            'channels' => ['mail', 'database'],
        ],
        'consultation' => [
            'enabled' => env('CONSULTATION_REMINDERS_ENABLED', true),
            'times' => [
                24, // 24 hours before
                1,  // 1 hour before
            ],
            'channels' => ['mail', 'database', 'sms'],
        ],
        'payment' => [
            'enabled' => env('PAYMENT_REMINDERS_ENABLED', true),
            'times' => [
                72, // 3 days before due
                24, // 1 day before due
                0,  // Day of due date
            ],
            'channels' => ['mail', 'database'],
            'overdue_times' => [
                24, // 1 day overdue
                72, // 3 days overdue
                168, // 1 week overdue
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Templates
    |--------------------------------------------------------------------------
    |
    | Map notification types to their email templates and subjects.
    |
    */

    'templates' => [
        'booking_confirmation' => [
            'mail' => 'emails.booking.confirmation',
            'subject' => 'Booking Confirmed - #{reference}',
            'priority' => 'high',
        ],
        'booking_reminder' => [
            'mail' => 'emails.booking.reminder',
            'subject' => 'Reminder: Upcoming Service - #{reference}',
            'priority' => 'normal',
        ],
        'booking_cancelled' => [
            'mail' => 'emails.booking.cancelled',
            'subject' => 'Booking Cancelled - #{reference}',
            'priority' => 'high',
        ],
        'booking_rescheduled' => [
            'mail' => 'emails.booking.rescheduled',
            'subject' => 'Booking Rescheduled - #{reference}',
            'priority' => 'high',
        ],
        'consultation_confirmation' => [
            'mail' => 'emails.consultation.confirmation',
            'subject' => 'Consultation Confirmed - #{reference}',
            'priority' => 'high',
        ],
        'consultation_reminder' => [
            'mail' => 'emails.consultation.reminder',
            'subject' => 'Reminder: Consultation Tomorrow - #{reference}',
            'priority' => 'normal',
        ],
        'consultation_starting_soon' => [
            'mail' => 'emails.consultation.starting_soon',
            'subject' => 'Starting Soon: Your Consultation - #{reference}',
            'priority' => 'urgent',
            'sms' => 'Your consultation is starting in 15 minutes. Join at: {meeting_link}',
        ],
        'payment_reminder' => [
            'mail' => 'emails.payment.reminder',
            'subject' => 'Payment Reminder - #{reference}',
            'priority' => 'normal',
        ],
        'payment_overdue' => [
            'mail' => 'emails.payment.overdue',
            'subject' => 'Overdue Payment - #{reference}',
            'priority' => 'high',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for different notification priorities.
    |
    */

    'queues' => [
        'urgent' => env('NOTIFICATIONS_URGENT_QUEUE', 'notifications-urgent'),
        'high' => env('NOTIFICATIONS_HIGH_QUEUE', 'notifications-high'),
        'normal' => env('NOTIFICATIONS_NORMAL_QUEUE', 'notifications'),
        'low' => env('NOTIFICATIONS_LOW_QUEUE', 'notifications-low'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configure retry attempts and delays for failed notifications.
    |
    */

    'retry' => [
        'max_attempts' => env('NOTIFICATIONS_MAX_ATTEMPTS', 3),
        'backoff_seconds' => [30, 300, 1800], // 30 sec, 5 min, 30 min
        'failed_job_retention_days' => env('NOTIFICATIONS_FAILED_RETENTION_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Prevent notification spam by limiting how often notifications
    | of the same type can be sent to the same user.
    |
    */

    'rate_limiting' => [
        'enabled' => env('NOTIFICATIONS_RATE_LIMITING_ENABLED', true),
        'limits' => [
            'booking_reminder' => [
                'max_per_hour' => 2,
                'max_per_day' => 5,
            ],
            'consultation_reminder' => [
                'max_per_hour' => 3,
                'max_per_day' => 10,
            ],
            'payment_reminder' => [
                'max_per_hour' => 1,
                'max_per_day' => 3,
            ],
            'sms' => [
                'max_per_hour' => 5,
                'max_per_day' => 20,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SMS notification providers.
    |
    */

    'sms_providers' => [
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'vonage' => [
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from' => env('VONAGE_FROM'),
        ],
        'aws' => [
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for push notification providers.
    |
    */

    'push_providers' => [
        'fcm' => [
            'server_key' => env('FCM_SERVER_KEY'),
            'sender_id' => env('FCM_SENDER_ID'),
        ],
        'apn' => [
            'certificate_path' => env('APN_CERTIFICATE_PATH'),
            'certificate_passphrase' => env('APN_CERTIFICATE_PASSPHRASE'),
            'production' => env('APN_PRODUCTION', false),
        ],
        'onesignal' => [
            'app_id' => env('ONESIGNAL_APP_ID'),
            'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Preferences
    |--------------------------------------------------------------------------
    |
    | Default user notification preferences and available options.
    |
    */

    'user_preferences' => [
        'defaults' => [
            'booking_confirmations' => ['mail', 'database'],
            'booking_reminders' => ['mail', 'database'],
            'consultation_reminders' => ['mail', 'database'],
            'payment_reminders' => ['mail', 'database'],
            'marketing_emails' => [],
        ],
        'available_channels' => [
            'mail' => 'Email',
            'database' => 'In-App Notifications',
            'sms' => 'SMS',
            'push' => 'Push Notifications',
        ],
        'available_types' => [
            'booking_confirmations' => 'Booking Confirmations',
            'booking_reminders' => 'Booking Reminders',
            'booking_updates' => 'Booking Updates',
            'consultation_reminders' => 'Consultation Reminders',
            'payment_reminders' => 'Payment Reminders',
            'marketing_emails' => 'Marketing & Promotions',
            'system_updates' => 'System Updates',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cleanup of old notifications and logs.
    |
    */

    'cleanup' => [
        'enabled' => env('NOTIFICATIONS_CLEANUP_ENABLED', true),
        'retention_days' => [
            'read_notifications' => env('NOTIFICATIONS_READ_RETENTION_DAYS', 30),
            'unread_notifications' => env('NOTIFICATIONS_UNREAD_RETENTION_DAYS', 90),
            'notification_logs' => env('NOTIFICATIONS_LOG_RETENTION_DAYS', 60),
            'failed_notifications' => env('NOTIFICATIONS_FAILED_RETENTION_DAYS', 7),
        ],
        'batch_size' => env('NOTIFICATIONS_CLEANUP_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for testing notifications without sending them.
    |
    */

    'testing' => [
        'fake_notifications' => env('FAKE_NOTIFICATIONS', false),
        'log_fake_notifications' => env('LOG_FAKE_NOTIFICATIONS', true),
        'test_phone_numbers' => [
            env('TEST_SMS_NUMBER'),
        ],
        'test_email_addresses' => [
            env('TEST_EMAIL_ADDRESS'),
        ],
    ],

];

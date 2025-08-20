<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class NotificationTemplateSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $this->command->info('Seeding notification templates and settings...');

        // Clear notification-related cache
        Cache::forget('notification_statistics');
        Cache::forget('notification_templates');

        // Create notification template records (if you have a templates table)
        // $this->seedNotificationTemplates();

        // Seed default notification settings in cache/config
        $this->seedNotificationSettings();

        // Seed notification preferences examples
        $this->seedNotificationPreferencesExamples();

        // Seed notification statistics for demo purposes
        $this->seedNotificationStatistics();

        $this->command->info('Notification templates and settings seeded successfully!');
    }

    /**
     * Seed notification template records
     * Note: This assumes you might create a notification_templates table in the future
     */
    private function seedNotificationTemplates(): void
    {
        $templates = [
            [
                'type' => 'booking_confirmation',
                'name' => 'Booking Confirmation',
                'description' => 'Sent when a booking is confirmed',
                'channels' => json_encode(['mail', 'database']),
                'subject_template' => 'Booking Confirmed - #{reference}',
                'email_template' => 'emails.booking.confirmation',
                'sms_template' => 'Booking #{reference} confirmed for {service_name} on {scheduled_date}. Reply STOP to opt out.',
                'push_template' => json_encode([
                    'title' => 'Booking Confirmed! 🎉',
                    'body' => 'Your {service_name} booking for {scheduled_date} has been confirmed.',
                ]),
                'variables' => json_encode([
                    'reference', 'service_name', 'scheduled_date', 'scheduled_time',
                    'client_name', 'total_amount', 'location_name'
                ]),
                'is_active' => true,
                'priority' => 'high',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'booking_reminder',
                'name' => 'Booking Reminder',
                'description' => 'Sent as reminders before bookings',
                'channels' => json_encode(['mail', 'database', 'sms']),
                'subject_template' => 'Reminder: Upcoming Service - #{reference}',
                'email_template' => 'emails.booking.reminder',
                'sms_template' => 'Reminder: Your {service_name} appointment #{reference} is {time_phrase} at {scheduled_time}. Reply STOP to opt out.',
                'push_template' => json_encode([
                    'title' => '⏰ Booking Reminder',
                    'body' => 'Your {service_name} appointment is {time_phrase}.',
                ]),
                'variables' => json_encode([
                    'reference', 'service_name', 'scheduled_date', 'scheduled_time',
                    'time_phrase', 'hours_until', 'location_name', 'urgency_level'
                ]),
                'is_active' => true,
                'priority' => 'normal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'booking_cancelled',
                'name' => 'Booking Cancelled',
                'description' => 'Sent when a booking is cancelled',
                'channels' => json_encode(['mail', 'database', 'sms']),
                'subject_template' => 'Booking Cancelled - #{reference}',
                'email_template' => 'emails.booking.cancelled',
                'sms_template' => 'Your {service_name} booking #{reference} for {scheduled_date} has been cancelled. Contact us for assistance.',
                'push_template' => json_encode([
                    'title' => '❌ Booking Cancelled',
                    'body' => 'Your {service_name} service for {scheduled_date} has been cancelled.',
                ]),
                'variables' => json_encode([
                    'reference', 'service_name', 'scheduled_date', 'cancellation_reason',
                    'refund_amount', 'cancelled_by'
                ]),
                'is_active' => true,
                'priority' => 'high',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'consultation_reminder',
                'name' => 'Consultation Reminder',
                'description' => 'Sent before consultation appointments',
                'channels' => json_encode(['mail', 'database', 'sms']),
                'subject_template' => 'Reminder: Consultation Tomorrow - #{reference}',
                'email_template' => 'emails.consultation.reminder',
                'sms_template' => 'Reminder: Your consultation #{reference} is {time_phrase} at {scheduled_time}. Join: {meeting_link}',
                'push_template' => json_encode([
                    'title' => '🎥 Consultation Reminder',
                    'body' => 'Your consultation is scheduled for {scheduled_datetime}.',
                ]),
                'variables' => json_encode([
                    'reference', 'scheduled_date', 'scheduled_time', 'time_phrase',
                    'format', 'meeting_link', 'dial_in_number', 'meeting_location'
                ]),
                'is_active' => true,
                'priority' => 'normal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'consultation_starting_soon',
                'name' => 'Consultation Starting Soon',
                'description' => 'Urgent notification when consultation is about to start',
                'channels' => json_encode(['database', 'sms', 'push']),
                'subject_template' => 'Starting Soon: Your Consultation - #{reference}',
                'email_template' => 'emails.consultation.starting_soon',
                'sms_template' => '🚀 Your consultation #{reference} is starting {time_phrase}! Join: {meeting_link}',
                'push_template' => json_encode([
                    'title' => '🚀 Consultation Starting Soon!',
                    'body' => 'Your consultation is starting {time_phrase}. Tap to join now.',
                ]),
                'variables' => json_encode([
                    'reference', 'time_phrase', 'minutes_until', 'meeting_link',
                    'format', 'access_code', 'dial_in_number'
                ]),
                'is_active' => true,
                'priority' => 'urgent',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'payment_reminder',
                'name' => 'Payment Reminder',
                'description' => 'Sent to remind about pending payments',
                'channels' => json_encode(['mail', 'database']),
                'subject_template' => 'Payment Reminder - #{reference}',
                'email_template' => 'emails.payment.reminder',
                'sms_template' => 'Payment reminder: {amount_due} is due for booking #{reference}. Complete payment to secure your appointment.',
                'push_template' => json_encode([
                    'title' => '💳 Payment Reminder',
                    'body' => 'Payment of {amount_due} is due for your upcoming service.',
                ]),
                'variables' => json_encode([
                    'reference', 'amount_due', 'due_date', 'service_name',
                    'scheduled_date', 'is_overdue', 'days_overdue'
                ]),
                'is_active' => true,
                'priority' => 'normal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Only seed if using a notification_templates table
        // DB::table('notification_templates')->insert($templates);

        // For now, store in cache as example data
        Cache::put('notification_templates', collect($templates), now()->addDays(30));

        $this->command->info('✓ Notification templates seeded');
    }

    /**
     * Seed notification settings in cache
     */
    private function seedNotificationSettings(): void
    {
        $settings = [
            'default_preferences' => [
                'booking_confirmations' => ['mail', 'database'],
                'booking_reminders' => ['mail', 'database'],
                'booking_updates' => ['mail', 'database'],
                'consultation_reminders' => ['mail', 'database', 'sms'],
                'payment_reminders' => ['mail', 'database'],
                'marketing_emails' => [],
                'system_updates' => ['database'],
            ],
            'reminder_schedules' => [
                'booking' => [
                    'enabled' => true,
                    'times' => [24, 2], // hours before
                    'channels' => ['mail', 'database'],
                ],
                'consultation' => [
                    'enabled' => true,
                    'times' => [24, 1], // hours before
                    'channels' => ['mail', 'database', 'sms'],
                ],
                'payment' => [
                    'enabled' => true,
                    'times' => [72, 24, 0], // hours before due
                    'channels' => ['mail', 'database'],
                    'overdue_times' => [24, 72, 168], // hours after due
                ],
            ],
            'rate_limits' => [
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
            'channel_settings' => [
                'mail' => [
                    'enabled' => true,
                    'queue' => 'emails',
                    'from_name' => config('app.name'),
                    'from_address' => config('mail.from.address'),
                ],
                'sms' => [
                    'enabled' => false,
                    'provider' => 'twilio',
                    'queue' => 'sms',
                ],
                'push' => [
                    'enabled' => false,
                    'provider' => 'fcm',
                    'queue' => 'push',
                ],
                'database' => [
                    'enabled' => true,
                    'queue' => 'notifications',
                ],
            ],
        ];

        Cache::put('notification_settings', $settings, now()->addDays(30));

        $this->command->info('✓ Notification settings seeded');
    }

    /**
     * Seed example notification preferences for different user types
     */
    private function seedNotificationPreferencesExamples(): void
    {
        $preferencesExamples = [
            'default_user' => [
                'booking_confirmations' => ['mail', 'database'],
                'booking_reminders' => ['mail', 'database'],
                'booking_updates' => ['mail', 'database'],
                'consultation_reminders' => ['mail', 'database'],
                'payment_reminders' => ['mail', 'database'],
                'marketing_emails' => [],
                'system_updates' => ['database'],
                'preferred_time' => 'any',
                'timezone' => 'Europe/London',
                'reminder_frequency' => 'all',
            ],
            'sms_enabled_user' => [
                'booking_confirmations' => ['mail', 'database', 'sms'],
                'booking_reminders' => ['mail', 'database', 'sms'],
                'booking_updates' => ['mail', 'database', 'sms'],
                'consultation_reminders' => ['mail', 'database', 'sms'],
                'payment_reminders' => ['mail', 'database'],
                'marketing_emails' => ['mail'],
                'system_updates' => ['database'],
                'preferred_time' => 'business_hours',
                'timezone' => 'Europe/London',
                'reminder_frequency' => 'all',
            ],
            'minimal_notifications_user' => [
                'booking_confirmations' => ['mail'],
                'booking_reminders' => ['mail'],
                'booking_updates' => ['mail'],
                'consultation_reminders' => ['mail'],
                'payment_reminders' => ['mail'],
                'marketing_emails' => [],
                'system_updates' => [],
                'preferred_time' => 'business_hours',
                'timezone' => 'Europe/London',
                'reminder_frequency' => 'important_only',
            ],
            'push_enabled_user' => [
                'booking_confirmations' => ['mail', 'database', 'push'],
                'booking_reminders' => ['mail', 'database', 'push'],
                'booking_updates' => ['mail', 'database', 'push'],
                'consultation_reminders' => ['mail', 'database', 'push'],
                'payment_reminders' => ['mail', 'database', 'push'],
                'marketing_emails' => ['mail'],
                'system_updates' => ['database', 'push'],
                'preferred_time' => 'any',
                'timezone' => 'Europe/London',
                'reminder_frequency' => 'all',
            ],
        ];

        Cache::put('notification_preferences_examples', $preferencesExamples, now()->addDays(30));

        $this->command->info('✓ Notification preference examples seeded');
    }

    /**
     * Seed notification statistics for demo/testing
     */
    private function seedNotificationStatistics(): void
    {
        $statistics = [
            'total_notifications' => 1250,
            'by_status' => [
                'sent' => 1020,
                'pending' => 45,
                'failed' => 28,
                'cancelled' => 12,
                'delivered' => 980,
                'read' => 735,
            ],
            'by_type' => [
                'booking_confirmation' => 320,
                'booking_reminder' => 450,
                'consultation_reminder' => 180,
                'payment_reminder' => 220,
                'booking_cancelled' => 35,
                'consultation_starting_soon' => 45,
            ],
            'by_channel' => [
                'mail' => 890,
                'database' => 1250,
                'sms' => 125,
                'push' => 85,
            ],
            'success_rate' => 94.2,
            'recent_activity' => [
                'sent' => 45,
                'pending' => 12,
                'failed' => 3,
                'delivered' => 42,
            ],
            'performance_metrics' => [
                'avg_processing_time_ms' => 150,
                'avg_delivery_time_ms' => 1200,
                'avg_open_rate' => 68.5,
                'avg_click_rate' => 12.3,
            ],
            'calculated_at' => now()->toISOString(),
        ];

        Cache::put('notification_statistics', $statistics, now()->addHours(6));

        $this->command->info('✓ Notification statistics seeded');
    }

    /**
     * Create sample notification preference templates
     */
    private function createNotificationPreferenceTemplates(): array
    {
        return [
            'business_customer' => [
                'name' => 'Business Customer',
                'description' => 'Professional settings for business customers',
                'preferences' => [
                    'booking_confirmations' => ['mail', 'database'],
                    'booking_reminders' => ['mail', 'database'],
                    'booking_updates' => ['mail', 'database'],
                    'consultation_reminders' => ['mail', 'database', 'sms'],
                    'payment_reminders' => ['mail', 'database'],
                    'marketing_emails' => ['mail'],
                    'system_updates' => ['database'],
                ],
                'settings' => [
                    'preferred_time' => 'business_hours',
                    'timezone' => 'Europe/London',
                    'reminder_frequency' => 'all',
                    'do_not_disturb_start' => '18:00',
                    'do_not_disturb_end' => '09:00',
                    'do_not_disturb_days' => [0, 6], // Sunday, Saturday
                ],
            ],
            'individual_customer' => [
                'name' => 'Individual Customer',
                'description' => 'Standard settings for individual customers',
                'preferences' => [
                    'booking_confirmations' => ['mail', 'database'],
                    'booking_reminders' => ['mail', 'database'],
                    'booking_updates' => ['mail', 'database'],
                    'consultation_reminders' => ['mail', 'database'],
                    'payment_reminders' => ['mail', 'database'],
                    'marketing_emails' => [],
                    'system_updates' => ['database'],
                ],
                'settings' => [
                    'preferred_time' => 'any',
                    'timezone' => 'Europe/London',
                    'reminder_frequency' => 'all',
                ],
            ],
            'tech_savvy_customer' => [
                'name' => 'Tech-Savvy Customer',
                'description' => 'Full digital experience with all channels',
                'preferences' => [
                    'booking_confirmations' => ['mail', 'database', 'push'],
                    'booking_reminders' => ['database', 'push'],
                    'booking_updates' => ['database', 'push'],
                    'consultation_reminders' => ['database', 'push', 'sms'],
                    'payment_reminders' => ['database', 'push'],
                    'marketing_emails' => ['mail'],
                    'system_updates' => ['database', 'push'],
                ],
                'settings' => [
                    'preferred_time' => 'any',
                    'timezone' => 'Europe/London',
                    'reminder_frequency' => 'all',
                ],
            ],
        ];
    }

    /**
     * Seed notification channel configurations
     */
    private function seedChannelConfigurations(): void
    {
        $channelConfigs = [
            'mail' => [
                'name' => 'Email',
                'description' => 'Email notifications',
                'icon' => 'mail',
                'enabled' => true,
                'settings' => [
                    'queue' => 'emails',
                    'retry_attempts' => 3,
                    'retry_delay' => [30, 300, 1800], // seconds
                ],
            ],
            'sms' => [
                'name' => 'SMS',
                'description' => 'Text message notifications',
                'icon' => 'message-circle',
                'enabled' => false,
                'settings' => [
                    'queue' => 'sms',
                    'retry_attempts' => 3,
                    'retry_delay' => [30, 120, 300],
                    'character_limit' => 160,
                ],
            ],
            'push' => [
                'name' => 'Push Notifications',
                'description' => 'Mobile app push notifications',
                'icon' => 'smartphone',
                'enabled' => false,
                'settings' => [
                    'queue' => 'push',
                    'retry_attempts' => 3,
                    'retry_delay' => [30, 180, 600],
                    'ttl' => 86400, // 24 hours
                ],
            ],
            'database' => [
                'name' => 'In-App Notifications',
                'description' => 'Notifications within the application',
                'icon' => 'bell',
                'enabled' => true,
                'settings' => [
                    'queue' => 'notifications',
                    'retry_attempts' => 1,
                    'retention_days' => 30,
                ],
            ],
        ];

        Cache::put('notification_channel_configs', $channelConfigs, now()->addDays(30));

        $this->command->info('✓ Channel configurations seeded');
    }
}

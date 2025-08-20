<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Notification preferences stored as JSON
            $table->json('notification_preferences')->nullable()->after('email_verified_at');

            // Device tokens for push notifications
            $table->json('device_tokens')->nullable()->after('notification_preferences');

            // SMS preferences and phone verification
            $table->boolean('sms_notifications_enabled')->default(true)->after('device_tokens');
            $table->timestamp('phone_verified_at')->nullable()->after('sms_notifications_enabled');

            // Push notification preferences
            $table->boolean('push_notifications_enabled')->default(true)->after('phone_verified_at');

            // Email notification preferences (granular control)
            $table->boolean('email_notifications_enabled')->default(true)->after('push_notifications_enabled');
            $table->boolean('marketing_emails_enabled')->default(false)->after('email_notifications_enabled');

            // Notification delivery preferences
            $table->enum('preferred_notification_time', ['any', 'business_hours', 'evenings', 'weekends'])
                ->default('any')->after('marketing_emails_enabled');
            $table->string('timezone', 50)->nullable()->after('preferred_notification_time');

            // Do not disturb settings
            $table->time('do_not_disturb_start')->nullable()->after('timezone');
            $table->time('do_not_disturb_end')->nullable()->after('do_not_disturb_start');
            $table->json('do_not_disturb_days')->nullable()->after('do_not_disturb_end'); // Array of day numbers (0-6)

            // Communication frequency preferences
            $table->enum('reminder_frequency', ['all', 'important_only', 'minimal', 'none'])
                ->default('all')->after('do_not_disturb_days');

            // Last notification sent tracking
            $table->timestamp('last_notification_sent_at')->nullable()->after('reminder_frequency');
            $table->string('last_notification_type')->nullable()->after('last_notification_sent_at');

            // Unsubscribe tokens for easy opt-out
            $table->string('notification_unsubscribe_token', 64)->nullable()->unique()->after('last_notification_type');

            // Indexes for performance
            $table->index(['sms_notifications_enabled']);
            $table->index(['push_notifications_enabled']);
            $table->index(['email_notifications_enabled']);
            $table->index(['last_notification_sent_at']);
            $table->index(['notification_unsubscribe_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['sms_notifications_enabled']);
            $table->dropIndex(['push_notifications_enabled']);
            $table->dropIndex(['email_notifications_enabled']);
            $table->dropIndex(['last_notification_sent_at']);
            $table->dropIndex(['notification_unsubscribe_token']);

            $table->dropColumn([
                'notification_preferences',
                'device_tokens',
                'sms_notifications_enabled',
                'phone_verified_at',
                'push_notifications_enabled',
                'email_notifications_enabled',
                'marketing_emails_enabled',
                'preferred_notification_time',
                'timezone',
                'do_not_disturb_start',
                'do_not_disturb_end',
                'do_not_disturb_days',
                'reminder_frequency',
                'last_notification_sent_at',
                'last_notification_type',
                'notification_unsubscribe_token',
            ]);
        });
    }
};

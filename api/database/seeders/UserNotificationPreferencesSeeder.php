<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserNotificationPreferencesSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $this->command->info('Seeding user notification preferences...');

        // Get all existing users
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Creating sample users with notification preferences...');
            $this->createSampleUsersWithPreferences();
        } else {
            $this->command->info("Found {$users->count()} existing users. Adding notification preferences...");
            $this->updateExistingUsersWithPreferences($users);
        }

        $this->command->info('User notification preferences seeded successfully!');
    }

    /**
     * Create sample users with various notification preferences
     */
    private function createSampleUsersWithPreferences(): void
    {
        $sampleUsers = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@example.com',
                'phone' => '+447812345678',
                'preference_type' => 'business_customer',
                'marketing_opt_in' => true,
            ],
            [
                'name' => 'Michael Chen',
                'email' => 'michael.chen@example.com',
                'phone' => '+447987654321',
                'preference_type' => 'tech_savvy_customer',
                'marketing_opt_in' => true,
            ],
            [
                'name' => 'Emma Wilson',
                'email' => 'emma.wilson@example.com',
                'phone' => '+447555123456',
                'preference_type' => 'minimal_notifications',
                'marketing_opt_in' => false,
            ],
            [
                'name' => 'David Brown',
                'email' => 'david.brown@example.com',
                'phone' => '+447444567890',
                'preference_type' => 'standard_customer',
                'marketing_opt_in' => false,
            ],
            [
                'name' => 'Lisa Thompson',
                'email' => 'lisa.thompson@example.com',
                'phone' => '+447333987654',
                'preference_type' => 'all_channels_enabled',
                'marketing_opt_in' => true,
            ],
        ];

        foreach ($sampleUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $userData['phone'],
                'email_verified_at' => now(),
                'password' => bcrypt('password123'),
                'notification_preferences' => $this->getPreferencesForType($userData['preference_type']),
                'sms_notifications_enabled' => in_array($userData['preference_type'], ['tech_savvy_customer', 'all_channels_enabled']),
                'push_notifications_enabled' => in_array($userData['preference_type'], ['tech_savvy_customer', 'all_channels_enabled']),
                'email_notifications_enabled' => true,
                'marketing_emails_enabled' => $userData['marketing_opt_in'],
                'preferred_notification_time' => $this->getPreferredTimeForType($userData['preference_type']),
                'timezone' => 'Europe/London',
                'reminder_frequency' => $this->getReminderFrequencyForType($userData['preference_type']),
                'notification_unsubscribe_token' => Str::random(32),
                'phone_verified_at' => in_array($userData['preference_type'], ['tech_savvy_customer', 'all_channels_enabled']) ? now() : null,
            ]);

            // Add device tokens for push-enabled users
            if (in_array($userData['preference_type'], ['tech_savvy_customer', 'all_channels_enabled'])) {
                $this->addDeviceTokens($user);
            }

            // Add do not disturb settings for business customers
            if ($userData['preference_type'] === 'business_customer') {
                $this->addDoNotDisturbSettings($user);
            }
        }

        $this->command->info('✓ Created ' . count($sampleUsers) . ' sample users with preferences');
    }

    /**
     * Update existing users with notification preferences
     */
    private function updateExistingUsersWithPreferences($users): void
    {
        $preferenceTypes = [
            'standard_customer',
            'business_customer',
            'tech_savvy_customer',
            'minimal_notifications',
            'all_channels_enabled'
        ];

        $updated = 0;

        foreach ($users as $user) {
            // Skip if user already has notification preferences
            if ($user->notification_preferences) {
                continue;
            }

            // Randomly assign a preference type
            $preferenceType = $preferenceTypes[array_rand($preferenceTypes)];

            $user->update([
                'notification_preferences' => $this->getPreferencesForType($preferenceType),
                'sms_notifications_enabled' => $this->getSmsEnabledForType($preferenceType),
                'push_notifications_enabled' => $this->getPushEnabledForType($preferenceType),
                'email_notifications_enabled' => true,
                'marketing_emails_enabled' => rand(0, 1) === 1,
                'preferred_notification_time' => $this->getPreferredTimeForType($preferenceType),
                'timezone' => $this->getRandomTimezone(),
                'reminder_frequency' => $this->getReminderFrequencyForType($preferenceType),
                'notification_unsubscribe_token' => Str::random(32),
            ]);

            // Add phone verification for SMS-enabled users
            if ($this->getSmsEnabledForType($preferenceType) && $user->phone) {
                $user->update(['phone_verified_at' => now()]);
            }

            // Add device tokens for push-enabled users
            if ($this->getPushEnabledForType($preferenceType)) {
                $this->addDeviceTokens($user);
            }

            // Add do not disturb for business customers
            if ($preferenceType === 'business_customer') {
                $this->addDoNotDisturbSettings($user);
            }

            $updated++;
        }

        $this->command->info("✓ Updated {$updated} existing users with notification preferences");
    }

    /**
     * Get notification preferences for a given type
     */
    private function getPreferencesForType(string $type): array
    {
        return match($type) {
            'business_customer' => [
                'booking_confirmations' => ['mail', 'database'],
                'booking_reminders' => ['mail', 'database'],
                'booking_updates' => ['mail', 'database'],
                'consultation_reminders' => ['mail', 'database', 'sms'],
                'payment_reminders' => ['mail', 'database'],
                'marketing_emails' => ['mail'],
                'system_updates' => ['database'],
            ],
            'tech_savvy_customer' => [
                'booking_confirmations' => ['mail', 'database', 'push'],
                'booking_reminders' => ['database', 'push'],
                'booking_updates' => ['database', 'push'],
                'consultation_reminders' => ['database', 'push', 'sms'],
                'payment_reminders' => ['database', 'push'],
                'marketing_emails' => ['mail'],
                'system_updates' => ['database', 'push'],
            ],
            'minimal_notifications' => [
                'booking_confirmations' => ['mail'],
                'booking_reminders' => ['mail'],
                'booking_updates' => ['mail'],
                'consultation_reminders' => ['mail'],
                'payment_reminders' => ['mail'],
                'marketing_emails' => [],
                'system_updates' => [],
            ],
            'all_channels_enabled' => [
                'booking_confirmations' => ['mail', 'database', 'sms', 'push'],
                'booking_reminders' => ['mail', 'database', 'sms', 'push'],
                'booking_updates' => ['mail', 'database', 'sms', 'push'],
                'consultation_reminders' => ['mail', 'database', 'sms', 'push'],
                'payment_reminders' => ['mail', 'database', 'push'],
                'marketing_emails' => ['mail'],
                'system_updates' => ['database', 'push'],
            ],
            default => [ // 'standard_customer'
                'booking_confirmations' => ['mail', 'database'],
                'booking_reminders' => ['mail', 'database'],
                'booking_updates' => ['mail', 'database'],
                'consultation_reminders' => ['mail', 'database'],
                'payment_reminders' => ['mail', 'database'],
                'marketing_emails' => [],
                'system_updates' => ['database'],
            ],
        };
    }

    /**
     * Get preferred notification time for type
     */
    private function getPreferredTimeForType(string $type): string
    {
        return match($type) {
            'business_customer' => 'business_hours',
            'minimal_notifications' => 'business_hours',
            default => 'any',
        };
    }

    /**
     * Get reminder frequency for type
     */
    private function getReminderFrequencyForType(string $type): string
    {
        return match($type) {
            'minimal_notifications' => 'important_only',
            'business_customer' => 'all',
            default => 'all',
        };
    }

    /**
     * Check if SMS should be enabled for type
     */
    private function getSmsEnabledForType(string $type): bool
    {
        return in_array($type, ['tech_savvy_customer', 'all_channels_enabled', 'business_customer']);
    }

    /**
     * Check if push should be enabled for type
     */
    private function getPushEnabledForType(string $type): bool
    {
        return in_array($type, ['tech_savvy_customer', 'all_channels_enabled']);
    }

    /**
     * Add device tokens for push notifications
     */
    private function addDeviceTokens(User $user): void
    {
        $deviceTokens = [
            // Mock FCM tokens (real tokens are much longer)
            'fcm_token_' . Str::random(20) . '_android',
            'fcm_token_' . Str::random(20) . '_ios',
        ];

        $user->update([
            'device_tokens' => $deviceTokens,
        ]);
    }

    /**
     * Add do not disturb settings
     */
    private function addDoNotDisturbSettings(User $user): void
    {
        $user->update([
            'do_not_disturb_start' => '18:00:00',
            'do_not_disturb_end' => '09:00:00',
            'do_not_disturb_days' => [0, 6], // Sunday and Saturday
        ]);
    }

    /**
     * Get random timezone
     */
    private function getRandomTimezone(): string
    {
        $timezones = [
            'Europe/London',
            'Europe/Paris',
            'America/New_York',
            'America/Los_Angeles',
            'Australia/Sydney',
            'Asia/Tokyo',
        ];

        return $timezones[array_rand($timezones)];
    }

    /**
     * Create notification preference statistics
     */
    private function createPreferenceStatistics(): void
    {
        $users = User::whereNotNull('notification_preferences')->get();

        $stats = [
            'total_users' => $users->count(),
            'channel_adoption' => [
                'email' => $users->where('email_notifications_enabled', true)->count(),
                'sms' => $users->where('sms_notifications_enabled', true)->count(),
                'push' => $users->where('push_notifications_enabled', true)->count(),
                'marketing' => $users->where('marketing_emails_enabled', true)->count(),
            ],
            'preference_distribution' => [
                'all_notifications' => $users->where('reminder_frequency', 'all')->count(),
                'important_only' => $users->where('reminder_frequency', 'important_only')->count(),
                'minimal' => $users->where('reminder_frequency', 'minimal')->count(),
                'none' => $users->where('reminder_frequency', 'none')->count(),
            ],
            'time_preferences' => [
                'any_time' => $users->where('preferred_notification_time', 'any')->count(),
                'business_hours' => $users->where('preferred_notification_time', 'business_hours')->count(),
                'evenings' => $users->where('preferred_notification_time', 'evenings')->count(),
                'weekends' => $users->where('preferred_notification_time', 'weekends')->count(),
            ],
            'do_not_disturb_enabled' => $users->whereNotNull('do_not_disturb_start')->count(),
            'phone_verified' => $users->whereNotNull('phone_verified_at')->count(),
            'generated_at' => now()->toISOString(),
        ];

        // Store in database or cache for admin dashboard
        DB::table('settings')->updateOrInsert(
            ['key' => 'notification_preference_stats'],
            [
                'value' => json_encode($stats),
                'updated_at' => now(),
            ]
        );

        $this->command->info('✓ Generated notification preference statistics');
    }

    /**
     * Create sample unsubscribe scenarios
     */
    private function createUnsubscribeScenarios(): void
    {
        $users = User::inRandomOrder()->limit(3)->get();

        foreach ($users as $user) {
            // Simulate some users who have unsubscribed from marketing
            if (rand(0, 1)) {
                $user->update(['marketing_emails_enabled' => false]);
            }

            // Simulate some users who have disabled SMS
            if (rand(0, 1)) {
                $user->update(['sms_notifications_enabled' => false]);
            }

            // Simulate some users with minimal preferences
            if (rand(0, 2) === 0) {
                $user->update([
                    'reminder_frequency' => 'minimal',
                    'notification_preferences' => [
                        'booking_confirmations' => ['mail'],
                        'booking_reminders' => [],
                        'booking_updates' => ['mail'],
                        'consultation_reminders' => ['mail'],
                        'payment_reminders' => ['mail'],
                        'marketing_emails' => [],
                        'system_updates' => [],
                    ],
                ]);
            }
        }

        $this->command->info('✓ Created sample unsubscribe scenarios');
    }

    /**
     * Validate notification preferences
     */
    private function validateNotificationPreferences(): void
    {
        $users = User::whereNotNull('notification_preferences')->get();
        $errors = [];

        foreach ($users as $user) {
            $preferences = $user->notification_preferences;

            // Check if preferences is valid JSON structure
            if (!is_array($preferences)) {
                $errors[] = "User {$user->id}: Invalid preferences format";
                continue;
            }

            // Check for required preference types
            $requiredTypes = [
                'booking_confirmations',
                'booking_reminders',
                'consultation_reminders',
                'payment_reminders'
            ];

            foreach ($requiredTypes as $type) {
                if (!isset($preferences[$type])) {
                    $errors[] = "User {$user->id}: Missing {$type} preferences";
                }
            }

            // Validate channels
            foreach ($preferences as $type => $channels) {
                if (!is_array($channels)) {
                    $errors[] = "User {$user->id}: Invalid channels for {$type}";
                    continue;
                }

                $validChannels = ['mail', 'sms', 'push', 'database'];
                foreach ($channels as $channel) {
                    if (!in_array($channel, $validChannels)) {
                        $errors[] = "User {$user->id}: Invalid channel '{$channel}' for {$type}";
                    }
                }
            }

            // Validate SMS preferences match SMS enabled flag
            $hasSmsPrefs = collect($preferences)->flatten()->contains('sms');
            if ($hasSmsPrefs && !$user->sms_notifications_enabled) {
                $errors[] = "User {$user->id}: SMS in preferences but SMS disabled";
            }

            // Validate push preferences match push enabled flag
            $hasPushPrefs = collect($preferences)->flatten()->contains('push');
            if ($hasPushPrefs && !$user->push_notifications_enabled) {
                $errors[] = "User {$user->id}: Push in preferences but push disabled";
            }
        }

        if (empty($errors)) {
            $this->command->info('✓ All notification preferences are valid');
        } else {
            $this->command->warn('Found ' . count($errors) . ' validation errors:');
            foreach ($errors as $error) {
                $this->command->warn('  - ' . $error);
            }
        }
    }

    /**
     * Generate preference migration report
     */
    private function generateMigrationReport(): void
    {
        $totalUsers = User::count();
        $usersWithPrefs = User::whereNotNull('notification_preferences')->count();
        $smsEnabled = User::where('sms_notifications_enabled', true)->count();
        $pushEnabled = User::where('push_notifications_enabled', true)->count();
        $marketingOptIn = User::where('marketing_emails_enabled', true)->count();

        $report = [
            'migration_date' => now()->toDateString(),
            'total_users' => $totalUsers,
            'users_with_preferences' => $usersWithPrefs,
            'migration_coverage' => $totalUsers > 0 ? round(($usersWithPrefs / $totalUsers) * 100, 2) : 0,
            'channel_adoption' => [
                'sms_enabled' => $smsEnabled,
                'push_enabled' => $pushEnabled,
                'marketing_opt_in' => $marketingOptIn,
            ],
            'adoption_rates' => [
                'sms_rate' => $totalUsers > 0 ? round(($smsEnabled / $totalUsers) * 100, 2) : 0,
                'push_rate' => $totalUsers > 0 ? round(($pushEnabled / $totalUsers) * 100, 2) : 0,
                'marketing_rate' => $totalUsers > 0 ? round(($marketingOptIn / $totalUsers) * 100, 2) : 0,
            ],
        ];

        $this->command->info('');
        $this->command->info('=== NOTIFICATION PREFERENCES MIGRATION REPORT ===');
        $this->command->info("Total Users: {$report['total_users']}");
        $this->command->info("Users with Preferences: {$report['users_with_preferences']} ({$report['migration_coverage']}%)");
        $this->command->info("SMS Enabled: {$report['channel_adoption']['sms_enabled']} ({$report['adoption_rates']['sms_rate']}%)");
        $this->command->info("Push Enabled: {$report['channel_adoption']['push_enabled']} ({$report['adoption_rates']['push_rate']}%)");
        $this->command->info("Marketing Opt-in: {$report['channel_adoption']['marketing_opt_in']} ({$report['adoption_rates']['marketing_rate']}%)");
        $this->command->info('================================================');
    }
}

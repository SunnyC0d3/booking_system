<?php

use Illuminate\Support\Facades\Schedule;

use App\Console\Commands\RevokeExpiredTokens;

// Authentication & Security
Schedule::command('passport:purge')->hourly();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command(RevokeExpiredTokens::class)->everyThirtyMinutes();

// Communication & Notifications
Schedule::command('notifications:send-pending')->everyFiveMinutes();

// Maintenance & Cleanup
Schedule::command('logs:cleanup', ['--days=30'])->dailyAt('01:00');
Schedule::command('cache:cleanup-expired')->hourlyAt(15);
Schedule::command('queue:retry', ['--queue=failed'])->dailyAt('04:00');

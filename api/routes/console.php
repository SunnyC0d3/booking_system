<?php

use Illuminate\Support\Facades\Schedule;

use App\Console\Commands\RevokeExpiredTokens;

// Authentication & Security
Schedule::command('passport:purge')->hourly();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command(RevokeExpiredTokens::class)->everyThirtyMinutes();

// Order Management
Schedule::command('orders:process-overdue-shipments')->hourly();
Schedule::command('orders:auto-cancel-abandoned')->dailyAt('03:00');

// Communication & Notifications
Schedule::command('reviews:send-digest')->weeklyOn(1, '10:00');
Schedule::command('notifications:send-pending')->everyFiveMinutes();

// Maintenance & Cleanup
Schedule::command('logs:cleanup', ['--days=30'])->dailyAt('01:00');
Schedule::command('cache:cleanup-expired')->hourlyAt(15);
Schedule::command('queue:retry', ['--queue=failed'])->dailyAt('04:00');

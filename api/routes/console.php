<?php

use Illuminate\Support\Facades\Schedule;

use App\Console\Commands\CleanupEmptyCarts;
use App\Console\Commands\CleanupExpiredCarts;
use App\Console\Commands\RevokeExpiredTokens;
use App\Console\Commands\SyncSupplierStock;
use App\Console\Commands\RetryFailedDropshipOrders;
use App\Console\Commands\CheckSupplierHealth;
use App\Console\Commands\ProcessAutoFulfillment;
use App\Console\Commands\SendWeeklyReviewDigest;
use App\Console\Commands\CleanupOldLogs;

// Authentication & Security
Schedule::command('passport:purge')->hourly();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command(RevokeExpiredTokens::class)->everyThirtyMinutes();

// Cart Management
Schedule::command(CleanupExpiredCarts::class)->hourly();
Schedule::command(CleanupEmptyCarts::class, ['--days=7'])->dailyAt('02:00');

// Inventory Management
Schedule::command('inventory:check')->hourly();
Schedule::command('inventory:check', ['--force'])->dailyAt('09:00'); // Morning alert

// Dropshipping & Supplier Management
Schedule::command('dropship:sync-stock')->everyThirtyMinutes();
Schedule::command('dropship:retry-failed')->hourly();
Schedule::command('supplier:health-check')->dailyAt('06:00');
Schedule::command('dropship:auto-fulfill')->everyFifteenMinutes();

// Order Management
Schedule::command('orders:process-overdue-shipments')->hourly();
Schedule::command('orders:auto-cancel-abandoned')->dailyAt('03:00');

// Communication & Notifications
Schedule::command('reviews:send-digest')->weeklyOn(1, '10:00'); // Monday 10 AM
Schedule::command('notifications:send-pending')->everyFiveMinutes();

// Maintenance & Cleanup
Schedule::command('logs:cleanup', ['--days=30'])->dailyAt('01:00');
Schedule::command('cache:cleanup-expired')->hourlyAt(15);
Schedule::command('queue:retry', ['--queue=failed'])->dailyAt('04:00');

// Analytics & Reporting
Schedule::command('analytics:process-daily')->dailyAt('05:00');
Schedule::command('reports:generate-weekly')->weeklyOn(1, '07:00');

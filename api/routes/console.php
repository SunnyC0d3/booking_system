<?php

use Illuminate\Support\Facades\Schedule;

use App\Console\Commands\CleanupEmptyCarts;
use App\Console\Commands\CleanupExpiredCarts;
use App\Console\Commands\RevokeExpiredTokens;

Schedule::command('passport:purge')->hourly();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command(RevokeExpiredTokens::class)->everyThirtyMinutes();
Schedule::command(CleanupExpiredCarts::class)->hourly();
Schedule::command(CleanupEmptyCarts::class, ['--days=7'])->dailyAt('02:00');
Schedule::command('inventory:check')->hourly();

<?php

use Illuminate\Support\Facades\Schedule;

use \App\Console\Commands\RevokeExpiredTokens;

Schedule::command('passport:purge')->hourly();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
Schedule::command(RevokeExpiredTokens::class)->everyThirtyMinutes();

<?php

use Illuminate\Support\Facades\Schedule;
 
Schedule::command('passport:purge')->hourly();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();

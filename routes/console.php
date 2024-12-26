<?php

use Illuminate\Support\Facades\Schedule;
 
Schedule::command('passport:purge')->hourly();

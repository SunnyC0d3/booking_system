<?php

namespace App\Constants;

class AccountLockSettings
{
    public const MAX_LOGIN_ATTEMPTS = 5;
    public const LOCKOUT_DURATION_MINUTES = 30;
    public const PROGRESSIVE_LOCKOUT_ENABLED = true;

    public const LOCKOUT_DURATIONS = [
        1 => 5,   // 1st lockout: 5 minutes
        2 => 15,  // 2nd lockout: 15 minutes
        3 => 30,  // 3rd lockout: 30 minutes
        4 => 60,  // 4th lockout: 1 hour
        5 => 240, // 5th+ lockout: 4 hours
    ];

    public const RESET_ATTEMPTS_AFTER_HOURS = 24;
}

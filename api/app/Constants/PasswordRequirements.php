<?php

namespace App\Constants;

class PasswordRequirements
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 128;

    public const REQUIRE_UPPERCASE = true;
    public const REQUIRE_LOWERCASE = true;
    public const REQUIRE_NUMBERS = true;
    public const REQUIRE_SYMBOLS = true;

    public const MIN_UPPERCASE = 1;
    public const MIN_LOWERCASE = 1;
    public const MIN_NUMBERS = 1;
    public const MIN_SYMBOLS = 1;

    public const COMMON_PASSWORDS = [
        'password', 'password123', '123456789', 'qwerty123',
        'admin123', 'welcome123', 'letmein123', 'monkey123',
        'dragon123', 'master123', 'shadow123', 'football123'
    ];

    public const SEQUENTIAL_PATTERNS = [
        '123456', '654321', 'abcdef', 'fedcba',
        'qwerty', 'asdfgh', 'zxcvbn'
    ];

    public const REPEATED_CHARS_MAX = 3;

    public const PASSWORD_HISTORY_COUNT = 5;
}

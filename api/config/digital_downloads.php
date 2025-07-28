<?php

return [
    'max_file_size' => env('DIGITAL_DOWNLOAD_MAX_FILE_SIZE', 104857600), // 100MB
    'allowed_extensions' => explode(',', env('DIGITAL_DOWNLOAD_ALLOWED_EXTENSIONS', 'zip,exe,dmg,pkg,pdf,epub')),
    'default_expiry_days' => env('DIGITAL_DOWNLOAD_DEFAULT_EXPIRY_DAYS', 365),
    'default_download_limit' => env('DIGITAL_DOWNLOAD_DEFAULT_LIMIT', 3),
    'storage_disk' => env('DIGITAL_DOWNLOAD_STORAGE_DISK', 'local'),
    'cleanup' => [
        'expired_access_retention_days' => 30,
        'failed_attempt_retention_days' => 7,
    ],
    'security' => [
        'enforce_ip_validation' => true,
        'max_concurrent_downloads' => 2,
        'download_timeout_minutes' => 60,
    ],
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Define rate limits for different endpoint categories
    |
    */

    'auth' => [
        'login' => '5,1',           // 5 attempts per minute
        'register' => '3,1',        // 3 attempts per minute
        'password_reset' => '3,5',  // 3 attempts per 5 minutes
        'email_verify' => '10,1',   // 10 attempts per minute
    ],

    'api' => [
        'general' => '1000,1',      // 1000 requests per minute
        'search' => '30,1',         // 30 searches per minute
        'admin' => '100,1',         // 100 admin requests per minute
        'payments' => '10,1',       // 10 payment requests per minute
        'uploads' => '10,1',        // 10 upload requests per minute
        'downloads' => '50,1',      // 50 download requests per minute (NEW)
        'license_validation' => '100,1', // 100 license checks per minute (NEW)
    ],

    'guest' => [
        'products' => '30,1',       // 30 product views per minute
        'public' => '20,1',         // 20 public requests per minute
        'reviews' => '15,1',        // 15 review views per minute
        'review_votes' => '0,1',    // No voting for guests
        'review_reports' => '0,1',  // No reporting for guests
        'review_create' => '0,1',   // No review creation for guests
        'review_responses' => '5,1', // 5 response views per minute
    ],

    'reviews' => [
        'create' => '10,60',        // 10 reviews per hour
        'update' => '10,1',         // 10 review updates per minute
        'vote' => '20,1',           // 20 helpfulness votes per minute
        'report' => '3,5',          // 3 reports per 5 minutes
        'respond' => '10,5',        // 10 vendor responses per 5 minutes
        'moderate' => '50,1',       // 50 moderation actions per minute (admin)
        'bulk' => '5,5',            // 5 bulk operations per 5 minutes (admin)
    ],

    'returns' => [
        'attempts' => 10,           // 10 return requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many return requests. Please wait a moment.',
    ],

    'users' => [
        'attempts' => 40,           // 40 user profile requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many user requests. Please wait a moment.',
    ],

    // Password security
    'password_change' => [
        'attempts' => 5,            // 5 password changes per hour
        'decay_minutes' => 60,
        'message' => 'Too many password change attempts. Please wait an hour.',
    ],

    // Security info requests
    'security_info' => [
        'attempts' => 20,           // 20 security info requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many security requests. Please wait a moment.',
    ],

    // Email verification
    'email_resend' => [
        'attempts' => 3,            // 3 email resend attempts per 5 minutes
        'decay_minutes' => 5,
        'message' => 'Too many email resend attempts. Please wait 5 minutes.',
    ],

    // Webhook protection (especially for payment webhooks)
    'webhooks' => [
        'attempts' => 100,          // 100 webhook requests per minute
        'decay_minutes' => 1,
        'message' => 'Webhook rate limit exceeded.',
    ],

    // Payment verification
    'payment_verify' => [
        'attempts' => 15,           // 15 payment verification attempts per minute
        'decay_minutes' => 1,
        'message' => 'Too many payment verification attempts. Please wait a moment.',
    ],

    // Address validation (can be expensive)
    'address_validation' => [
        'attempts' => 10,           // 10 address validations per minute
        'decay_minutes' => 1,
        'message' => 'Too many address validation requests. Please wait a moment.',
    ],

    // General client limits (for non-authenticated requests)
    'client' => [
        'attempts' => 100,          // 100 general client requests per minute
        'decay_minutes' => 1,
        'message' => 'Rate limit exceeded. Please slow down.',
    ],
];

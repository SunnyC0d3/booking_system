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
        'general' => '60,1',        // 60 requests per minute
        'search' => '30,1',         // 30 searches per minute
        'admin' => '100,1',         // 100 admin requests per minute
        'payments' => '10,1',       // 10 payment requests per minute
        'uploads' => '5,1',         // 5 file uploads per minute
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

    'cart' => [
        'attempts' => 120, // 120 requests per minute for cart operations
        'decay_minutes' => 1,
        'message' => 'Too many cart requests. Please slow down.',
    ],

    'cart.add' => [
        'attempts' => 30, // 30 add-to-cart requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many items added to cart. Please wait a moment.',
    ],

    'cart.update' => [
        'attempts' => 60, // 60 update requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many cart updates. Please wait a moment.',
    ],

    'reviews' => [
        'create' => '5,5',          // 5 review creations per 5 minutes
        'update' => '10,1',         // 10 review updates per minute
        'vote' => '20,1',           // 20 helpfulness votes per minute
        'report' => '3,5',          // 3 reports per 5 minutes
        'respond' => '10,5',        // 10 vendor responses per 5 minutes
        'moderate' => '50,1',       // 50 moderation actions per minute (admin)
        'bulk' => '5,5',            // 5 bulk operations per 5 minutes (admin)
    ],

    'vendor' => [
        'responses' => '10,5',      // 10 vendor responses per 5 minutes
        'dashboard' => '30,1',      // 30 vendor dashboard requests per minute
    ],

    // NEW: Missing rate limits from routes analysis

    'shipping' => [
        'attempts' => 50,           // 50 shipping requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many shipping requests. Please wait a moment.',
    ],

    'tracking' => [
        'attempts' => 30,           // 30 tracking requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many tracking requests. Please wait a moment.',
    ],

    'checkout' => [
        'attempts' => 20,           // 20 checkout requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many checkout requests. Please wait a moment.',
    ],

    'orders' => [
        'attempts' => 30,           // 30 order requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many order requests. Please wait a moment.',
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

    'vendors.profile' => [
        'attempts' => 40,           // 40 vendor profile requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many vendor requests. Please wait a moment.',
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

    // Shipping address management
    'shipping_addresses' => [
        'attempts' => 30,           // 30 shipping address requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many shipping address requests. Please wait a moment.',
    ],

    // Address validation (can be expensive)
    'address_validation' => [
        'attempts' => 10,           // 10 address validations per minute
        'decay_minutes' => 1,
        'message' => 'Too many address validation requests. Please wait a moment.',
    ],

    // Shipping calculations (can be expensive with external APIs)
    'shipping_calculations' => [
        'attempts' => 40,           // 40 shipping calculations per minute
        'decay_minutes' => 1,
        'message' => 'Too many shipping calculation requests. Please wait a moment.',
    ],

    // Product-specific limits
    'products' => [
        'attempts' => 60,           // 60 product requests per minute
        'decay_minutes' => 1,
        'message' => 'Too many product requests. Please wait a moment.',
    ],

    // General client limits (for non-authenticated requests)
    'client' => [
        'attempts' => 100,          // 100 general client requests per minute
        'decay_minutes' => 1,
        'message' => 'Rate limit exceeded. Please slow down.',
    ],
];

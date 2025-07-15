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
];

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
    ],

    'bookings' => [
        'create' => '10,60',           // 10 booking creations per hour
        'update' => '20,60',           // 20 booking updates per hour
        'cancel' => '5,60',            // 5 cancellations per hour
        'reschedule' => '3,60',        // 3 reschedules per hour
        'view' => '100,60',            // 100 booking views per hour
        'consultation' => '10,60',     // 10 consultation completions per hour
    ],

    'consultations' => [
        'create' => '8,60',            // 8 consultation bookings per hour
        'update' => '15,60',           // 15 consultation updates per hour
        'cancel' => '5,60',            // 5 consultation cancellations per hour
        'reschedule' => '3,60',        // 3 consultation reschedules per hour
        'view' => '80,60',             // 80 consultation views per hour
        'join' => '20,60',             // 20 consultation joins per hour
        'feedback' => '10,60',         // 10 feedback submissions per hour
        'slots' => '40,60',            // 40 consultation slot requests per hour
    ],

    'availability' => [
        'check' => '60,60',            // 60 availability checks per hour
        'slots' => '30,60',            // 30 slot requests per hour
        'summary' => '20,60',          // 20 availability summaries per hour
        'pricing' => '30,60',          // 30 pricing estimates per hour
        'next_slot' => '20,60',        // 20 next available slot requests per hour
    ],

    'services' => [
        'view' => '200,60',            // 200 service views per hour (public)
        'locations' => '100,60',       // 100 location requests per hour
        'addons' => '100,60',          // 100 add-on requests per hour
        'packages' => '100,60',        // 100 package requests per hour
    ],

    'admin' => [
        'bookings.view' => '500,60',       // 500 booking views per hour for admins
        'bookings.create' => '100,60',     // 100 booking creations per hour for admins
        'bookings.update' => '200,60',     // 200 booking updates per hour for admins
        'bookings.delete' => '50,60',      // 50 booking deletions per hour for admins
        'consultations.view' => '300,60',  // 300 consultation views per hour for admins
        'consultations.create' => '50,60', // 50 consultation creations per hour for admins
        'consultations.update' => '100,60', // 100 consultation updates per hour for admins
        'consultations.delete' => '30,60', // 30 consultation deletions per hour for admins
        'consultations.assign' => '40,60', // 40 consultant assignments per hour
        'consultations.bulk' => '10,60',   // 10 bulk consultation operations per hour
        'consultations.export' => '5,60',  // 5 consultation exports per hour
        'statistics' => '50,60',           // 50 statistics requests per hour
        'calendar' => '100,60',            // 100 calendar requests per hour
        'export' => '10,60',               // 10 export requests per hour
        'bulk_operations' => '20,60',      // 20 bulk operations per hour
        'service_management' => '100,60',  // 100 service management operations per hour
        'location_management' => '50,60',  // 50 location management operations per hour
        'availability_management' => '100,60', // 100 availability management operations per hour
    ],

    'guest' => [
        'products' => '30,1',       // 30 product views per minute
        'public' => '20,1',         // 20 public requests per minute
        'reviews' => '15,1',        // 15 review views per minute
        'review_votes' => '0,1',    // No voting for guests
        'review_reports' => '0,1',  // No reporting for guests
        'review_create' => '0,1',   // No review creation for guests
        'review_responses' => '5,1', // 5 response views per minute
        'services.view' => '60,60',        // 60 service views per hour for guests
        'services.locations' => '30,60',   // 30 location requests per hour for guests
        'services.addons' => '30,60',      // 30 add-on requests per hour for guests
        'services.packages' => '30,60',    // 30 package requests per hour for guests
        'availability.check' => '20,60',   // 20 availability checks per hour for guests
        'availability.slots' => '10,60',   // 10 slot requests per hour for guests
        'availability.summary' => '5,60',  // 5 availability summaries per hour for guests
        'availability.pricing' => '10,60', // 10 pricing estimates per hour for guests
        'availability.next_slot' => '5,60', // 5 next slot requests per hour for guests
        'consultation.slots' => '10,60',   // 10 consultation slot requests per hour for guests
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

    // New booking-specific rate limits
    'booking_specific' => [
        'consultation_booking' => '5,60',   // 5 consultation bookings per hour
        'emergency_booking' => '2,60',      // 2 emergency bookings per hour
        'bulk_booking' => '1,60',           // 1 bulk booking operation per hour
        'package_booking' => '10,60',       // 10 package bookings per hour
        'recurring_booking' => '5,60',      // 5 recurring booking setups per hour
    ],

    // Service-specific limits
    'service_specific' => [
        'complex_service_booking' => '3,60',    // 3 complex service bookings per hour
        'consultation_scheduling' => '10,60',   // 10 consultation schedulings per hour
        'venue_search' => '50,60',              // 50 venue searches per hour
        'availability_bulk_check' => '10,60',   // 10 bulk availability checks per hour
    ],

    // Throttle group aliases for easier middleware usage
    'throttle_aliases' => [
        'consultation-api' => 'consultations.view',
        'consultation-create' => 'consultations.create',
        'consultation-update' => 'consultations.update',
        'admin-consultation' => 'admin.consultations.update',
        'admin-consultation-bulk' => 'admin.consultations.bulk',
    ],
];

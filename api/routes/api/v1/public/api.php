<?php

use Illuminate\Support\Facades\Route;

// Auth Controllers
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\EmailVerificationController;

// Other Controllers
use App\Http\Controllers\V1\Public\UserController;
use App\Http\Controllers\V1\Public\PaymentController;
use App\Http\Controllers\V1\Public\ReturnsController;
use App\Http\Controllers\V1\Public\BookingController;

// Auth

//Route::middleware(['throttle:3,1', 'client'])
Route::middleware(['client'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->middleware('rate_limit:auth.register')->name('auth.register');
        Route::post('/login', 'login')->middleware('rate_limit:auth.login')->name('auth.login');
    });

Route::middleware(['auth:api', 'account_lock', 'password_expiry'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
        Route::post('/change-password', 'changePassword')->middleware('rate_limit:auth.password_reset')->name('password.change');
        Route::get('/security-info', 'getSecurityInfo')->name('auth.security-info');
    });

// Email Verification

Route::prefix('email')
    ->middleware(['signed'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/verify/{id}/{hash}', 'verify')->name('verification.verify');
    });

Route::prefix('email')
    ->middleware(['auth:api'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/resend', 'resend')->name('verification.resend');
    });

// Password Reset

Route::middleware(['throttle:3,5', 'client'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/forgot-password', 'forgotPassword')->middleware('rate_limit:auth.password_reset')->name('password.email');
        Route::post('/reset-password', 'passwordReset')->middleware('rate_limit:auth.password_reset')->name('password.update');
    });

// Payments

Route::prefix('payments')
    ->middleware(['rate_limit:payments'])
    ->controller(PaymentController::class)
    ->group(function () {
        Route::post('/{gateway}/create', 'store')->name('payments.store');
        Route::post('/stripe/webhook', 'stripeWebhook')->name('payments.stripe.webhook');
        Route::post('/{gateway}/verify', 'verify')->name('payments.verify');
    });

// Users

Route::prefix('users')
    ->middleware(['auth:api', 'roles:user', 'emailVerified'])
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/{user}', 'show')->name('users.show');
        Route::post('/{user}', 'update')->name('users.update');
    });

// Returns

Route::prefix('returns')
    ->middleware(['auth:api', 'roles:user, vendor', 'emailVerified'])
    ->controller(ReturnsController::class)
    ->group(function () {
        Route::post('/', 'return')->name('returns');
    });


// Booking management routes

Route::prefix('bookings')
    ->middleware(['auth:api', 'roles:user', 'emailVerified'])
    ->controller(BookingController::class)
    ->group(function () {
        Route::get('/', 'index')->middleware('rate_limit:bookings.view')->name('bookings.index');
        Route::post('/{booking}/consultation/complete', 'completeConsultation')
            ->middleware('rate_limit:bookings.update')
            ->name('bookings.consultation.complete');
    });

// Service availability routes (public access with guest rate limiting)

Route::prefix('services/{service}')
    ->controller(BookingController::class)
    ->group(function () {
        Route::get('/slots', 'getAvailableSlots')
            ->middleware('rate_limit:availability.slots')
            ->name('services.slots');
        Route::get('/availability', 'getAvailabilitySummary')
            ->middleware('rate_limit:availability.summary')
            ->name('services.availability');
        Route::post('/slots/check', 'checkSlotAvailability')
            ->middleware('rate_limit:availability.check')
            ->name('services.slots.check');
        Route::get('/slots/next', 'getNextAvailableSlot')
            ->middleware('rate_limit:availability.slots')
            ->name('services.slots.next');
        Route::post('/pricing', 'getPricingEstimate')
            ->middleware('rate_limit:availability.pricing')
            ->name('services.pricing');
    });

// Public service information routes (no auth required, guest rate limiting)

Route::prefix('services')
    ->group(function () {
        Route::get('/', [ServiceController::class, 'index'])
            ->middleware('rate_limit:services.view')
            ->name('services.index');
        Route::get('/{service}', [ServiceController::class, 'show'])
            ->middleware('rate_limit:services.view')
            ->name('services.show');
        Route::get('/{service}/locations', [ServiceController::class, 'getLocations'])
            ->middleware('rate_limit:services.locations')
            ->name('services.locations');
        Route::get('/{service}/add-ons', [ServiceController::class, 'getAddOns'])
            ->middleware('rate_limit:services.addons')
            ->name('services.addons');
    });('/', 'store')->middleware('rate_limit:bookings.create')->name('bookings.store');
        Route::get('/{booking}', 'show')->middleware('rate_limit:bookings.view')->name('bookings.show');
        Route::put('/{booking}', 'update')->middleware('rate_limit:bookings.update')->name('bookings.update');
        Route::delete('/{booking}/cancel', 'cancel')->middleware('rate_limit:bookings.cancel')->name('bookings.cancel');
        Route::post('/{booking}/reschedule', 'reschedule')->middleware('rate_limit:bookings.reschedule')->name('bookings.reschedule');
        Route::post

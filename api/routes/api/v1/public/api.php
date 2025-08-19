<?php

use App\Http\Controllers\V1\Public\ConsultationController;
use App\Http\Controllers\V1\Public\ServiceController;
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
    ->middleware(['auth:api', 'roles:user', 'emailVerified'])
    ->controller(ReturnsController::class)
    ->group(function () {
        Route::post('/', 'return')->name('returns');
    });


// SERVICE INFORMATION ROUTES (Public Access)
Route::prefix('services')
    ->middleware(['client'])
    ->controller(ServiceController::class)
    ->group(function () {
        Route::get('/', 'index')
            ->middleware('rate_limit:services.view')
            ->name('services.index');
        Route::get('/{service}', 'show')
            ->middleware('rate_limit:services.view')
            ->name('services.show');
        Route::get('/{service}/locations', 'getLocations')
            ->middleware('rate_limit:services.view')
            ->name('services.locations');
        Route::get('/{service}/addons', 'getAddons')
            ->middleware('rate_limit:services.view')
            ->name('services.addons');
        Route::get('/{service}/packages', 'getPackages')
            ->middleware('rate_limit:services.view')
            ->name('services.packages');

        // Service availability (public access for checking slots)
        Route::get('/{service}/slots', [BookingController::class, 'getAvailableSlots'])
            ->middleware('rate_limit:availability.slots')
            ->name('services.slots');
    });

// BOOKING MANAGEMENT ROUTES (Authenticated Users)
Route::prefix('bookings')
    ->middleware(['auth:api', 'roles:user', 'emailVerified'])
    ->controller(BookingController::class)
    ->group(function () {
        // Core booking CRUD
        Route::get('/', 'index')
            ->middleware('rate_limit:bookings.view')
            ->name('bookings.index');
        Route::post('/', 'store')
            ->middleware('rate_limit:bookings.create')
            ->name('bookings.store');
        Route::get('/{booking}', 'show')
            ->middleware('rate_limit:bookings.view')
            ->name('bookings.show');
        Route::put('/{booking}', 'update')
            ->middleware('rate_limit:bookings.update')
            ->name('bookings.update');

        // Booking actions
        Route::delete('/{booking}/cancel', 'cancel')
            ->middleware('rate_limit:bookings.cancel')
            ->name('bookings.cancel');
        Route::post('/{booking}/reschedule', 'reschedule')
            ->middleware('rate_limit:bookings.reschedule')
            ->name('bookings.reschedule');

        // Consultation related
        Route::post('/{booking}/consultation/complete', 'completeConsultation')
            ->middleware('rate_limit:bookings.update')
            ->name('bookings.consultation.complete');

        // Notifications
        Route::post('/{booking}/notifications/resend-confirmation', 'resendConfirmation')
            ->middleware('rate_limit:bookings.notifications')
            ->name('bookings.notifications.resend-confirmation');
        Route::get('/{booking}/notifications/stats', 'getNotificationStats')
            ->middleware('rate_limit:bookings.notifications')
            ->name('bookings.notifications.stats');
    });

// CONSULTATION MANAGEMENT ROUTES (Authenticated Users)
Route::prefix('consultations')
    ->middleware(['auth:api', 'roles:user', 'emailVerified'])
    ->controller(ConsultationController::class)
    ->group(function () {
        // Core consultation CRUD
        Route::get('/', 'index')
            ->middleware('rate_limit:consultations.view')
            ->name('consultations.index');
        Route::post('/', 'store')
            ->middleware('rate_limit:consultations.create')
            ->name('consultations.store');
        Route::get('/{consultation}', 'show')
            ->middleware('rate_limit:consultations.view')
            ->name('consultations.show');
        Route::put('/{consultation}', 'update')
            ->middleware('rate_limit:consultations.update')
            ->name('consultations.update');

        // Consultation actions
        Route::delete('/{consultation}/cancel', 'cancel')
            ->middleware('rate_limit:consultations.cancel')
            ->name('consultations.cancel');
        Route::post('/{consultation}/reschedule', 'reschedule')
            ->middleware('rate_limit:consultations.reschedule')
            ->name('consultations.reschedule');
        Route::post('/{consultation}/join', 'join')
            ->middleware('rate_limit:consultations.join')
            ->name('consultations.join');
        Route::post('/{consultation}/feedback', 'provideFeedback')
            ->middleware('rate_limit:consultations.feedback')
            ->name('consultations.feedback');
    });

// BOOKING UTILITIES (Public & Authenticated)
Route::prefix('booking-utils')
    ->middleware(['client'])
    ->controller(BookingController::class)
    ->group(function () {
        // Booking summary calculation (no auth required for price checks)
        Route::post('/summary', 'getBookingSummary')
            ->middleware('rate_limit:booking.summary')
            ->name('booking.utils.summary');
    });

// SERVICE-SPECIFIC BOOKING ROUTES (Authenticated)
Route::prefix('services/{service}')
    ->middleware(['auth:api', 'emailVerified'])
    ->group(function () {
        // Direct service booking
        Route::post('/book', [BookingController::class, 'store'])
            ->middleware('rate_limit:bookings.create')
            ->name('services.book');

        // Service consultation booking
        Route::post('/consultations', [ConsultationController::class, 'store'])
            ->middleware('rate_limit:consultations.create')
            ->name('services.consultations.book');

        // Consultation availability
        Route::get('/consultations/slots', [ConsultationController::class, 'getAvailableSlots'])
            ->middleware('rate_limit:consultation.slots')
            ->name('services.consultation.slots');
    });

<?php

use App\Http\Controllers\V1\Public\VenueController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\EmailVerificationController;
use App\Http\Controllers\V1\Public\BookingController;
use App\Http\Controllers\V1\Public\CalendarController;
use App\Http\Controllers\V1\Public\ConsultationController;
use App\Http\Controllers\V1\Public\PaymentController;
use App\Http\Controllers\V1\Public\ReturnsController;
use App\Http\Controllers\V1\Public\ServiceController;
use App\Http\Controllers\V1\Public\UserController;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| Here are the public-facing API routes that handle user interactions
| with the booking system. These routes are organized by functionality
| and include appropriate middleware for security and rate limiting.
|
*/

//Auth

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

// CALENDAR INTEGRATION ROUTES (Authenticated Users)
Route::prefix('calendar')
    ->middleware(['auth:api', 'emailVerified'])
    ->controller(CalendarController::class)
    ->group(function () {
        // Core calendar integration management
        Route::get('/', 'index')
            ->middleware('rate_limit:calendar.view')
            ->name('calendar.integrations.index');

        // OAuth flow for connecting calendars
        Route::post('/oauth/initiate', 'initiateOAuth')
            ->middleware('rate_limit:calendar.oauth')
            ->name('calendar.oauth.initiate');

        Route::get('/oauth/callback', 'handleOAuthCallback')
            ->middleware('rate_limit:calendar.oauth')
            ->name('calendar.oauth.callback');

        // Integration management
        Route::put('/{integration}', 'update')
            ->middleware('rate_limit:calendar.update')
            ->name('calendar.integrations.update');

        Route::delete('/{integration}', 'destroy')
            ->middleware('rate_limit:calendar.delete')
            ->name('calendar.integrations.destroy');

        // Sync operations
        Route::post('/{integration}/sync', 'triggerSync')
            ->middleware('rate_limit:calendar.sync')
            ->name('calendar.integrations.sync');

        Route::get('/sync-status', 'getSyncStatus')
            ->middleware('rate_limit:calendar.status')
            ->name('calendar.sync.status');

        // Token management
        Route::post('/{integration}/refresh-tokens', 'refreshTokens')
            ->middleware('rate_limit:calendar.tokens')
            ->name('calendar.tokens.refresh');
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

// Calendar Webhook Routes
Route::prefix('webhooks/calendar')
    ->middleware(['verify_webhook_signature', 'throttle:webhook'])
    ->name('webhooks.calendar.')
    ->group(function () {

        // Google Calendar Webhooks
        Route::post('/google', [CalendarController::class, 'handleGoogleWebhook'])
            ->name('google');

        // Outlook/Microsoft Calendar Webhooks
        Route::post('/outlook', [CalendarController::class, 'handleOutlookWebhook'])
            ->name('outlook');

        // Handle Outlook subscription validation (GET request)
        Route::get('/outlook', [CalendarController::class, 'validateOutlookSubscription'])
            ->name('outlook.validate');

        // iCal Webhook (for calendar feeds that support webhooks)
        Route::post('/ical/{integration}', [CalendarController::class, 'handleICalWebhook'])
            ->name('ical')
            ->where('integration', '[0-9]+');

        // Apple Calendar Webhooks (if needed in future)
        Route::post('/apple', [CalendarController::class, 'handleAppleWebhook'])
            ->name('apple');

        // Generic webhook handler for testing/development
        Route::post('/test', [CalendarController::class, 'handleTestWebhook'])
            ->name('test')
            ->middleware('throttle:10,1'); // More restrictive for testing

        // Webhook status/health check endpoint
        Route::get('/status', [CalendarController::class, 'getWebhookStatus'])
            ->middleware('auth:api')
            ->name('status');
    });

// Payment Webhooks (existing, keeping for reference)
Route::prefix('webhooks/payments')
    ->middleware(['throttle:webhook'])
    ->name('webhooks.payments.')
    ->group(function () {
        Route::post('/stripe', [PaymentController::class, 'stripeWebhook'])
            ->name('stripe');
    });

// VENUE INFORMATION ROUTES (Public Access)
Route::prefix('venues/locations/{location}')
    ->middleware(['client'])
    ->controller(VenueController::class)
    ->group(function () {

        // Basic venue information (existing route enhanced)
        Route::get('/details', 'getDetails')
            ->middleware('rate_limit:venue.public_view')
            ->name('venues.details');

        // Venue amenities (existing route enhanced)
        Route::get('/amenities', 'getAmenities')
            ->middleware('rate_limit:venue.public_view')
            ->name('venues.amenities');

        // Venue availability calendar
        Route::get('/availability', 'getAvailability')
            ->middleware('rate_limit:venue.public_view')
            ->name('venues.availability');

        // Available time slots for booking
        Route::get('/slots', 'getAvailableSlots')
            ->middleware('rate_limit:venue.availability_check')
            ->name('venues.slots');

        // Venue booking requirements
        Route::post('/requirements-check', 'checkRequirements')
            ->middleware('rate_limit:venue.requirements_check')
            ->name('venues.requirements-check');

        // Venue suitability assessment
        Route::post('/suitability-check', 'checkSuitability')
            ->middleware('rate_limit:venue.suitability_check')
            ->name('venues.suitability-check');
    });

// VENUE SEARCH AND DISCOVERY (Public)
Route::prefix('venues')
    ->middleware(['client'])
    ->controller(VenueController::class)
    ->group(function () {

        // Search venues by criteria
        Route::get('/search', 'search')
            ->middleware('rate_limit:venue.search')
            ->name('venues.search');

        // Get venue recommendations
        Route::post('/recommendations', 'getRecommendations')
            ->middleware('rate_limit:venue.recommendations')
            ->name('venues.recommendations');

        // Compare multiple venues
        Route::post('/compare', 'compareVenues')
            ->middleware('rate_limit:venue.compare')
            ->name('venues.compare');

        // Get popular venues
        Route::get('/popular', 'getPopularVenues')
            ->middleware('rate_limit:venue.public_view')
            ->name('venues.popular');

        // Get venues by type/category
        Route::get('/by-type/{type}', 'getVenuesByType')
            ->middleware('rate_limit:venue.public_view')
            ->name('venues.by-type');
    });

// VENUE BOOKING INTEGRATION (Public/Authenticated)
Route::prefix('venues/locations/{location}/booking')
    ->middleware(['client'])
    ->controller(VenueController::class)
    ->group(function () {

        // Get booking summary with venue details
        Route::post('/summary', 'getBookingSummary')
            ->middleware('rate_limit:booking.venue_summary')
            ->name('venues.booking.summary');

        // Validate booking against venue constraints
        Route::post('/validate', 'validateBooking')
            ->middleware('rate_limit:booking.venue_validation')
            ->name('venues.booking.validate');

        // Get setup instructions for booking
        Route::post('/setup-guide', 'getSetupGuide')
            ->middleware('rate_limit:venue.setup_guide')
            ->name('venues.booking.setup-guide');
    });

// VENUE AMENITY MATCHING (Public)
Route::prefix('venues/amenities')
    ->middleware(['client'])
    ->controller(VenueController::class)
    ->group(function () {

        // Match requirements to available amenities
        Route::post('/match-requirements', 'matchAmenityRequirements')
            ->middleware('rate_limit:venue.amenity_matching')
            ->name('venues.amenities.match-requirements');

        // Get amenity categories and filters
        Route::get('/categories', 'getAmenityCategories')
            ->middleware('rate_limit:venue.public_view')
            ->name('venues.amenities.categories');

        // Search amenities across all venues
        Route::get('/search', 'searchAmenities')
            ->middleware('rate_limit:venue.amenity_search')
            ->name('venues.amenities.search');

        // Get amenity compatibility information
        Route::post('/compatibility', 'checkAmenityCompatibility')
            ->middleware('rate_limit:venue.amenity_compatibility')
            ->name('venues.amenities.compatibility');
    });

// VENUE INFORMATION WIDGETS (Public - for embedding)
Route::prefix('venues/widgets')
    ->middleware(['client'])
    ->controller(VenueController::class)
    ->group(function () {

        // Availability widget data
        Route::get('/locations/{location}/availability-widget', 'getAvailabilityWidget')
            ->middleware('rate_limit:venue.widget')
            ->name('venues.widgets.availability');

        // Quick booking widget data
        Route::get('/locations/{location}/booking-widget', 'getBookingWidget')
            ->middleware('rate_limit:venue.widget')
            ->name('venues.widgets.booking');

        // Amenity showcase widget
        Route::get('/locations/{location}/amenities-widget', 'getAmenitiesWidget')
            ->middleware('rate_limit:venue.widget')
            ->name('venues.widgets.amenities');

        // Venue info card widget
        Route::get('/locations/{location}/info-card', 'getInfoCard')
            ->middleware('rate_limit:venue.widget')
            ->name('venues.widgets.info-card');
    });

// BALLOON ARCH SPECIFIC ROUTES (Public)
Route::prefix('venues/balloon-arch')
    ->middleware(['client'])
    ->controller(VenueController::class)
    ->group(function () {

        // Find balloon arch suitable venues
        Route::get('/suitable-venues', 'getBalloonArchSuitableVenues')
            ->middleware('rate_limit:venue.balloon_arch')
            ->name('venues.balloon-arch.suitable');

        // Balloon arch setup requirements
        Route::post('/setup-requirements', 'getBalloonArchRequirements')
            ->middleware('rate_limit:venue.balloon_arch')
            ->name('venues.balloon-arch.requirements');

        // Balloon arch compatibility check
        Route::post('/compatibility-check', 'checkBalloonArchCompatibility')
            ->middleware('rate_limit:venue.balloon_arch')
            ->name('venues.balloon-arch.compatibility');

        // Get balloon arch equipment recommendations
        Route::get('/equipment-recommendations', 'getBalloonArchEquipmentRecommendations')
            ->middleware('rate_limit:venue.balloon_arch')
            ->name('venues.balloon-arch.equipment');

        // Balloon arch venue calculator
        Route::post('/venue-calculator', 'calculateBalloonArchVenue')
            ->middleware('rate_limit:venue.balloon_arch')
            ->name('venues.balloon-arch.calculator');
    });

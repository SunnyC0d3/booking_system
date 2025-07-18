<?php

use Illuminate\Support\Facades\Route;

// Auth Controllers
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\EmailVerificationController;

// Other Controllers
use App\Http\Controllers\V1\Public\UserController;
use App\Http\Controllers\V1\Public\VendorController;
use App\Http\Controllers\V1\Public\ProductController;
use App\Http\Controllers\V1\Public\OrderController;
use App\Http\Controllers\V1\Public\PaymentController;
use App\Http\Controllers\V1\Public\ReturnsController;
use App\Http\Controllers\V1\Public\CartController;
use App\Http\Controllers\V1\Public\ReviewController;
use App\Http\Controllers\V1\Public\ReviewResponseController;
use App\Http\Controllers\V1\Public\ShippingAddressController;
use App\Http\Controllers\V1\Public\ShippingCalculationController;
use App\Http\Controllers\V1\Public\ShippingController;
use App\Http\Controllers\V1\Public\CheckoutController;

// Auth

// Route::middleware(['throttle:3,1', 'client'])
Route::controller(AuthController::class)
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

// Vendors

Route::prefix('vendors')
    ->middleware(['auth:api', 'roles:vendor', 'emailVerified'])
    ->controller(VendorController::class)
    ->group(function () {
        Route::get('/{vendor}', 'show')->name('vendors.show');
        Route::post('/{vendor}', 'update')->name('vendors.update');
    });

// Products

Route::prefix('products')
    ->middleware(['client', 'rate_limit:search'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{product}', 'show')->name('products.show');
    });

// Orders

Route::prefix('orders')
    ->middleware(['auth:api', 'roles:user, vendor', 'emailVerified'])
    ->controller(OrderController::class)
    ->group(function () {
        Route::get('/', 'index')->name('orders.index');
        Route::get('/{order}', 'show')->name('orders.show');
        Route::post('/from-cart', 'create')->name('orders.create-from-cart');
    });

// Returns

Route::prefix('returns')
    ->middleware(['auth:api', 'roles:user, vendor', 'emailVerified'])
    ->controller(ReturnsController::class)
    ->group(function () {
        Route::post('/', 'return')->name('returns');
    });

// Cart

Route::prefix('cart')
    ->middleware(['auth:api', 'rate_limit:cart'])
    ->controller(CartController::class)
    ->group(function () {
        Route::get('/', 'index')->name('cart.index');
        Route::post('/items', 'store')->name('cart.add');
        Route::post('/items/{cartItem}', 'update')->name('cart.update');
        Route::delete('/items/{cartItem}', 'destroy')->name('cart.remove');
        Route::delete('/clear', 'clear')->name('cart.clear');
        Route::post('/sync-prices', 'syncPrices')->name('cart.sync-prices');
    });

// Public Review Viewing (Guest + Authenticated users can view reviews)

Route::prefix('products/{product}/reviews')
    ->middleware(['review.smart_throttle:view,false'])
    ->controller(ReviewController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.reviews.index');
    });

Route::prefix('reviews')
    ->middleware(['review.smart_throttle:view,false'])
    ->controller(ReviewController::class)
    ->group(function () {
        Route::get('/{review}', 'show')->name('reviews.show');
    });

// Review Actions (Require Authentication) - SmartReviewThrottle handles auth

Route::prefix('reviews')
    ->controller(ReviewController::class)
    ->group(function () {
        Route::post('/', 'store')
            ->middleware(['review.smart_throttle:create,true'])
            ->name('reviews.store');
    });

Route::prefix('reviews/{review}')
    ->controller(ReviewController::class)
    ->group(function () {
        Route::post('/', 'update')
            ->middleware(['review.smart_throttle:update,true'])
            ->name('reviews.update');

        Route::delete('/', 'destroy')
            ->middleware(['review.smart_throttle:delete,true'])
            ->name('reviews.destroy');

        Route::post('/helpfulness', 'voteHelpfulness')
            ->middleware(['review.smart_throttle:vote,true'])
            ->name('reviews.helpfulness');

        Route::post('/report', 'report')
            ->middleware(['review.smart_throttle:report,true'])
            ->name('reviews.report');
    });

// Review Responses - Public Viewing (No auth required)

Route::prefix('reviews/{review}/responses')
    ->middleware(['review.smart_throttle:responses,false'])
    ->controller(ReviewResponseController::class)
    ->group(function () {
        Route::get('/', 'publicIndex')->name('review.responses.public.index');
        Route::get('/{response}', 'publicShow')->name('review.responses.public.show');
    });

// Review Responses - Vendor Actions (Auth required + Role check)

Route::prefix('reviews/{review}/responses')
    ->middleware(['review.smart_throttle:respond,true', 'roles:vendor'])
    ->controller(ReviewResponseController::class)
    ->group(function () {
        Route::post('/', 'store')->name('review.responses.store');
        Route::post('/{response}', 'update')->name('review.responses.update');
        Route::delete('/{response}', 'destroy')->name('review.responses.destroy');
    });

// Vendor Dashboard (Auth required + Role check)

Route::prefix('vendor/responses')
    ->middleware(['review.smart_throttle:dashboard,true', 'roles:vendor'])
    ->controller(ReviewResponseController::class)
    ->group(function () {
        Route::get('/', 'index')->name('vendor.responses.index');
        Route::get('/{response}', 'show')->name('vendor.responses.show');
    });

// Vendor Unanswered Reviews

Route::get('vendor/unanswered-reviews', [ReviewResponseController::class, 'getUnansweredReviews'])
    ->middleware(['review.smart_throttle:dashboard,true', 'roles:vendor'])
    ->name('vendor.responses.unanswered');

// Shipping Addresses (User-specific)

Route::prefix('shipping-addresses')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:shipping'])
    ->controller(ShippingAddressController::class)
    ->group(function () {
        Route::get('/', 'index')->name('shipping-addresses.index');
        Route::post('/', 'store')->name('shipping-addresses.store');
        Route::get('/{shippingAddress}', 'show')->name('shipping-addresses.show');
        Route::put('/{shippingAddress}', 'update')->name('shipping-addresses.update');
        Route::delete('/{shippingAddress}', 'destroy')->name('shipping-addresses.destroy');
        Route::patch('/{shippingAddress}/set-default', 'setDefault')->name('shipping-addresses.set-default');
        Route::post('/{shippingAddress}/validate', 'validate')->name('shipping-addresses.validate');
    });

// Shipping Calculations and Quotes

Route::prefix('shipping')
    ->middleware(['rate_limit:shipping'])
    ->controller(ShippingCalculationController::class)
    ->group(function () {
        // Cart shipping (requires auth)
        Route::post('/cart/quote', 'getCartShippingQuote')
            ->middleware(['auth:api'])
            ->name('shipping.cart.quote');

        // Product shipping (requires auth)
        Route::post('/products/quote', 'getProductShippingQuote')
            ->middleware(['auth:api'])
            ->name('shipping.products.quote');

        // Quick estimate (public - no auth required)
        Route::post('/estimate', 'getQuickEstimate')->name('shipping.estimate');

        // Cheapest/Fastest options (requires auth)
        Route::post('/cheapest', 'getCheapestOption')
            ->middleware(['auth:api'])
            ->name('shipping.cheapest');
        Route::post('/fastest', 'getFastestOption')
            ->middleware(['auth:api'])
            ->name('shipping.fastest');

        // Method validation (requires auth)
        Route::post('/validate-method', 'validateShippingMethod')
            ->middleware(['auth:api'])
            ->name('shipping.validate-method');
    });

// Shipment Tracking (public - no auth required for tracking)

Route::prefix('tracking')
    ->middleware(['rate_limit:tracking'])
    ->controller(ShippingController::class)
    ->group(function () {
        Route::get('/{trackingNumber}', 'trackShipment')->name('tracking.shipment');
        Route::get('/{trackingNumber}/status', 'getShipmentStatus')->name('tracking.status');
    });

// User's Shipments (requires auth)

Route::prefix('my-shipments')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:shipping'])
    ->controller(ShippingController::class)
    ->group(function () {
        Route::get('/', 'getUserShipments')->name('my-shipments.index');
        Route::get('/{shipment}', 'getUserShipment')->name('my-shipments.show');
    });

// Checkout

Route::prefix('checkout')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:checkout'])
    ->controller(CheckoutController::class)
    ->group(function () {
        Route::post('/summary', 'getCheckoutSummary')->name('checkout.summary');
        Route::post('/validate', 'validateCheckout')->name('checkout.validate');
    });

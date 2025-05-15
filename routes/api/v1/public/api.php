<?php

use Illuminate\Support\Facades\Route;

// Auth Controllers
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\EmailVerificationController;

use App\Http\Controllers\V1\Public\UserController;
use App\Http\Controllers\V1\Public\VendorController;
use App\Http\Controllers\V1\Public\ProductController;
use App\Http\Controllers\V1\Public\OrderController;
use App\Http\Controllers\V1\Public\PaymentController;
use App\Http\Controllers\V1\Public\ReturnsController;

// Auth

//Route::middleware(['throttle:3,1', 'hmac'])
Route::middleware(['throttle:3,1'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->name('auth.register');
        Route::post('/login', 'login')->name('auth.login');
    });

Route::middleware(['auth:api', 'hmac'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
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

Route::middleware(['throttle:3,1', 'hmac'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/forgot-password', 'forgotPassword')->name('password.email');
        Route::post('/reset-password', 'passwordReset')->name('password.update');
    });

// Payments

Route::prefix('payments')
    ->middleware(['hmac'])
    ->controller(PaymentController::class)
    ->group(function () {
        Route::post('/{gateway}/create', 'store')->name('payments.store');
        Route::post('/stripe/webhook', 'stripeWebhook')->name('payments.stripe.webhook');
    });

// Users

Route::prefix('users')
    ->middleware(['auth:api', 'roles:user', 'emailVerified', 'hmac'])
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/{user}', 'show')->name('users.show');
        Route::post('/{user}', 'update')->name('users.update');
    });

// Vendors

Route::prefix('vendors')
    ->middleware(['auth:api', 'roles:vendor', 'emailVerified', 'hmac'])
    ->controller(VendorController::class)
    ->group(function () {
        Route::get('/{vendor}', 'show')->name('vendors.show');
        Route::post('/{vendor}', 'update')->name('vendors.update');
    });

// Products

Route::prefix('products')
    ->middleware(['hmac'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{product}', 'show')->name('products.show');
    });

// Orders

Route::prefix('orders')
    ->middleware(['auth:api', 'roles:user, vendor', 'emailVerified', 'hmac'])
    ->controller(OrderController::class)
    ->group(function () {
        Route::get('/{order}', 'show')->name('orders.show');
    });

// Returns

Route::prefix('returns')
    ->middleware(['hmac'])
    ->controller(ReturnsController::class)
    ->group(function () {
        Route::post('/', 'return')->name('returns');
    });

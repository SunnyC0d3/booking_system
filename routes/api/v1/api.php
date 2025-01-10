<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\EmailVerificationController;
use Illuminate\Support\Facades\Redirect;

// Registering, Logging In Routes

Route::middleware(['throttle:3,1', 'hmac'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->name('auth.register');
        Route::post('/login', 'login')->name('auth.login');
    });

Route::middleware(['throttle:3,1', 'auth:api', 'emailVerified'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
    });

// Products

Route::prefix('products')
    ->middleware(['throttle:10,1', 'auth:api', 'scope:read-products', 'roles:user,admin', 'emailVerified'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{id}', 'show')->name('products.show');
    });

Route::prefix('products')
    ->middleware(['throttle:10,1', 'auth:api', 'scope:write-products', 'roles:admin', 'emailVerified'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::post('/', 'store')->name('products.store');
        Route::put('/{id}', 'update')->name('products.update');
        Route::delete('/{id}', 'destroy')->name('products.destroy');
    });

// Email Verification

Route::prefix('email')
    ->middleware(['throttle:10,1', 'signed'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/verify/{id}/{hash}', 'verify')->name('verification.verify');
    });

Route::prefix('email')
    ->middleware(['throttle:10,1', 'auth:api'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/resend', 'resend')->name('verification.resend');
    });

// Password Reset

Route::middleware(['throttle:10,1', 'hmac'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/forgot-password', 'forgotPassword')->name('password.email');
        Route::post('/reset-password', 'passwordReset')->name('password.update');
    });

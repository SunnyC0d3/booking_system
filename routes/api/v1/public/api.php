<?php

use Illuminate\Support\Facades\Route;

// Auth Controllers
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\EmailVerificationController;

// Auth

Route::middleware(['throttle:3,1', 'hmac'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->name('auth.register');
        Route::post('/login', 'login')->name('auth.login');
    });

Route::middleware(['throttle:3,1', 'auth:api'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
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

// Payment Page Demo

Route::middleware(['throttle:10,1'])
    ->group(function () {
        Route::get('/orders/{orderId}/pay', function($orderId) {
            return view('app', [
                'orderId' => $orderId
            ]);
        })->name('order.pay');
    });

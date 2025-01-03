<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

use App\Http\Controllers\V1\AuthController;

// Registering, Logging In Routes

Route::middleware(['throttle:3,1', 'guest'])
    ->group(function () {
        Route::get('/register', function () {
            return view('auth.register');
        })->name('register');

        Route::get('/login', function () {
            return view('auth.login');
        })->name('login');
    });

Route::middleware(['throttle:3,1', 'guest'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->name('auth.register');
        Route::post('/login', 'login')->name('auth.login');
    });

Route::middleware(['throttle:3,1', 'auth', 'verified'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
    });


// Email Verification Routes

Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect('/home');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', function (Request $request) {
    $request->user()->sendEmailVerificationNotification();
    return back()->with('message', 'Verification link sent!');
})->middleware(['auth', 'throttle:6,1'])->name('verification.send');

<?php

use Illuminate\Support\Facades\Route;

// Registering, Logging In Routes

Route::middleware(['throttle:60,1', 'web'])
    ->group(function () {
        Route::get('/register', function () {
            return view('auth.register');
        })->name('register');
        
        Route::get('/login', function () {
            return view('auth.login');
        })->name('login');
    });

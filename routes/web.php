<?php

use Illuminate\Support\Facades\Route;

// Registering, Logging In Routes

Route::middleware(['throttle:60,1', 'client:register,login'])
    ->group(function () {
        Route::get('/register', function () {
            return view('auth.register');
        })->name('register');
        
        Route::get('/login', function () {
            return view('auth.login');
        })->name('login');
    });

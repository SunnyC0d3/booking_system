<?php

use Illuminate\Support\Facades\Route;

// Login

Route::middleware(['throttle:60,1', 'client:login'])
    ->group(function () {
        Route::get('/login', function () {
            return view('auth.login');
        })->name('login');
    });

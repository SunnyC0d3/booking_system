<?php

use Illuminate\Support\Facades\Route;

// Demo App

Route::get('/demo/app', function () {
    return view('demo.app');
})->name('demo.app');

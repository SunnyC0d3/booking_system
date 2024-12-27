<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;

// Get Client Token

Route::middleware(['throttle:60,1'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/client-token', 'clientToken');
    });

// Registering, Logging In/Logging Out Routes

Route::middleware(['throttle:60,1', 'client:register'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register');
    });

Route::middleware(['throttle:60,1', 'auth:api', 'scope:logout'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout');
    });

// Products

Route::prefix('products')
    ->middleware(['throttle:60,1', 'auth:api', 'scope:read-products'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
    });

Route::prefix('products')
    ->middleware(['throttle:60,1', 'auth:api', 'scope:write-products'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

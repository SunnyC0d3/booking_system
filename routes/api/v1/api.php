<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;

// Registering, Logging In/Logging Out Routes

Route::middleware(['throttle:60,1', 'auth:sanctum', 'ability:client:only'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/login', 'login');
        Route::post('/register', 'register');
        Route::post('/logout', 'logout');
        Route::post('/token/refresh', 'refreshToken');
    });

// Products

Route::prefix('products')
    ->middleware(['throttle:60,1', 'auth:sanctum', 'ability:client:only'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

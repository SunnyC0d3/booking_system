<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;

// Registering, Logging In/Logging Out Routes

Route::middleware(['throttle:60,1', 'auth:sanctum', 'ability:client:only'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/token/refresh', [AuthController::class, 'refreshToken']);
});

// Products

Route::prefix('products')
    ->middleware(['throttle:60,1', 'auth:sanctum', 'ability:client:only'])
    ->controller(ProductController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/{id}', 'show');
        Route::post('/', 'store');
        Route::put('/{id}', 'update');
        Route::delete('/{id}', 'destroy');
    });

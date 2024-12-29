<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ProductController;

// Registering, Logging In/Logging Out Routes

Route::middleware(['throttle:60,1', 'client:register,login'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->name('auth.register');
        Route::post('/login', 'login')->name('auth.login');
    });

Route::middleware(['throttle:60,1', 'auth:api', 'scope:logout'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
    });

// Products

Route::prefix('products')
    ->middleware(['throttle:60,1', 'auth:api', 'scope:read-products'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{id}', 'show')->name('products.show');
    });

Route::prefix('products')
    ->middleware(['throttle:60,1', 'auth:api', 'scope:write-products'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::post('/', 'store')->name('products.store');
        Route::put('/{id}', 'update')->name('products.update');
        Route::delete('/{id}', 'destroy')->name('products.destroy');
    });

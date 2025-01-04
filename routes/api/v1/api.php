<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\ProductController;

// Products

Route::prefix('products')
    ->middleware(['throttle:10,1', 'auth:api', 'scope:read-products'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{id}', 'show')->name('products.show');
    });

Route::prefix('products')
    ->middleware(['throttle:10,1', 'auth:api', 'scope:write-products'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::post('/', 'store')->name('products.store');
        Route::put('/{id}', 'update')->name('products.update');
        Route::delete('/{id}', 'destroy')->name('products.destroy');
    });

<?php

use Illuminate\Support\Facades\Route;

// Auth Controllers
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\EmailVerificationController;

// Admin Controllers

use App\Http\Controllers\V1\Admin\ProductController;
use App\Http\Controllers\V1\Admin\ProductAttributeController;
use App\Http\Controllers\V1\Admin\ProductCategoryController;
use App\Http\Controllers\V1\Admin\ProductStatusController;

// Registering, Logging In Routes

// Route::middleware(['throttle:3,1', 'hmac'])
Route::middleware(['throttle:3,1'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->name('auth.register');
        Route::post('/login', 'login')->name('auth.login');
    });

Route::middleware(['throttle:3,1', 'auth:api'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
    });

// Admin/Products

Route::prefix('admin/products')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin', 'emailVerified'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{product}', 'show')->name('products.show');
        Route::post('/', 'store')->name('products.store');
        Route::post('/{product}', 'update')->name('products.update');
        Route::delete('/soft-destroy/{product}', 'softDestroy')->name('products.softDestroy');
        Route::delete('/{product}', 'destroy')->name('products.destroy');
    });

// Admin/Product Attributes

Route::prefix('admin/product-attributes')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin', 'emailVerified'])
    ->controller(ProductAttributeController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.attributes.index');
        Route::post('/', 'store')->name('products.attributes.store');
        Route::get('/{productAttribute}', 'show')->name('products.attributes.show');
        Route::post('/{productAttribute}', 'update')->name('products.attributes.update');
        Route::delete('/{productAttribute}', 'destroy')->name('products.attributes.destroy');
    });

// Admin/Product Categories

Route::prefix('admin/product-categories')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin', 'emailVerified'])
    ->controller(ProductCategoryController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.categories.index');
        Route::post('/', 'store')->name('products.categories.store');
        Route::get('/{productCategory}', 'show')->name('products.categories.show');
        Route::post('/{productCategory}', 'update')->name('products.categories.update');
        Route::delete('/{productCategory}', 'destroy')->name('products.categories.destroy');
    });

// Admin/Product Statuses

Route::prefix('admin/product-statuses')
->middleware(['throttle:10,1', 'auth:api', 'roles:super admin', 'emailVerified'])
->controller(ProductStatusController::class)
->group(function () {
    Route::get('/', 'index')->name('products.statuses.index');
    Route::post('/', 'store')->name('products.statuses.store');
    Route::get('/{productStatus}', 'show')->name('products.statuses.show');
    Route::post('/{productStatus}', 'update')->name('products.statuses.update');
    Route::delete('/{productStatus}', 'destroy')->name('products.statuses.destroy');
});

// Email Verification

Route::prefix('email')
    ->middleware(['throttle:10,1', 'signed'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/verify/{id}/{hash}', 'verify')->name('verification.verify');
    });

Route::prefix('email')
    ->middleware(['throttle:10,1', 'auth:api'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/resend', 'resend')->name('verification.resend');
    });

// Password Reset

Route::middleware(['throttle:10,1', 'hmac'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/forgot-password', 'forgotPassword')->name('password.email');
        Route::post('/reset-password', 'passwordReset')->name('password.update');
    });

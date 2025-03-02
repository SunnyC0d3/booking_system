<?php

use Illuminate\Support\Facades\Route;

// Admin Controllers

use App\Http\Controllers\V1\Admin\ProductController;
use App\Http\Controllers\V1\Admin\ProductAttributeController;
use App\Http\Controllers\V1\Admin\ProductCategoryController;
use App\Http\Controllers\V1\Admin\ProductStatusController;
use App\Http\Controllers\V1\Admin\ProductTagController;

// Admin/Products

Route::prefix('admin/products')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.products.index');
        Route::get('/{product}', 'show')->name('admin.products.show');
        Route::post('/', 'store')->name('admin.products.store');
        Route::post('/{product}', 'update')->name('admin.products.update');
        Route::delete('/soft-destroy/{product}', 'softDestroy')->name('admin.products.softDestroy');
        Route::delete('/{product}', 'destroy')->name('admin.products.destroy');
    });

// Admin/Product Attributes

Route::prefix('admin/product-attributes')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(ProductAttributeController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.products.attributes.index');
        Route::post('/', 'store')->name('admin.products.attributes.store');
        Route::get('/{productAttribute}', 'show')->name('admin.products.attributes.show');
        Route::post('/{productAttribute}', 'update')->name('admin.products.attributes.update');
        Route::delete('/{productAttribute}', 'destroy')->name('admin.products.attributes.destroy');
    });

// Admin/Product Categories

Route::prefix('admin/product-categories')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(ProductCategoryController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.products.categories.index');
        Route::post('/', 'store')->name('admin.products.categories.store');
        Route::get('/{productCategory}', 'show')->name('admin.products.categories.show');
        Route::post('/{productCategory}', 'update')->name('admin.products.categories.update');
        Route::delete('/{productCategory}', 'destroy')->name('admin.products.categories.destroy');
    });

// Admin/Product Statuses

Route::prefix('admin/product-statuses')
->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
->controller(ProductStatusController::class)
->group(function () {
    Route::get('/', 'index')->name('admin.products.statuses.index');
    Route::post('/', 'store')->name('admin.products.statuses.store');
    Route::get('/{productStatus}', 'show')->name('admin.products.statuses.show');
    Route::post('/{productStatus}', 'update')->name('admin.products.statuses.update');
    Route::delete('/{productStatus}', 'destroy')->name('admin.products.statuses.destroy');
});

// Admin/Product Tags

Route::prefix('admin/product-tags')
->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
->controller(ProductTagController::class)
->group(function () {
    Route::get('/', 'index')->name('admin.products.tags.index');
    Route::post('/', 'store')->name('admin.products.tags.store');
    Route::get('/{productTag}', 'show')->name('admin.products.tags.show');
    Route::post('/{productTag}', 'update')->name('admin.products.tags.update');
    Route::delete('/{productTag}', 'destroy')->name('admin.products.tags.destroy');
});

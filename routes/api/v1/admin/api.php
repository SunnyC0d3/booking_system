<?php

use Illuminate\Support\Facades\Route;

// Admin Controllers

use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\Admin\VendorController;
use App\Http\Controllers\V1\Admin\PermissionController;
use App\Http\Controllers\V1\Admin\RoleController;
use App\Http\Controllers\V1\Admin\ProductController;
use App\Http\Controllers\V1\Admin\ProductAttributeController;
use App\Http\Controllers\V1\Admin\ProductCategoryController;
use App\Http\Controllers\V1\Admin\ProductStatusController;
use App\Http\Controllers\V1\Admin\ProductTagController;

// Admin/Users

Route::prefix('admin/users')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.users.index');
        Route::get('/{user}', 'show')->name('admin.users.show');
        Route::post('/', 'store')->name('admin.users.store');
        Route::post('/{user}', 'update')->name('admin.users.update');
        Route::delete('/{user}', 'destroy')->name('admin.users.destroy');
    });

// Admin/Vendors

Route::prefix('admin/vendors')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(VendorController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.vendors.index');
        Route::get('/{vendor}', 'show')->name('admin.vendors.show');
        Route::post('/', 'store')->name('admin.vendors.store');
        Route::post('/{vendor}', 'update')->name('admin.vendors.update');
        Route::delete('/{vendor}', 'destroy')->name('admin.vendors.destroy');
    });

// Admin/Permissions

Route::prefix('admin/permissions')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(PermissionController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.permissions.index');
        Route::post('/', 'store')->name('admin.permissions.store');
        Route::post('/{permission}', 'update')->name('admin.permissions.update');
        Route::delete('/{permission}', 'destroy')->name('admin.permissions.destroy');
    });

// Admin/Roles

Route::prefix('admin/roles')
    ->middleware(['throttle:10,1', 'auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(RoleController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.roles.index');
        Route::post('/', 'store')->name('admin.roles.store');
        Route::post('/{role}', 'update')->name('admin.roles.update');
        Route::delete('/{role}', 'destroy')->name('admin.roles.destroy');
    });

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

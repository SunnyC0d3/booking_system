<?php

use Illuminate\Support\Facades\Route;

// Admin Controllers

use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\Admin\PermissionController;
use App\Http\Controllers\V1\Admin\RoleController;
use App\Http\Controllers\V1\Admin\RolePermissionController;
use App\Http\Controllers\V1\Admin\PaymentMethodController;
use App\Http\Controllers\V1\Admin\ReturnsController;
use App\Http\Controllers\V1\Admin\RefundController;
use App\Http\Controllers\V1\Admin\PaymentController;
use App\Http\Controllers\V1\Admin\ReviewController;
use App\Http\Controllers\V1\Admin\ReviewResponseController;

// Admin/Users

Route::prefix('admin/users')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.users.index');
        Route::get('/{user}', 'show')->name('admin.users.show');
        Route::post('/', 'store')->name('admin.users.store');
        Route::post('/{user}', 'update')->name('admin.users.update');
        Route::delete('/{user}', 'destroy')->name('admin.users.destroy');
    });

// Admin/Permissions

Route::prefix('admin/permissions')
    ->middleware(['auth:api', 'roles:super admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(PermissionController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.permissions.index');
        Route::post('/', 'store')->name('admin.permissions.store');
        Route::post('/{permission}', 'update')->name('admin.permissions.update');
        Route::delete('/{permission}', 'destroy')->name('admin.permissions.destroy');
    });

// Admin/Roles

Route::prefix('admin/roles')
    ->middleware(['auth:api', 'roles:super admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(RoleController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.roles.index');
        Route::post('/', 'store')->name('admin.roles.store');
        Route::post('/{role}', 'update')->name('admin.roles.update');
        Route::delete('/{role}', 'destroy')->name('admin.roles.destroy');
    });

// Admin/RolePermission

Route::prefix('admin')
    ->middleware(['auth:api', 'roles:super admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(RolePermissionController::class)
    ->group(function () {
        Route::get('roles/{role}/permissions', 'index')->name('admin.rolepermission.index');
        Route::post('roles/{role}/permissions', 'assign')->name('admin.rolepermission.assign');
        Route::post('roles/{role}/permissions/assign-all', 'assignAllPermissions')->name('admin.rolepermission.assignAll');
        Route::delete('roles/{role}/permissions/{permission}', 'revoke')->name('admin.rolepermission.revoke');
    });

// Admin/Payment Methods

Route::prefix('admin/payment-methods')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(PaymentMethodController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.paymentmethods.index');
        Route::post('/', 'store')->name('admin.paymentmethods.store');
        Route::post('/{paymentMethod}', 'update')->name('admin.paymentmethods.update');
        Route::delete('/{paymentMethod}', 'destroy')->name('admin.paymentmethods.destroy');
    });

// Admin/Returns

Route::prefix('admin/returns')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ReturnsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.returns.index');
        Route::post('/{returnId}/{action}', 'reviewReturn')->name('admin.returns.action');
    });

// Admin/Refund

Route::prefix('admin/refunds')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(RefundController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.refunds.index');
        Route::post('/{gateway}/{id}', 'refund')->name('admin.refund');
    });

// Admin/Refund

Route::prefix('admin/payments')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(PaymentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.payments.index');
    });

// Admin/Reviews - Complete Review Management System

Route::prefix('admin/reviews')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ReviewController::class)
    ->group(function () {
        Route::get('/reports', 'getReportedReviews')->name('admin.reviews.reports');
        Route::get('/analytics', 'getAnalytics')->name('admin.reviews.analytics');
        Route::post('/bulk-moderate', 'bulkModerate')->name('admin.reviews.bulk-moderate');

        Route::get('/', 'index')->name('admin.reviews.index');

        Route::get('/{review}', 'show')->name('admin.reviews.show');
        Route::delete('/{review}', 'destroy')->name('admin.reviews.destroy');
        Route::post('/{review}/moderate', 'moderate')->name('admin.reviews.moderate');

        Route::post('/reports/{report}/handle', 'handleReport')->name('admin.reviews.reports.handle');
    });

// Admin/Review Responses - Manage vendor responses

Route::prefix('admin/review-responses')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ReviewResponseController::class)
    ->group(function () {
        Route::get('/analytics', 'getAnalytics')->name('admin.review-responses.analytics');
        Route::post('/bulk-moderate', 'bulkModerate')->name('admin.review-responses.bulk-moderate');

        Route::get('/', 'adminIndex')->name('admin.review-responses.index');

        Route::get('/{response}', 'adminShow')->name('admin.review-responses.show');
        Route::post('/{response}/approve', 'approve')->name('admin.review-responses.approve');
        Route::delete('/{response}', 'adminDestroy')->name('admin.review-responses.destroy');
    });

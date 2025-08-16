<?php

use App\Http\Controllers\V1\Admin\ServiceAddOnController;
use App\Http\Controllers\V1\Admin\ServiceController;
use App\Http\Controllers\V1\Admin\ServiceLocationController;
use App\Http\Controllers\V1\Admin\ServicePackageController;
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
use App\Http\Controllers\V1\Admin\BookingController;

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

// Admin/Bookings

Route::prefix('admin/bookings')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(BookingController::class)
    ->group(function () {
        Route::get('/', 'index')
            ->middleware('rate_limit:admin.bookings.view')
            ->name('admin.bookings.index');
        Route::post('/', 'store')
            ->middleware('rate_limit:admin.bookings.create')
            ->name('admin.bookings.store');
        Route::get('/statistics', 'getStatistics')
            ->middleware('rate_limit:admin.statistics')
            ->name('admin.bookings.statistics');
        Route::get('/calendar', 'getCalendarData')
            ->middleware('rate_limit:admin.calendar')
            ->name('admin.bookings.calendar');
        Route::get('/schedule/daily', 'getDailySchedule')
            ->middleware('rate_limit:admin.calendar')
            ->name('admin.bookings.schedule.daily');
        Route::post('/bulk-update', 'bulkUpdate')
            ->middleware('rate_limit:admin.bulk_operations')
            ->name('admin.bookings.bulk-update');
        Route::get('/export', 'export')
            ->middleware('rate_limit:admin.export')
            ->name('admin.bookings.export');

        Route::get('/{booking}', 'show')
            ->middleware('rate_limit:admin.bookings.view')
            ->name('admin.bookings.show');
        Route::put('/{booking}', 'update')
            ->middleware('rate_limit:admin.bookings.update')
            ->name('admin.bookings.update');
        Route::delete('/{booking}', 'destroy')
            ->middleware('rate_limit:admin.bookings.delete')
            ->name('admin.bookings.destroy');

        // Status management
        Route::post('/{booking}/confirm', 'confirm')
            ->middleware('rate_limit:admin.bookings.update')
            ->name('admin.bookings.confirm');
        Route::post('/{booking}/in-progress', 'markInProgress')
            ->middleware('rate_limit:admin.bookings.update')
            ->name('admin.bookings.in-progress');
        Route::post('/{booking}/completed', 'markCompleted')
            ->middleware('rate_limit:admin.bookings.update')
            ->name('admin.bookings.completed');
        Route::post('/{booking}/no-show', 'markNoShow')
            ->middleware('rate_limit:admin.bookings.update')
            ->name('admin.bookings.no-show');
    });

// Service management routes (Admin)
Route::prefix('admin/services')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(ServiceController::class)
    ->group(function () {
        Route::get('/', 'index')
            ->middleware('rate_limit:admin.service_management')
            ->name('admin.services.index');
        Route::post('/', 'store')
            ->middleware('rate_limit:admin.service_management')
            ->name('admin.services.store');
        Route::get('/{service}', 'show')
            ->middleware('rate_limit:admin.service_management')
            ->name('admin.services.show');
        Route::put('/{service}', 'update')
            ->middleware('rate_limit:admin.service_management')
            ->name('admin.services.update');
        Route::delete('/{service}', 'destroy')
            ->middleware('rate_limit:admin.service_management')
            ->name('admin.services.destroy');

        // Service locations
        Route::prefix('{service}/locations')
            ->controller(ServiceLocationController::class)
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('rate_limit:admin.location_management')
                    ->name('admin.services.locations.index');
                Route::post('/', 'store')
                    ->middleware('rate_limit:admin.location_management')
                    ->name('admin.services.locations.store');
                Route::get('/{location}', 'show')
                    ->middleware('rate_limit:admin.location_management')
                    ->name('admin.services.locations.show');
                Route::put('/{location}', 'update')
                    ->middleware('rate_limit:admin.location_management')
                    ->name('admin.services.locations.update');
                Route::delete('/{location}', 'destroy')
                    ->middleware('rate_limit:admin.location_management')
                    ->name('admin.services.locations.destroy');
            });

        // Service availability windows
        Route::prefix('{service}/availability')
            ->controller(ServiceAvailabilityController::class)
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('rate_limit:admin.availability_management')
                    ->name('admin.services.availability.index');
                Route::post('/', 'store')
                    ->middleware('rate_limit:admin.availability_management')
                    ->name('admin.services.availability.store');
                Route::get('/{window}', 'show')
                    ->middleware('rate_limit:admin.availability_management')
                    ->name('admin.services.availability.show');
                Route::put('/{window}', 'update')
                    ->middleware('rate_limit:admin.availability_management')
                    ->name('admin.services.availability.update');
                Route::delete('/{window}', 'destroy')
                    ->middleware('rate_limit:admin.availability_management')
                    ->name('admin.services.availability.destroy');

                // Bulk operations for availability
                Route::post('/bulk-create', 'bulkCreate')
                    ->middleware('rate_limit:admin.bulk_operations')
                    ->name('admin.services.availability.bulk-create');
                Route::post('/bulk-update', 'bulkUpdate')
                    ->middleware('rate_limit:admin.bulk_operations')
                    ->name('admin.services.availability.bulk-update');
            });

        // Service add-ons
        Route::prefix('{service}/add-ons')
            ->controller(ServiceAddOnController::class)
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.addons.index');
                Route::post('/', 'store')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.addons.store');
                Route::get('/{addon}', 'show')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.addons.show');
                Route::put('/{addon}', 'update')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.addons.update');
                Route::delete('/{addon}', 'destroy')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.addons.destroy');
            });

        // Service packages
        Route::prefix('{service}/packages')
            ->controller(ServicePackageController::class)
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.packages.index');
                Route::post('/', 'store')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.packages.store');
                Route::get('/{package}', 'show')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.packages.show');
                Route::put('/{package}', 'update')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.packages.update');
                Route::delete('/{package}', 'destroy')
                    ->middleware('rate_limit:admin.service_management')
                    ->name('admin.services.packages.destroy');
            });
    });



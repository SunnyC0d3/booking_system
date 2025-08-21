<?php

use App\Http\Controllers\V1\Admin\ConsultationController;
use App\Http\Controllers\V1\Admin\ServiceAddOnController;
use App\Http\Controllers\V1\Admin\ServiceAvailabilityController;
use App\Http\Controllers\V1\Admin\ServiceController;
use App\Http\Controllers\V1\Admin\ServiceLocationController;
use App\Http\Controllers\V1\Admin\ServicePackageController;
use App\Http\Controllers\V1\Admin\VenueAmenityController;
use App\Http\Controllers\V1\Admin\VenueAvailabilityController;
use App\Http\Controllers\V1\Admin\VenueDetailsController;
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

// CLEANED Admin/Bookings - Essential routes only
Route::prefix('admin/bookings')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->controller(BookingController::class)
    ->group(function () {
        // Core CRUD operations
        Route::get('/', 'index')
            ->middleware('rate_limit:admin.bookings.view')
            ->name('admin.bookings.index');
        Route::post('/', 'store')
            ->middleware('rate_limit:admin.bookings.create')
            ->name('admin.bookings.store');
        Route::get('/{booking}', 'show')
            ->middleware('rate_limit:admin.bookings.view')
            ->name('admin.bookings.show');
        Route::put('/{booking}', 'update')
            ->middleware('rate_limit:admin.bookings.update')
            ->name('admin.bookings.update');
        Route::delete('/{booking}/cancel', 'cancel')
            ->middleware('rate_limit:admin.bookings.delete')
            ->name('admin.bookings.cancel');

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

        // Statistics and monitoring
        Route::get('/stats/overview', 'getStatistics')
            ->middleware('rate_limit:admin.statistics')
            ->name('admin.bookings.statistics');
        Route::get('/system/health', 'getSystemHealth')
            ->middleware('rate_limit:admin.system')
            ->name('admin.bookings.system.health');
        Route::post('/notifications/process-overdue', 'processOverdueNotifications')
            ->middleware('rate_limit:admin.system')
            ->name('admin.bookings.notifications.process-overdue');

        // Notification management
        Route::post('/{booking}/notifications/resend', 'resendNotification')
            ->middleware('rate_limit:admin.notifications')
            ->name('admin.bookings.notifications.resend');
        Route::get('/{booking}/notifications/stats', 'getNotificationStats')
            ->middleware('rate_limit:admin.notifications')
            ->name('admin.bookings.notifications.stats');
    });

// CLEANED Admin/Consultations - Essential routes only
Route::prefix('admin/consultations')
    ->middleware(['auth:api', 'roles:super admin,admin,customer service', 'emailVerified'])
    ->controller(ConsultationController::class)
    ->group(function () {
        // Core consultation management
        Route::get('/', 'index')
            ->middleware('rate_limit:admin.consultations.view')
            ->name('admin.consultations.index');
        Route::post('/', 'store')
            ->middleware('rate_limit:admin.consultations.create')
            ->name('admin.consultations.store');
        Route::get('/{consultation}', 'show')
            ->middleware('rate_limit:admin.consultations.view')
            ->name('admin.consultations.show');
        Route::put('/{consultation}', 'update')
            ->middleware('rate_limit:admin.consultations.update')
            ->name('admin.consultations.update');
        Route::delete('/{consultation}/cancel', 'cancel')
            ->middleware('rate_limit:admin.consultations.delete')
            ->name('admin.consultations.cancel');

        // Consultation workflow actions
        Route::post('/{consultation}/start', 'start')
            ->middleware('rate_limit:admin.consultations.update')
            ->name('admin.consultations.start');
        Route::post('/{consultation}/complete', 'complete')
            ->middleware('rate_limit:admin.consultations.update')
            ->name('admin.consultations.complete');
        Route::post('/{consultation}/no-show', 'markNoShow')
            ->middleware('rate_limit:admin.consultations.update')
            ->name('admin.consultations.no-show');

        // Consultation notes
        Route::post('/{consultation}/notes', 'addNote')
            ->middleware('rate_limit:admin.consultations.notes')
            ->name('admin.consultations.notes.add');

        // Statistics
        Route::get('/statistics', 'getStatistics')
            ->middleware('rate_limit:admin.statistics')
            ->name('admin.consultations.statistics');
    });

// Service management routes (Admin) - Essential routes only
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

// VENUE MANAGEMENT ROUTES (Admin)
Route::prefix('admin/venues')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->group(function () {

        // VENUE DETAILS MANAGEMENT
        Route::prefix('locations/{location}/details')
            ->controller(VenueDetailsController::class)
            ->group(function () {
                Route::get('/', 'show')
                    ->middleware('rate_limit:admin.venue_management')
                    ->name('admin.venues.details.show');
                Route::post('/', 'store')
                    ->middleware('rate_limit:admin.venue_management')
                    ->name('admin.venues.details.store');
                Route::put('/{details}', 'update')
                    ->middleware('rate_limit:admin.venue_management')
                    ->name('admin.venues.details.update');
                Route::delete('/{details}', 'destroy')
                    ->middleware('rate_limit:admin.venue_management')
                    ->name('admin.venues.details.destroy');

                // Venue analytics and validation
                Route::get('/analytics', 'getAnalytics')
                    ->middleware('rate_limit:admin.venue_analytics')
                    ->name('admin.venues.details.analytics');
                Route::post('/validate', 'validateConfiguration')
                    ->middleware('rate_limit:admin.venue_management')
                    ->name('admin.venues.details.validate');
                Route::post('/setup-instructions', 'generateSetupInstructions')
                    ->middleware('rate_limit:admin.venue_management')
                    ->name('admin.venues.details.setup-instructions');
            });

        // VENUE AVAILABILITY MANAGEMENT
        Route::prefix('locations/{location}/availability')
            ->controller(VenueAvailabilityController::class)
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('rate_limit:admin.venue_availability')
                    ->name('admin.venues.availability.index');
                Route::post('/', 'store')
                    ->middleware('rate_limit:admin.venue_availability')
                    ->name('admin.venues.availability.store');
                Route::get('/{window}', 'show')
                    ->middleware('rate_limit:admin.venue_availability')
                    ->name('admin.venues.availability.show');
                Route::put('/{window}', 'update')
                    ->middleware('rate_limit:admin.venue_availability')
                    ->name('admin.venues.availability.update');
                Route::delete('/{window}', 'destroy')
                    ->middleware('rate_limit:admin.venue_availability')
                    ->name('admin.venues.availability.destroy');

                // Bulk operations
                Route::post('/bulk-update', 'bulkUpdate')
                    ->middleware('rate_limit:admin.venue_bulk_operations')
                    ->name('admin.venues.availability.bulk-update');
                Route::post('/bulk-delete', 'bulkDelete')
                    ->middleware('rate_limit:admin.venue_bulk_operations')
                    ->name('admin.venues.availability.bulk-delete');

                // Availability analysis
                Route::get('/conflicts', 'getConflicts')
                    ->middleware('rate_limit:admin.venue_availability')
                    ->name('admin.venues.availability.conflicts');
                Route::get('/utilization', 'getUtilization')
                    ->middleware('rate_limit:admin.venue_analytics')
                    ->name('admin.venues.availability.utilization');
            });

        // VENUE AMENITY MANAGEMENT
        Route::prefix('locations/{location}/amenities')
            ->controller(VenueAmenityController::class)
            ->group(function () {
                Route::get('/', 'index')
                    ->middleware('rate_limit:admin.venue_amenities')
                    ->name('admin.venues.amenities.index');
                Route::post('/', 'store')
                    ->middleware('rate_limit:admin.venue_amenities')
                    ->name('admin.venues.amenities.store');
                Route::get('/{amenity}', 'show')
                    ->middleware('rate_limit:admin.venue_amenities')
                    ->name('admin.venues.amenities.show');
                Route::put('/{amenity}', 'update')
                    ->middleware('rate_limit:admin.venue_amenities')
                    ->name('admin.venues.amenities.update');
                Route::delete('/{amenity}', 'destroy')
                    ->middleware('rate_limit:admin.venue_amenities')
                    ->name('admin.venues.amenities.destroy');

                // Amenity operations
                Route::post('/bulk-update-availability', 'bulkUpdateAvailability')
                    ->middleware('rate_limit:admin.venue_bulk_operations')
                    ->name('admin.venues.amenities.bulk-update-availability');
                Route::post('/{amenity}/duplicate', 'duplicate')
                    ->middleware('rate_limit:admin.venue_amenities')
                    ->name('admin.venues.amenities.duplicate');

                // Amenity analysis
                Route::get('/{amenity}/usage-stats', 'getUsageStats')
                    ->middleware('rate_limit:admin.venue_analytics')
                    ->name('admin.venues.amenities.usage-stats');
                Route::get('/{amenity}/requirements', 'getRequirements')
                    ->middleware('rate_limit:admin.venue_amenities')
                    ->name('admin.venues.amenities.requirements');
                Route::get('/compatibility-matrix', 'getCompatibilityMatrix')
                    ->middleware('rate_limit:admin.venue_analytics')
                    ->name('admin.venues.amenities.compatibility-matrix');
            });

        // VENUE OVERVIEW AND MANAGEMENT
        Route::get('/dashboard', function () {
            // Venue management dashboard overview
            return response()->json([
                'message' => 'Venue management dashboard',
                'available_endpoints' => [
                    'venue_details' => '/admin/venues/locations/{location}/details',
                    'availability_windows' => '/admin/venues/locations/{location}/availability',
                    'amenities' => '/admin/venues/locations/{location}/amenities',
                ],
            ]);
        })
            ->middleware('rate_limit:admin.venue_management')
            ->name('admin.venues.dashboard');
    });

// VENUE REPORTING AND ANALYTICS (Admin)
Route::prefix('admin/reports/venues')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified'])
    ->group(function () {

        Route::get('/overview', function () {
            // Overall venue system analytics
            return response()->json(['message' => 'Venue system overview']);
        })
            ->middleware('rate_limit:admin.venue_analytics')
            ->name('admin.reports.venues.overview');

        Route::get('/utilization', function () {
            // Venue utilization reports
            return response()->json(['message' => 'Venue utilization reports']);
        })
            ->middleware('rate_limit:admin.venue_analytics')
            ->name('admin.reports.venues.utilization');

        Route::get('/revenue', function () {
            // Venue revenue analytics
            return response()->json(['message' => 'Venue revenue analytics']);
        })
            ->middleware('rate_limit:admin.venue_analytics')
            ->name('admin.reports.venues.revenue');

        Route::get('/maintenance', function () {
            // Venue maintenance tracking
            return response()->json(['message' => 'Venue maintenance reports']);
        })
            ->middleware('rate_limit:admin.venue_analytics')
            ->name('admin.reports.venues.maintenance');
    });

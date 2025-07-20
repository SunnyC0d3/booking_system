<?php

use Illuminate\Support\Facades\Route;

// Admin Controllers

use App\Http\Controllers\V1\Admin\UserController;
use App\Http\Controllers\V1\Admin\VendorController;
use App\Http\Controllers\V1\Admin\PermissionController;
use App\Http\Controllers\V1\Admin\RoleController;
use App\Http\Controllers\V1\Admin\RolePermissionController;
use App\Http\Controllers\V1\Admin\ProductController;
use App\Http\Controllers\V1\Admin\ProductAttributeController;
use App\Http\Controllers\V1\Admin\ProductCategoryController;
use App\Http\Controllers\V1\Admin\ProductTagController;
use App\Http\Controllers\V1\Admin\PaymentMethodController;
use App\Http\Controllers\V1\Admin\OrderController;
use App\Http\Controllers\V1\Admin\ReturnsController;
use App\Http\Controllers\V1\Admin\RefundController;
use App\Http\Controllers\V1\Admin\PaymentController;
use App\Http\Controllers\V1\Admin\InventoryController;
use App\Http\Controllers\V1\Admin\ReviewController;
use App\Http\Controllers\V1\Admin\ReviewResponseController;
use App\Http\Controllers\V1\Admin\ShippingMethodController;
use App\Http\Controllers\V1\Admin\ShippingZoneController;
use App\Http\Controllers\V1\Admin\ShippingRateController;
use App\Http\Controllers\V1\Admin\ShipmentController;
use App\Http\Controllers\V1\Admin\SupplierController;
use App\Http\Controllers\V1\Admin\SupplierProductController;
use App\Http\Controllers\V1\Admin\SupplierIntegrationController;
use App\Http\Controllers\V1\Admin\ProductSupplierMappingController;
use App\Http\Controllers\V1\Admin\DropshipOrderController;

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

// Admin/Vendors

Route::prefix('admin/vendors')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
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

// Admin/Products

Route::prefix('admin/products')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.products.index');
        Route::get('/{product}', 'show')->name('admin.products.show');
        Route::post('/', 'store')->name('admin.products.store');
        Route::post('/{product}', 'update')->name('admin.products.update');
        Route::delete('/soft-destroy/{product}', 'softDestroy')->name('admin.products.softDestroy');
        Route::delete('/{id}', 'destroy')->name('admin.products.destroy');
        Route::patch('/{id}/restore', 'restore')->name('admin.products.restore');
    });

// Admin/Product Attributes

Route::prefix('admin/product-attributes')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
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
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ProductCategoryController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.products.categories.index');
        Route::post('/', 'store')->name('admin.products.categories.store');
        Route::get('/{productCategory}', 'show')->name('admin.products.categories.show');
        Route::post('/{productCategory}', 'update')->name('admin.products.categories.update');
        Route::delete('/{productCategory}', 'destroy')->name('admin.products.categories.destroy');
    });

// Admin/Product Tags

Route::prefix('admin/product-tags')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ProductTagController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.products.tags.index');
        Route::post('/', 'store')->name('admin.products.tags.store');
        Route::get('/{productTag}', 'show')->name('admin.products.tags.show');
        Route::post('/{productTag}', 'update')->name('admin.products.tags.update');
        Route::delete('/{productTag}', 'destroy')->name('admin.products.tags.destroy');
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

// Admin/Orders

Route::prefix('admin/orders')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(OrderController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.orders.index');
        Route::post('/', 'store')->name('admin.orders.store');
        Route::get('/{order}', 'show')->name('admin.orders.show');
        Route::post('/{order}', 'update')->name('admin.orders.update');
        Route::delete('/{order}', 'destroy')->name('admin.orders.destroy');
        Route::delete('/{id}/force-delete', 'forceDelete')->name('admin.orders.forceDelete');
        Route::patch('/{id}/restore', 'restore')->name('admin.orders.restore');
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

// Admin/Inventory

Route::prefix('admin/inventory')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(InventoryController::class)
    ->group(function () {
        Route::get('/overview', 'overview')->name('admin.inventory.overview');
        Route::post('/products/{product}/threshold', 'updateProductThreshold')->name('admin.inventory.product.threshold');
        Route::post('/variants/{variant}/threshold', 'updateVariantThreshold')->name('admin.inventory.variant.threshold');
        Route::post('/check', 'manualCheck')->name('admin.inventory.check');
        Route::post('/bulk-update-thresholds', 'bulkUpdateThresholds')->name('admin.inventory.bulk.update');
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

// Admin/Shipping Methods

Route::prefix('admin/shipping-methods')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ShippingMethodController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.shipping-methods.index');
        Route::post('/', 'store')->name('admin.shipping-methods.store');
        Route::get('/{shippingMethod}', 'show')->name('admin.shipping-methods.show');
        Route::put('/{shippingMethod}', 'update')->name('admin.shipping-methods.update');
        Route::delete('/{shippingMethod}', 'destroy')->name('admin.shipping-methods.destroy');
        Route::patch('/{shippingMethod}/activate', 'activate')->name('admin.shipping-methods.activate');
        Route::patch('/{shippingMethod}/deactivate', 'deactivate')->name('admin.shipping-methods.deactivate');
        Route::post('/reorder', 'reorder')->name('admin.shipping-methods.reorder');
    });

// Admin/Shipping Zones

Route::prefix('admin/shipping-zones')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ShippingZoneController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.shipping-zones.index');
        Route::post('/', 'store')->name('admin.shipping-zones.store');
        Route::get('/{shippingZone}', 'show')->name('admin.shipping-zones.show');
        Route::put('/{shippingZone}', 'update')->name('admin.shipping-zones.update');
        Route::delete('/{shippingZone}', 'destroy')->name('admin.shipping-zones.destroy');
        Route::patch('/{shippingZone}/activate', 'activate')->name('admin.shipping-zones.activate');
        Route::patch('/{shippingZone}/deactivate', 'deactivate')->name('admin.shipping-zones.deactivate');
        Route::post('/reorder', 'reorder')->name('admin.shipping-zones.reorder');
        Route::post('/{shippingZone}/methods', 'attachMethod')->name('admin.shipping-zones.attach-method');
        Route::delete('/{shippingZone}/methods/{shippingMethod}', 'detachMethod')->name('admin.shipping-zones.detach-method');
        Route::put('/{shippingZone}/methods/{shippingMethod}', 'updateMethodSettings')->name('admin.shipping-zones.update-method');
    });

// Admin/Shipping Rates

Route::prefix('admin/shipping-rates')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ShippingRateController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.shipping-rates.index');
        Route::post('/', 'store')->name('admin.shipping-rates.store');
        Route::get('/{shippingRate}', 'show')->name('admin.shipping-rates.show');
        Route::put('/{shippingRate}', 'update')->name('admin.shipping-rates.update');
        Route::delete('/{shippingRate}', 'destroy')->name('admin.shipping-rates.destroy');
        Route::patch('/{shippingRate}/activate', 'activate')->name('admin.shipping-rates.activate');
        Route::patch('/{shippingRate}/deactivate', 'deactivate')->name('admin.shipping-rates.deactivate');
        Route::post('/bulk-create', 'bulkCreate')->name('admin.shipping-rates.bulk-create');
        Route::put('/bulk-update', 'bulkUpdate')->name('admin.shipping-rates.bulk-update');
        Route::post('/{shippingRate}/duplicate', 'duplicate')->name('admin.shipping-rates.duplicate');
        Route::post('/calculate', 'calculate')->name('admin.shipping-rates.calculate');
    });

// Admin/Shipments

Route::prefix('admin/shipments')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ShipmentController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.shipments.index');
        Route::post('/', 'store')->name('admin.shipments.store');
        Route::get('/stats', 'getStats')->name('admin.shipments.stats');
        Route::get('/overdue', 'getOverdue')->name('admin.shipments.overdue');
        Route::get('/{shipment}', 'show')->name('admin.shipments.show');
        Route::put('/{shipment}', 'update')->name('admin.shipments.update');
        Route::delete('/{shipment}', 'destroy')->name('admin.shipments.destroy');
        Route::post('/{shipment}/purchase-label', 'purchaseLabel')->name('admin.shipments.purchase-label');
        Route::post('/{shipment}/tracking-update', 'trackingUpdate')->name('admin.shipments.tracking-update');
        Route::post('/{shipment}/mark-shipped', 'markAsShipped')->name('admin.shipments.mark-shipped');
        Route::post('/bulk-update', 'bulkUpdate')->name('admin.shipments.bulk-update');
        Route::post('/orders/{order}/create-shipment', 'createFromOrder')->name('admin.shipments.create-from-order');
    });

// Admin/Suppliers

Route::prefix('admin/suppliers')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(SupplierController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.suppliers.index');
        Route::post('/', 'store')->name('admin.suppliers.store');
        Route::get('/{supplier}', 'show')->name('admin.suppliers.show');
        Route::put('/{supplier}', 'update')->name('admin.suppliers.update');
        Route::delete('/{supplier}', 'destroy')->name('admin.suppliers.destroy');
        Route::patch('/{supplier}/activate', 'activate')->name('admin.suppliers.activate');
        Route::patch('/{supplier}/deactivate', 'deactivate')->name('admin.suppliers.deactivate');
        Route::get('/{supplier}/stats', 'getStats')->name('admin.suppliers.stats');
        Route::post('/{supplier}/test-connection', 'testConnection')->name('admin.suppliers.test-connection');
    });

// Admin/Supplier Products

Route::prefix('admin/supplier-products')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(SupplierProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.supplier-products.index');
        Route::post('/', 'store')->name('admin.supplier-products.store');
        Route::get('/{supplierProduct}', 'show')->name('admin.supplier-products.show');
        Route::put('/{supplierProduct}', 'update')->name('admin.supplier-products.update');
        Route::delete('/{supplierProduct}', 'destroy')->name('admin.supplier-products.destroy');
        Route::post('/suppliers/{supplier}/sync', 'syncFromSupplier')->name('admin.supplier-products.sync');
        Route::post('/{supplierProduct}/map-to-product', 'mapToProduct')->name('admin.supplier-products.map');
        Route::post('/bulk-update-stock', 'bulkUpdateStock')->name('admin.supplier-products.bulk-stock');
        Route::post('/bulk-update-prices', 'bulkUpdatePrices')->name('admin.supplier-products.bulk-prices');
        Route::post('/bulk-mark-status', 'bulkMarkStatus')->name('admin.supplier-products.bulk-status');
    });

// Admin/Supplier Integrations

Route::prefix('admin/supplier-integrations')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(SupplierIntegrationController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.supplier-integrations.index');
        Route::post('/', 'store')->name('admin.supplier-integrations.store');
        Route::get('/{supplierIntegration}', 'show')->name('admin.supplier-integrations.show');
        Route::put('/{supplierIntegration}', 'update')->name('admin.supplier-integrations.update');
        Route::delete('/{supplierIntegration}', 'destroy')->name('admin.supplier-integrations.destroy');
        Route::patch('/{supplierIntegration}/enable', 'enable')->name('admin.supplier-integrations.enable');
        Route::patch('/{supplierIntegration}/disable', 'disable')->name('admin.supplier-integrations.disable');
        Route::post('/{supplierIntegration}/test', 'testIntegration')->name('admin.supplier-integrations.test');
        Route::post('/{supplierIntegration}/sync', 'syncNow')->name('admin.supplier-integrations.sync');
        Route::post('/{supplierIntegration}/reset-failures', 'resetFailures')->name('admin.supplier-integrations.reset-failures');
        Route::get('/{supplierIntegration}/logs', 'getLogs')->name('admin.supplier-integrations.logs');
    });

// Admin/Product Supplier Mappings

Route::prefix('admin/product-supplier-mappings')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(ProductSupplierMappingController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.product-supplier-mappings.index');
        Route::post('/', 'store')->name('admin.product-supplier-mappings.store');
        Route::get('/{productSupplierMapping}', 'show')->name('admin.product-supplier-mappings.show');
        Route::put('/{productSupplierMapping}', 'update')->name('admin.product-supplier-mappings.update');
        Route::delete('/{productSupplierMapping}', 'destroy')->name('admin.product-supplier-mappings.destroy');
        Route::patch('/{productSupplierMapping}/make-primary', 'makePrimary')->name('admin.product-supplier-mappings.make-primary');
        Route::patch('/{productSupplierMapping}/activate', 'activate')->name('admin.product-supplier-mappings.activate');
        Route::patch('/{productSupplierMapping}/deactivate', 'deactivate')->name('admin.product-supplier-mappings.deactivate');
        Route::post('/{productSupplierMapping}/update-markup', 'updateMarkup')->name('admin.product-supplier-mappings.update-markup');
        Route::post('/{productSupplierMapping}/sync-from-supplier', 'syncFromSupplier')->name('admin.product-supplier-mappings.sync-from-supplier');
        Route::post('/bulk-update-settings', 'bulkUpdateSettings')->name('admin.product-supplier-mappings.bulk-settings');
        Route::post('/bulk-sync-prices', 'bulkSyncPrices')->name('admin.product-supplier-mappings.bulk-sync-prices');
        Route::get('/health-report', 'getHealthReport')->name('admin.product-supplier-mappings.health-report');
    });

// Admin/Dropship Orders

Route::prefix('admin/dropship-orders')
    ->middleware(['auth:api', 'roles:super admin,admin', 'emailVerified', 'rate_limit:admin'])
    ->controller(DropshipOrderController::class)
    ->group(function () {
        Route::get('/', 'index')->name('admin.dropship-orders.index');
        Route::post('/', 'store')->name('admin.dropship-orders.store');
        Route::get('/stats', 'getStats')->name('admin.dropship-orders.stats');
        Route::get('/{dropshipOrder}', 'show')->name('admin.dropship-orders.show');
        Route::put('/{dropshipOrder}', 'update')->name('admin.dropship-orders.update');
        Route::delete('/{dropshipOrder}', 'destroy')->name('admin.dropship-orders.destroy');
        Route::post('/{dropshipOrder}/send-to-supplier', 'sendToSupplier')->name('admin.dropship-orders.send-to-supplier');
        Route::post('/{dropshipOrder}/mark-confirmed', 'markAsConfirmed')->name('admin.dropship-orders.mark-confirmed');
        Route::post('/{dropshipOrder}/mark-shipped', 'markAsShipped')->name('admin.dropship-orders.mark-shipped');
        Route::post('/{dropshipOrder}/mark-delivered', 'markAsDelivered')->name('admin.dropship-orders.mark-delivered');
        Route::post('/{dropshipOrder}/cancel', 'cancel')->name('admin.dropship-orders.cancel');
        Route::post('/{dropshipOrder}/retry', 'retry')->name('admin.dropship-orders.retry');
        Route::post('/bulk-update-status', 'bulkUpdateStatus')->name('admin.dropship-orders.bulk-status');
    });

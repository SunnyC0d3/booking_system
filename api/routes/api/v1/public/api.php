<?php

use App\Http\Controllers\V1\Admin\DigitalProductController;
use App\Http\Controllers\V1\Public\DownloadController;
use App\Http\Controllers\V1\Public\LicenseController;
use Illuminate\Support\Facades\Route;

// Auth Controllers
use App\Http\Controllers\V1\Auth\AuthController;
use App\Http\Controllers\V1\Auth\EmailVerificationController;

// Other Controllers
use App\Http\Controllers\V1\Public\UserController;
use App\Http\Controllers\V1\Public\VendorController;
use App\Http\Controllers\V1\Public\ProductController;
use App\Http\Controllers\V1\Public\OrderController;
use App\Http\Controllers\V1\Public\PaymentController;
use App\Http\Controllers\V1\Public\ReturnsController;
use App\Http\Controllers\V1\Public\CartController;
use App\Http\Controllers\V1\Public\ReviewController;
use App\Http\Controllers\V1\Public\ReviewResponseController;
use App\Http\Controllers\V1\Public\ShippingAddressController;
use App\Http\Controllers\V1\Public\ShippingCalculationController;
use App\Http\Controllers\V1\Public\ShippingController;
use App\Http\Controllers\V1\Public\CheckoutController;
use App\Http\Controllers\V1\Public\VendorDropshippingController;

// Auth

//Route::middleware(['throttle:3,1', 'client'])
Route::middleware(['client'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/register', 'register')->middleware('rate_limit:auth.register')->name('auth.register');
        Route::post('/login', 'login')->middleware('rate_limit:auth.login')->name('auth.login');
    });

Route::middleware(['auth:api', 'account_lock', 'password_expiry'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/logout', 'logout')->name('auth.logout');
        Route::post('/change-password', 'changePassword')->middleware('rate_limit:auth.password_reset')->name('password.change');
        Route::get('/security-info', 'getSecurityInfo')->name('auth.security-info');
    });

// Email Verification

Route::prefix('email')
    ->middleware(['signed'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/verify/{id}/{hash}', 'verify')->name('verification.verify');
    });

Route::prefix('email')
    ->middleware(['auth:api'])
    ->controller(EmailVerificationController::class)
    ->group(function () {
        Route::get('/resend', 'resend')->name('verification.resend');
    });

// Password Reset

Route::middleware(['throttle:3,5', 'client'])
    ->controller(AuthController::class)
    ->group(function () {
        Route::post('/forgot-password', 'forgotPassword')->middleware('rate_limit:auth.password_reset')->name('password.email');
        Route::post('/reset-password', 'passwordReset')->middleware('rate_limit:auth.password_reset')->name('password.update');
    });

// Payments

Route::prefix('payments')
    ->middleware(['rate_limit:payments'])
    ->controller(PaymentController::class)
    ->group(function () {
        Route::post('/{gateway}/create', 'store')->name('payments.store');
        Route::post('/stripe/webhook', 'stripeWebhook')->name('payments.stripe.webhook');
        Route::post('/{gateway}/verify', 'verify')->name('payments.verify');
    });

// Users

Route::prefix('users')
    ->middleware(['auth:api', 'roles:user', 'emailVerified'])
    ->controller(UserController::class)
    ->group(function () {
        Route::get('/{user}', 'show')->name('users.show');
        Route::post('/{user}', 'update')->name('users.update');
    });

// Vendors

Route::prefix('vendors')
    ->middleware(['auth:api', 'roles:vendor', 'emailVerified'])
    ->controller(VendorController::class)
    ->group(function () {
        Route::get('/{vendor}', 'show')->name('vendors.show');
        Route::post('/{vendor}', 'update')->name('vendors.update');
    });

// Products

Route::prefix('products')
    ->middleware(['client', 'rate_limit:search'])
    ->controller(ProductController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.index');
        Route::get('/{product}', 'show')->name('products.show');
    });

// Orders

Route::prefix('orders')
    ->middleware(['auth:api', 'roles:user, vendor', 'emailVerified'])
    ->controller(OrderController::class)
    ->group(function () {
        Route::get('/', 'index')->name('orders.index');
        Route::get('/{order}', 'show')->name('orders.show');
        Route::post('/from-cart', 'create')->name('orders.create-from-cart');
    });

// Returns

Route::prefix('returns')
    ->middleware(['auth:api', 'roles:user, vendor', 'emailVerified'])
    ->controller(ReturnsController::class)
    ->group(function () {
        Route::post('/', 'return')->name('returns');
    });

// Cart

Route::prefix('cart')
    ->middleware(['auth:api', 'rate_limit:cart'])
    ->controller(CartController::class)
    ->group(function () {
        Route::get('/', 'index')->name('cart.index');
        Route::post('/items', 'store')->name('cart.add');
        Route::post('/items/{cartItem}', 'update')->name('cart.update');
        Route::delete('/items/{cartItem}', 'destroy')->name('cart.remove');
        Route::delete('/clear', 'clear')->name('cart.clear');
        Route::post('/sync-prices', 'syncPrices')->name('cart.sync-prices');
    });

// Public Review Viewing (Guest + Authenticated users can view reviews)

Route::prefix('products/{product}/reviews')
    ->middleware(['review.smart_throttle:view,false'])
    ->controller(ReviewController::class)
    ->group(function () {
        Route::get('/', 'index')->name('products.reviews.index');
    });

Route::prefix('reviews')
    ->middleware(['review.smart_throttle:view,false'])
    ->controller(ReviewController::class)
    ->group(function () {
        Route::get('/{review}', 'show')->name('reviews.show');
    });

// Review Actions (Require Authentication) - SmartReviewThrottle handles auth

Route::prefix('reviews')
    ->controller(ReviewController::class)
    ->group(function () {
        Route::post('/', 'store')
            ->middleware(['review.smart_throttle:create,true'])
            ->name('reviews.store');
    });

Route::prefix('reviews/{review}')
    ->controller(ReviewController::class)
    ->group(function () {
        Route::post('/', 'update')
            ->middleware(['review.smart_throttle:update,true'])
            ->name('reviews.update');

        Route::delete('/', 'destroy')
            ->middleware(['review.smart_throttle:delete,true'])
            ->name('reviews.destroy');

        Route::post('/helpfulness', 'voteHelpfulness')
            ->middleware(['review.smart_throttle:vote,true'])
            ->name('reviews.helpfulness');

        Route::post('/report', 'report')
            ->middleware(['review.smart_throttle:report,true'])
            ->name('reviews.report');
    });

// Review Responses - Public Viewing (No auth required)

Route::prefix('reviews/{review}/responses')
    ->middleware(['review.smart_throttle:responses,false'])
    ->controller(ReviewResponseController::class)
    ->group(function () {
        Route::get('/', 'publicIndex')->name('review.responses.public.index');
        Route::get('/{response}', 'publicShow')->name('review.responses.public.show');
    });

// Review Responses - Vendor Actions (Auth required + Role check)

Route::prefix('reviews/{review}/responses')
    ->middleware(['review.smart_throttle:respond,true', 'roles:vendor'])
    ->controller(ReviewResponseController::class)
    ->group(function () {
        Route::post('/', 'store')->name('review.responses.store');
        Route::post('/{response}', 'update')->name('review.responses.update');
        Route::delete('/{response}', 'destroy')->name('review.responses.destroy');
    });

// Vendor Dashboard (Auth required + Role check)

Route::prefix('vendor/responses')
    ->middleware(['review.smart_throttle:dashboard,true', 'roles:vendor'])
    ->controller(ReviewResponseController::class)
    ->group(function () {
        Route::get('/', 'index')->name('vendor.responses.index');
        Route::get('/{response}', 'show')->name('vendor.responses.show');
    });

// Vendor Unanswered Reviews

Route::get('vendor/unanswered-reviews', [ReviewResponseController::class, 'getUnansweredReviews'])
    ->middleware(['review.smart_throttle:dashboard,true', 'roles:vendor'])
    ->name('vendor.responses.unanswered');

// Vendor Dropshipping (Read-Only Access)

Route::prefix('vendor/dropshipping')
    ->middleware(['auth:api', 'roles:vendor', 'emailVerified', 'rate_limit:dropshipping'])
    ->controller(VendorDropshippingController::class)
    ->group(function () {
        Route::get('/dashboard', 'getDashboard')->name('vendor.dropshipping.dashboard');
        Route::get('/orders', 'getDropshipOrders')->name('vendor.dropshipping.orders.index');
        Route::get('/orders/{dropshipOrder}', 'getDropshipOrder')->name('vendor.dropshipping.orders.show');
        Route::get('/suppliers', 'getSuppliers')->name('vendor.dropshipping.suppliers.index');
        Route::get('/suppliers/{supplier}', 'getSupplier')->name('vendor.dropshipping.suppliers.show');
        Route::get('/supplier-products', 'getSupplierProducts')->name('vendor.dropshipping.supplier-products.index');
        Route::get('/supplier-products/{supplierProduct}', 'getSupplierProduct')->name('vendor.dropshipping.supplier-products.show');
        Route::get('/product-mappings', 'getProductMappings')->name('vendor.dropshipping.product-mappings.index');
        Route::get('/product-mappings/{productSupplierMapping}', 'getProductMapping')->name('vendor.dropshipping.product-mappings.show');
        Route::get('/analytics', 'getAnalytics')->name('vendor.dropshipping.analytics');
        Route::get('/profit-margins', 'getProfitMargins')->name('vendor.dropshipping.profit-margins');
        Route::get('/supplier-performance', 'getSupplierPerformance')->name('vendor.dropshipping.supplier-performance');
    });

// Shipping Addresses (User-specific)

Route::prefix('shipping-addresses')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:shipping'])
    ->controller(ShippingAddressController::class)
    ->group(function () {
        Route::get('/', 'index')->name('shipping-addresses.index');
        Route::post('/', 'store')->name('shipping-addresses.store');
        Route::get('/{shippingAddress}', 'show')->name('shipping-addresses.show');
        Route::put('/{shippingAddress}', 'update')->name('shipping-addresses.update');
        Route::delete('/{shippingAddress}', 'destroy')->name('shipping-addresses.destroy');
        Route::patch('/{shippingAddress}/set-default', 'setDefault')->name('shipping-addresses.set-default');
        Route::post('/{shippingAddress}/validate', 'validate')->name('shipping-addresses.validate');
    });

// Shipping Calculations and Quotes

Route::prefix('shipping')
    ->middleware(['rate_limit:shipping'])
    ->controller(ShippingCalculationController::class)
    ->group(function () {
        // Cart shipping (requires auth)
        Route::post('/cart/quote', 'getCartShippingQuote')
            ->middleware(['auth:api'])
            ->name('shipping.cart.quote');

        // Product shipping (requires auth)
        Route::post('/products/quote', 'getProductShippingQuote')
            ->middleware(['auth:api'])
            ->name('shipping.products.quote');

        // Quick estimate (public - no auth required)
        Route::post('/estimate', 'getQuickEstimate')->name('shipping.estimate');

        // Cheapest/Fastest options (requires auth)
        Route::post('/cheapest', 'getCheapestOption')
            ->middleware(['auth:api'])
            ->name('shipping.cheapest');
        Route::post('/fastest', 'getFastestOption')
            ->middleware(['auth:api'])
            ->name('shipping.fastest');

        // Method validation (requires auth)
        Route::post('/validate-method', 'validateShippingMethod')
            ->middleware(['auth:api'])
            ->name('shipping.validate-method');
    });

// Shipment Tracking (public - no auth required for tracking)

Route::prefix('tracking')
    ->middleware(['rate_limit:tracking'])
    ->controller(ShippingController::class)
    ->group(function () {
        Route::get('/{trackingNumber}', 'trackShipment')->name('tracking.shipment');
        Route::get('/{trackingNumber}/status', 'getShipmentStatus')->name('tracking.status');
    });

// User's Shipments (requires auth)

Route::prefix('my-shipments')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:shipping'])
    ->controller(ShippingController::class)
    ->group(function () {
        Route::get('/', 'getUserShipments')->name('my-shipments.index');
        Route::get('/{shipment}', 'getUserShipment')->name('my-shipments.show');
    });

// Checkout

Route::prefix('checkout')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:checkout'])
    ->controller(CheckoutController::class)
    ->group(function () {
        Route::post('/summary', 'getCheckoutSummary')->name('checkout.summary');
        Route::post('/validate', 'validateCheckout')->name('checkout.validate');
    });

// Customer Digital Library - Authenticated User Access

Route::prefix('my-digital-products')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:user'])
    ->controller(DigitalProductController::class)
    ->group(function () {
        Route::get('/', 'userLibrary')->name('my-digital-products.index');
        Route::get('/downloads', 'userLibrary')->name('my-digital-products.downloads');
        Route::get('/licenses', 'userLicenses')->name('my-digital-products.licenses');
        Route::get('/statistics', 'userDigitalStats')->name('my-digital-products.statistics');
    });

// Secure Digital Downloads - Authenticated User Access

Route::prefix('digital/download')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:downloads'])
    ->controller(DownloadController::class)
    ->group(function () {
        Route::get('/{token}', 'download')->name('digital.download');
        Route::get('/{token}/info', 'info')->name('digital.download.info');
        Route::post('/{token}/progress/{attemptId}', 'updateProgress')->name('digital.download.progress');
    });

// Alternative download routes for better UX

Route::prefix('download')
    ->middleware(['auth:api', 'emailVerified', 'rate_limit:downloads'])
    ->controller(DownloadController::class)
    ->group(function () {
        Route::get('/{token}', 'download')->name('download.file');
        Route::get('/{token}/info', 'info')->name('download.info');
        Route::post('/{token}/progress/{attemptId}', 'updateProgress')->name('download.progress');
    });

// License Key Validation - Unauthenticated (for software applications)

Route::prefix('license')
    ->middleware(['rate_limit:license_validation'])
    ->controller(LicenseController::class)
    ->group(function () {
        Route::post('/validate', 'validate')->name('license.validate');
        Route::post('/activate', 'activate')->name('license.activate');
        Route::post('/deactivate', 'deactivate')->name('license.deactivate');
        Route::post('/info', 'info')->name('license.info');
        Route::post('/check-updates', 'checkUpdates')->name('license.check-updates');
        Route::post('/report-usage', 'reportUsage')->name('license.report-usage');
    });

// Alternative license routes for different integrations

Route::prefix('api/license')
    ->middleware(['rate_limit:license_validation'])
    ->controller(LicenseController::class)
    ->group(function () {
        Route::post('/v1/validate', 'validate')->name('api.license.validate');
        Route::post('/v1/activate', 'activate')->name('api.license.activate');
        Route::post('/v1/deactivate', 'deactivate')->name('api.license.deactivate');
        Route::post('/v1/info', 'info')->name('api.license.info');
        Route::post('/v1/updates', 'checkUpdates')->name('api.license.updates');
        Route::post('/v1/analytics', 'reportUsage')->name('api.license.analytics');
    });

// Public Product Information - No authentication required
Route::prefix('products/{product}/digital-info')
    ->middleware(['client', 'rate_limit:search'])
    ->group(function () {
        Route::get('/', function ($product) {
            $product = \App\Models\Product::findOrFail($product);

            if (!$product->isDigital()) {
                abort(404, 'Product does not have digital components');
            }

            return response()->json([
                'data' => [
                    'product_type' => $product->product_type,
                    'requires_license' => $product->requires_license,
                    'supported_platforms' => $product->supported_platforms,
                    'system_requirements' => $product->system_requirements,
                    'latest_version' => $product->latest_version,
                    'download_info' => [
                        'download_limit' => $product->download_limit,
                        'download_expiry_days' => $product->download_expiry_days,
                        'auto_delivery' => $product->auto_deliver,
                    ],
                ],
                'message' => 'Digital product information retrieved successfully.',
                'status' => 200
            ]);
        })->name('products.digital-info');
    });

// Guest Download Information - Limited info without authentication
Route::prefix('download-info')
    ->middleware(['rate_limit:guest_info'])
    ->group(function () {
        Route::get('/{token}', function ($token) {
            $downloadAccess = \App\Models\DownloadAccess::where('access_token', $token)->first();

            if (!$downloadAccess) {
                abort(404, 'Download access not found');
            }

            return response()->json([
                'data' => [
                    'product_name' => $downloadAccess->product->name,
                    'expires_at' => $downloadAccess->expires_at,
                    'downloads_remaining' => $downloadAccess->getRemainingDownloads(),
                    'status' => $downloadAccess->status,
                    'requires_login' => true,
                ],
                'message' => 'Download information retrieved successfully.',
                'status' => 200
            ]);
        })->name('download-info.guest');
    });

// Digital Product Support Routes - Customer Service

Route::prefix('support/download')
    ->middleware(['auth:api', 'roles:customer service', 'emailVerified', 'rate_limit:support'])
    ->group(function () {
        Route::get('/access/{downloadAccess}', [DownloadController::class, 'info'])->name('support.download.info');
        Route::post('/access/{downloadAccess}/extend', function ($downloadAccessId) {
            // Customer service can extend download access
            $downloadAccess = \App\Models\DownloadAccess::findOrFail($downloadAccessId);

            request()->validate([
                'additional_days' => 'required|integer|min:1|max:365',
                'reason' => 'required|string|max:500',
            ]);

            $downloadAccess->extendExpiry(request('additional_days'));

            \Illuminate\Support\Facades\Log::info('Download access extended by customer service', [
                'access_id' => $downloadAccess->id,
                'extended_by' => request()->user()->id,
                'additional_days' => request('additional_days'),
                'reason' => request('reason')
            ]);

            return response()->json([
                'data' => [
                    'access_id' => $downloadAccess->id,
                    'new_expiry' => $downloadAccess->fresh()->expires_at,
                    'extended_by_days' => request('additional_days'),
                ],
                'message' => 'Download access extended successfully.',
                'status' => 200
            ]);
        })->name('support.download.extend');
    });

// Webhook Routes - For external integrations

Route::prefix('webhooks/digital-products')
    ->middleware(['rate_limit:webhooks'])
    ->group(function () {
        Route::post('/license-validation', function () {
            // External webhook for license validation
            request()->validate([
                'license_key' => 'required|string',
                'product_id' => 'nullable|integer',
                'webhook_signature' => 'required|string',
            ]);

            // Verify webhook signature here
            // ... signature verification logic

            try {
                $licenseService = app(\App\Services\V1\DigitalProducts\LicenseKeyService::class);
                $license = $licenseService->validateLicense(
                    request('license_key'),
                    request('product_id')
                );

                return response()->json([
                    'valid' => true,
                    'license_status' => $license->status,
                    'expires_at' => $license->expires_at,
                    'activations_remaining' => $license->getRemainingActivations(),
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'valid' => false,
                    'error' => $e->getMessage(),
                ], 400);
            }
        })->name('webhooks.license-validation');

        Route::post('/download-completed', function () {
            // Webhook for when downloads are completed
            request()->validate([
                'access_token' => 'required|string',
                'download_size' => 'required|integer',
                'completion_time' => 'required|date',
                'webhook_signature' => 'required|string',
            ]);

            // Process download completion webhook
            $downloadAccess = \App\Models\DownloadAccess::where('access_token', request('access_token'))->first();

            if ($downloadAccess) {
                $downloadAccess->recordDownload();

                return response()->json([
                    'message' => 'Download completion recorded.',
                    'remaining_downloads' => $downloadAccess->getRemainingDownloads(),
                ]);
            }

            return response()->json(['error' => 'Access token not found'], 404);
        })->name('webhooks.download-completed');
    });

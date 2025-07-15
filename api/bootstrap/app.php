<?php

use App\Http\Middleware\V1\CheckAccountLock;
use App\Http\Middleware\V1\CheckPasswordExpiry;
use App\Http\Middleware\V1\DynamicRateLimit;
use App\Http\Middleware\V1\EnsureEmailIsVerified;
use App\Http\Middleware\V1\GuestReviewRestriction;
use App\Http\Middleware\V1\ReviewRateLimit;
use App\Http\Middleware\V1\Role;
use App\Http\Middleware\V1\SecureFileUpload;
use App\Http\Middleware\V1\SecurityHeaders;
use App\Http\Middleware\V1\SecurityLogger;
use App\Http\Middleware\V1\SecurityMonitoring;
use App\Http\Middleware\V1\SmartReviewThrottle;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;
use Laravel\Passport\Http\Middleware\CheckForAnyScope;
use Laravel\Passport\Http\Middleware\CheckScopes;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: [
            __DIR__ . '/../routes/api/v1/admin/api.php',
            __DIR__ . '/../routes/api/v1/public/api.php'
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            SecurityHeaders::class,
            SecurityLogger::class,
            SecurityMonitoring::class,
        ]);

        $middleware->alias([
            'client' => CheckClientCredentials::class,
            'scopes' => CheckScopes::class,
            'scope' => CheckForAnyScope::class,
            'roles' => Role::class,
            'emailVerified' => EnsureEmailIsVerified::class,
            'rate_limit' => DynamicRateLimit::class,
            'security_logger' => SecurityLogger::class,
            'security_monitor' => SecurityMonitoring::class,
            'secure_upload' => SecureFileUpload::class,
            'account_lock' => CheckAccountLock::class,
            'password_expiry' => CheckPasswordExpiry::class,
            'review.guest_restrict' => GuestReviewRestriction::class,
            'review.rate_limit' => ReviewRateLimit::class,
            'review.smart_throttle' => SmartReviewThrottle::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

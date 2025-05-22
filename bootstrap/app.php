<?php

use App\Http\Middleware\V1\EnsureEmailIsVerified;
use App\Http\Middleware\V1\Role;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Passport\Http\Middleware\CheckClientCredentials;
use Laravel\Passport\Http\Middleware\CheckForAnyScope;
use Laravel\Passport\Http\Middleware\CheckScopes;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [
            __DIR__ . '/../routes/web/v1/public/web.php',
        ],
        api: [
            __DIR__ . '/../routes/api/v1/admin/api.php',
            __DIR__ . '/../routes/api/v1/public/api.php'
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'client' => CheckClientCredentials::class,
            'scopes' => CheckScopes::class,
            'scope' => CheckForAnyScope::class,
            'roles' => Role::class,
            'emailVerified' => EnsureEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

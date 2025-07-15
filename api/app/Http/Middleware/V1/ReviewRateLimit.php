<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;

class ReviewRateLimit
{
    /**
     * Apply appropriate rate limiting based on user authentication status
     */
    public function handle(Request $request, Closure $next, string $action = 'view')
    {
        $user = $request->user();

        $rateLimitKey = $user
            ? "reviews.{$action}"
            : "guest.review_{$action}";

        $rateLimitMiddleware = new DynamicRateLimit(app('Illuminate\Cache\RateLimiter'));

        return $rateLimitMiddleware->handle($request, $next, $rateLimitKey);
    }
}

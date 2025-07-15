<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;

class SmartReviewThrottle
{
    /**
     * Smart throttling that combines authentication check and rate limiting
     */
    public function handle(Request $request, Closure $next, string $action = 'view', bool $requireAuth = false)
    {
        $user = $request->user();

        dd($requireAuth);

        if ($requireAuth && !$user) {
            $guestRestriction = new GuestReviewRestriction();
            return $guestRestriction->handle($request, function () {
            }, $action);
        }

        $reviewRateLimit = new ReviewRateLimit();
        return $reviewRateLimit->handle($request, $next, $action);
    }
}

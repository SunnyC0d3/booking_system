<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;

class SmartReviewThrottle
{
    /**
     * Smart throttling that combines authentication check and rate limiting
     */
    public function handle(Request $request, Closure $next, string $action = 'view', string $requireAuth = 'false')
    {
        $requireAuthBool = filter_var($requireAuth, FILTER_VALIDATE_BOOLEAN);

        $user = $this->attemptAuthentication($request);

        if ($requireAuthBool && !$user) {
            $guestRestriction = new GuestReviewRestriction();
            return $guestRestriction->handle($request, function () {}, $action);
        }

        $reviewRateLimit = new ReviewRateLimit();
        return $reviewRateLimit->handle($request, $next, $action);
    }

    /**
     * Attempt to authenticate the user if authentication headers are present
     */
    private function attemptAuthentication(Request $request)
    {
        $user = $request->user();

        if ($user) {
            return $user;
        }

        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        try {
            $guard = auth('api');

            $user = $guard->user();

            if ($user) {
                $request->setUserResolver(function () use ($user) {
                    return $user;
                });

                $guard->setUser($user);
            }

            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }
}

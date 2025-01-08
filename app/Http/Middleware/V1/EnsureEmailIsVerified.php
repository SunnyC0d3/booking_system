<?php

namespace App\Http\Middleware\V1;

use App\Traits\V1\ApiResponses;
use Closure;

class EnsureEmailIsVerified
{
    use ApiResponses;

    public function handle($request, Closure $next)
    {
        if (!$request->user() || !$request->user()->hasVerifiedEmail()) {
            return $this->error('Your email address is not verified.', 403);
        }

        return $next($request);
    }
}

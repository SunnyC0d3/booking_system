<?php

namespace App\Http\Middleware\V1;

use App\Traits\V1\ApiResponses;
use Closure;

class Role
{
    use ApiResponses;

    public function handle($request, Closure $next, ...$roles)
    {
        if (!$request->user() || !in_array($request->user()->role, $roles)) {
            return $this->error('Unauthorized action', 403);
        }

        return $next($request);
    }
}

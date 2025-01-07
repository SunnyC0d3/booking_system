<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RestrictExternalAccess
{
    public function handle(Request $request, Closure $next)
    {
        $allowedDomains = [env('APP_URL_FRONTEND'), env('APP_URL_FRONTEND')];

        foreach($allowedDomains as $allowedDomain) {
            if (strpos($request->header('Referer'), $allowedDomain) !== false) {
                return $next($request);
            }
        }

        return response('Forbidden', 403);
    }
}
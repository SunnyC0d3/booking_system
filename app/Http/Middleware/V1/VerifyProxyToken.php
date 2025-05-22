<?php

namespace App\Http\Middleware\V1;

use App\Traits\V1\ApiResponses;
use Closure;
use Illuminate\Http\Request;

class VerifyProxyToken
{
    use ApiResponses;

    public function handle(Request $request, Closure $next)
    {
        $proxyToken = $request->header('X-Proxy-Token');

        if (!$proxyToken) {
            return $this->error('Missing proxy token', 401);
        }

        try {
            $decrypted = decrypt($proxyToken);

            if ($decrypted !== config('services.proxy_key')) {
                return $this->error('Invalid proxy token', 401);
            }
        } catch (\Exception $e) {
            return $this->error('Invalid or expired proxy token', 401);
        }

        return $next($request);
    }
}

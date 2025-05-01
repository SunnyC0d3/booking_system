<?php

namespace App\Http\Middleware\V1;

use App\Traits\V1\ApiResponses;
use Closure;
use Illuminate\Http\Request;

class VerifyHmac
{
    use ApiResponses;

    public function handle(Request $request, Closure $next)
    {
        $sharedSecretKey = config('services.hmac_secret');
        $clientHmac = $request->header('X-Hmac');
        $timestamp = $request->header('X-Timestamp');
        $body = $request->getContent();

        if (!$clientHmac || !$timestamp) {
            return $this->error('Unauthorized', 401);
        }

        if (abs(time() - (int)$timestamp) > 300) {
            return $this->error('Request expired', 401);
        }

        $serverHmac = hash_hmac('sha256', $timestamp . $body, $sharedSecretKey);

        if (!hash_equals($serverHmac, $clientHmac)) {
            return $this->error('Invalid HMAC', 401);
        }

        return $next($request);
    }
}

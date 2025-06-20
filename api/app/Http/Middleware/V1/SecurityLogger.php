<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;
use App\Services\V1\Logger\SecurityLog;
use Symfony\Component\HttpFoundation\Response;

class SecurityLogger
{
    protected SecurityLog $securityLogger;

    public function __construct(SecurityLog $securityLogger)
    {
        $this->securityLogger = $securityLogger;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $responseTime = microtime(true) - $startTime;

        $this->securityLogger->logApiAccess($request, $response->getStatusCode(), $responseTime);

        return $response;
    }
}

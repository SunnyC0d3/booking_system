<?php

namespace App\Http\Middleware\V1;

use App\Services\V1\Logger\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecurityMonitoring
{
    protected SecurityLog $securityLogger;

    public function __construct(SecurityLog $securityLogger)
    {
        $this->securityLogger = $securityLogger;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user) {
            $this->checkForSuspiciousActivity($request, $user);
        }

        $response = $next($request);

        if ($user && $this->isAuthenticationRoute($request)) {
            $this->logAuthenticationActivity($request, $user, $response);
        }

        return $response;
    }

    protected function checkForSuspiciousActivity(Request $request, $user): void
    {
        $suspiciousPatterns = [
            'rapid_requests' => $this->checkRapidRequests($request, $user),
            'unusual_user_agent' => $this->checkUnusualUserAgent($request, $user),
            'location_change' => $this->checkLocationChange($request, $user),
            'time_anomaly' => $this->checkTimeAnomaly($request, $user),
        ];

        foreach ($suspiciousPatterns as $pattern => $detected) {
            if ($detected) {
                $this->securityLogger->logSecurityViolation(
                    "suspicious_activity_{$pattern}",
                    $request,
                    [
                        'user_id' => $user->id,
                        'pattern_type' => $pattern,
                        'risk_level' => $this->calculateRiskLevel($pattern),
                    ]
                );
            }
        }
    }

    protected function checkRapidRequests(Request $request, $user): bool
    {
        $cacheKey = "user_requests:{$user->id}";
        $requests = cache()->get($cacheKey, []);

        $currentTime = time();
        $requests[] = $currentTime;

        $requests = array_filter($requests, fn($time) => $currentTime - $time < 60);

        cache()->put($cacheKey, $requests, now()->addMinutes(2));

        return count($requests) > 100;
    }

    protected function checkUnusualUserAgent(Request $request, $user): bool
    {
        $currentUserAgent = $request->userAgent();
        $lastUserAgent = cache()->get("user_agent:{$user->id}");

        cache()->put("user_agent:{$user->id}", $currentUserAgent, now()->addDays(7));

        if ($lastUserAgent && $lastUserAgent !== $currentUserAgent) {
            $similarity = similar_text($lastUserAgent, $currentUserAgent);
            return $similarity < 50;
        }

        return false;
    }

    protected function checkLocationChange(Request $request, $user): bool
    {
        $currentIp = $request->ip();
        $lastIp = $user->last_login_ip;

        if ($lastIp && $lastIp !== $currentIp) {
            $ipParts1 = explode('.', $lastIp);
            $ipParts2 = explode('.', $currentIp);

            if (count($ipParts1) === 4 && count($ipParts2) === 4) {
                return $ipParts1[0] !== $ipParts2[0] || $ipParts1[1] !== $ipParts2[1];
            }
        }

        return false;
    }

    protected function checkTimeAnomaly(Request $request, $user): bool
    {
        if (!$user->last_login_at) {
            return false;
        }

        $currentHour = (int) date('H');
        $lastLoginHour = (int) $user->last_login_at->format('H');

        $hourDifference = abs($currentHour - $lastLoginHour);

        return $hourDifference > 12;
    }

    protected function calculateRiskLevel(string $pattern): string
    {
        $riskLevels = [
            'rapid_requests' => 'medium',
            'unusual_user_agent' => 'low',
            'location_change' => 'high',
            'time_anomaly' => 'low',
        ];

        return $riskLevels[$pattern] ?? 'low';
    }

    protected function isAuthenticationRoute(Request $request): bool
    {
        $authRoutes = [
            '/login',
            '/register',
            '/logout',
            '/password/reset',
            '/password/change',
        ];

        $path = $request->path();

        foreach ($authRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }

    protected function logAuthenticationActivity(Request $request, $user, $response): void
    {
        $statusCode = $response->getStatusCode();

        $activityData = [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route' => $request->path(),
            'method' => $request->method(),
            'status_code' => $statusCode,
            'success' => $statusCode < 400,
        ];

        if ($statusCode >= 400) {
            $this->securityLogger->logAuthEvent(
                'authentication_failed',
                $request,
                $activityData
            );
        } else {
            $this->securityLogger->logAuthEvent(
                'authentication_success',
                $request,
                $activityData
            );
        }
    }
}

<?php

namespace App\Services\V1\Logger;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SecurityLog
{
    public function logAuthEvent(string $event, Request $request, ?array $additionalData = null): void
    {
        $logData = [
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => Carbon::now()->toISOString(),
            'email' => $request->input('email'),
            'success' => str_contains($event, 'success'),
        ];

        if ($additionalData) {
            $logData = array_merge($logData, $additionalData);
        }

        Log::channel('security')->info("Auth Event: {$event}", $logData);

        if (str_contains($event, 'failed') || str_contains($event, 'blocked')) {
            Log::channel('suspicious')->warning("Suspicious Auth Activity: {$event}", $logData);

            $this->checkForBruteForce($request);
        }
    }

    public function logApiAccess(Request $request, int $statusCode, ?float $responseTime = null): void
    {
        $user = Auth::user();

        $logData = [
            'method' => $request->method(),
            'endpoint' => $request->path(),
            'status_code' => $statusCode,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_role' => $user?->role?->name,
            'response_time_ms' => $responseTime ? round($responseTime * 1000, 2) : null,
            'timestamp' => Carbon::now()->toISOString(),
        ];

        Log::channel('audit')->info('API Access', $logData);

        $this->detectSuspiciousApiPatterns($request, $statusCode, $logData);
    }

    public function logSecurityViolation(string $violation, Request $request, array $details = []): void
    {
        $logData = array_merge([
            'violation' => $violation,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'timestamp' => Carbon::now()->toISOString(),
            'severity' => 'high',
        ], $details);

        Log::channel('security')->critical("Security Violation: {$violation}", $logData);
        Log::channel('suspicious')->critical("Critical Security Event: {$violation}", $logData);

        $this->alertOnCriticalViolation($violation, $logData);
    }

    public function logFileUpload(Request $request, string $outcome, array $fileDetails = []): void
    {
        $logData = array_merge([
            'event' => 'file_upload',
            'outcome' => $outcome,
            'ip' => $request->ip(),
            'user_id' => Auth::id(),
            'endpoint' => $request->path(),
            'timestamp' => Carbon::now()->toISOString(),
        ], $fileDetails);

        if ($outcome === 'blocked') {
            Log::channel('security')->warning('Malicious File Upload Blocked', $logData);
            Log::channel('suspicious')->warning('Suspicious File Upload', $logData);
        } else {
            Log::channel('audit')->info('File Upload', $logData);
        }
    }

    public function logPaymentEvent(string $event, array $paymentData): void
    {
        $sanitizedData = $this->sanitizePaymentData($paymentData);

        $logData = array_merge([
            'event' => $event,
            'timestamp' => Carbon::now()->toISOString(),
            'severity' => str_contains($event, 'failed') ? 'high' : 'normal',
        ], $sanitizedData);

        Log::channel('audit')->info("Payment Event: {$event}", $logData);

        if (str_contains($event, 'failed') || str_contains($event, 'fraud')) {
            Log::channel('suspicious')->warning("Suspicious Payment Activity: {$event}", $logData);
        }
    }

    protected function checkForBruteForce(Request $request): void
    {
        $ip = $request->ip();
        $cacheKey = "failed_attempts:{$ip}";

        $attempts = cache()->get($cacheKey, 0) + 1;
        cache()->put($cacheKey, $attempts, now()->addHour());

        if ($attempts >= 5) {
            $this->logSecurityViolation('brute_force_detected', $request, [
                'attempts' => $attempts,
                'time_window' => '1 hour',
                'action_required' => 'consider_ip_blocking'
            ]);
        }
    }

    protected function detectSuspiciousApiPatterns(Request $request, int $statusCode, array $logData): void
    {
        if ($statusCode === 429) {
            Log::channel('suspicious')->warning('Rate Limit Exceeded', $logData);
        }

        if ($statusCode === 403) {
            Log::channel('suspicious')->info('Permission Denied', $logData);
        }

        if ($statusCode === 404 && $this->isPotentialScanning($request)) {
            Log::channel('suspicious')->warning('Potential Endpoint Scanning', $logData);
        }

        if ($this->detectSqlInjectionAttempt($request)) {
            $this->logSecurityViolation('sql_injection_attempt', $request, [
                'parameters' => $request->all()
            ]);
        }
    }

    protected function isPotentialScanning(Request $request): bool
    {
        $suspiciousPatterns = [
            '/admin', '/wp-admin', '/.env', '/api/v2', '/test', '/debug',
            '/phpmyadmin', '/config', '/backup', '/sql'
        ];

        $path = $request->path();
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function detectSqlInjectionAttempt(Request $request): bool
    {
        $sqlPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            '/or\s+1\s*=\s*1/i',
            '/\'\s*or\s*\'/i',
            '/--/i',
            '/\/\*/i'
        ];

        $input = json_encode($request->all());
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    protected function alertOnCriticalViolation(string $violation, array $logData): void
    {
        $criticalViolations = [
            'sql_injection_attempt',
            'brute_force_detected',
            'privilege_escalation',
            'malicious_file_upload'
        ];

        if (in_array($violation, $criticalViolations)) {
            Log::channel('security')->critical('IMMEDIATE ATTENTION REQUIRED', [
                'violation' => $violation,
                'data' => $logData,
                'action' => 'investigate_immediately'
            ]);
        }
    }

    protected function sanitizePaymentData(array $paymentData): array
    {
        $sensitiveFields = [
            'card_number', 'cvv', 'password', 'pin', 'token'
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($paymentData[$field])) {
                $paymentData[$field] = '***REDACTED***';
            }
        }

        return $paymentData;
    }
}

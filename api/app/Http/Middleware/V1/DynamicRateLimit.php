<?php

namespace App\Http\Middleware\V1;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;

class DynamicRateLimit
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    public function handle(Request $request, Closure $next, string $limitType = 'general')
    {
        $limits = config('rate-limiting');
        $limitConfig = $this->parseLimitConfig($limits, $limitType, $request);

        if (!$limitConfig) {
            return $next($request);
        }

        [$maxAttempts, $decayMinutes] = $limitConfig;

        $key = $this->resolveRequestSignature($request, $limitType);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildFailedResponse($key, $maxAttempts, $decayMinutes, $limitType);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts),
            $this->calculateRetryAfter($key)
        );
    }

    protected function parseLimitConfig(array $limits, string $limitType, Request $request): ?array
    {
        $user = $request->user();

        if (!$user) {
            if (str_contains($limitType, '.')) {
                [$category, $type] = explode('.', $limitType, 2);

                $guestLimitString = $limits['guest'][$type] ?? null;
                if ($guestLimitString) {
                    [$maxAttempts, $decayMinutes] = explode(',', $guestLimitString);
                    return [(int) $maxAttempts, (int) $decayMinutes];
                }

                $limitString = $limits[$category][$type] ?? null;
            } else {
                $limitString = $limits['guest'][$limitType] ?? $limits['api'][$limitType] ?? null;
            }
        } else {
            if (str_contains($limitType, '.')) {
                [$category, $type] = explode('.', $limitType, 2);
                $limitString = $limits[$category][$type] ?? null;
            } else {
                $limitString = $limits['api'][$limitType] ?? null;
            }
        }

        if (!$limitString) {
            return null;
        }

        [$maxAttempts, $decayMinutes] = explode(',', $limitString);
        return [(int) $maxAttempts, (int) $decayMinutes];
    }

    protected function resolveRequestSignature(Request $request, string $limitType): string
    {
        $user = $request->user();

        if ($user) {
            return sprintf(
                'rate_limit:%s:%s:%s',
                $limitType,
                $user->id,
                sha1($request->ip())
            );
        }

        $fingerprint = sha1($request->ip() . '|' . $request->userAgent());

        return sprintf(
            'rate_limit:guest:%s:%s',
            $limitType,
            $fingerprint
        );
    }

    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->limiter->attempts($key));
    }

    protected function calculateRetryAfter(string $key): int
    {
        return $this->limiter->availableIn($key);
    }

    protected function buildFailedResponse(string $key, int $maxAttempts, int $decayMinutes, string $limitType)
    {
        $retryAfter = $this->calculateRetryAfter($key);
        $isGuest = str_starts_with($key, 'rate_limit:guest:');

        $message = $isGuest
            ? 'Too many requests. Please log in for higher limits or try again later.'
            : 'Too many requests. Please try again later.';

        $specificMessages = [
            'reviews.create' => $isGuest
                ? 'You must be logged in to create reviews.'
                : 'Too many review submissions. Please wait before creating another review.',
            'reviews.vote' => $isGuest
                ? 'You must be logged in to vote on review helpfulness.'
                : 'Too many helpfulness votes. Please slow down.',
            'reviews.report' => $isGuest
                ? 'You must be logged in to report reviews.'
                : 'Too many reports submitted. Please wait before reporting again.',
        ];

        if (isset($specificMessages[$limitType])) {
            $message = $specificMessages[$limitType];
        }

        return response()->json([
            'message' => $message,
            'error' => 'rate_limit_exceeded',
            'retry_after' => $retryAfter,
            'limit' => $maxAttempts,
            'window' => $decayMinutes * 60,
            'is_guest' => $isGuest,
            'suggestion' => $isGuest ? 'Consider creating an account for higher rate limits.' : null,
        ], 429, [
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
            'Retry-After' => $retryAfter,
        ]);
    }

    protected function addHeaders($response, int $maxAttempts, int $remainingAttempts, int $retryAfter)
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
            'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->timestamp,
        ]);

        return $response;
    }
}

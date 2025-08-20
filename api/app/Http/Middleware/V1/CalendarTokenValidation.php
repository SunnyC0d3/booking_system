<?php

namespace App\Http\Middleware\V1;

use App\Constants\CalendarProviders;
use App\Models\CalendarIntegration;
use App\Services\V1\Calendar\CalendarAuthService;
use App\Traits\V1\ApiResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Exception;

class CalendarTokenValidation
{
    use ApiResponses;

    private CalendarAuthService $authService;

    public function __construct(CalendarAuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$parameters): Response
    {
        // Extract middleware parameters
        $requiresIntegration = in_array('require_integration', $parameters);
        $allowExpired = in_array('allow_expired', $parameters);
        $autoRefresh = !in_array('no_auto_refresh', $parameters);
        $strictValidation = in_array('strict', $parameters);

        try {
            // Check if user is authenticated
            $user = $request->user();
            if (!$user) {
                return $this->error('Authentication required for calendar operations', 401);
            }

            // Get calendar integration from route or request
            $integration = $this->getCalendarIntegration($request);

            if ($requiresIntegration && !$integration) {
                return $this->error('Calendar integration not found', 404);
            }

            if ($integration) {
                // Validate ownership unless user has admin permissions
                if (!$this->canAccessIntegration($user, $integration)) {
                    Log::warning('Unauthorized calendar integration access attempt', [
                        'user_id' => $user->id,
                        'integration_id' => $integration->id,
                        'integration_user_id' => $integration->user_id,
                        'route' => $request->route()?->getName(),
                        'ip' => $request->ip(),
                    ]);

                    return $this->error('You do not have permission to access this calendar integration', 403);
                }

                // Check integration status
                if (!$integration->is_active && $strictValidation) {
                    return $this->error('Calendar integration is disabled', 422, [
                        'error_code' => 'INTEGRATION_DISABLED',
                        'integration_id' => $integration->id,
                    ]);
                }

                // Validate tokens
                $tokenValidation = $this->validateTokens($integration, $allowExpired, $autoRefresh);

                if (!$tokenValidation['valid']) {
                    return $this->error($tokenValidation['message'], $tokenValidation['status_code'], [
                        'error_code' => $tokenValidation['error_code'],
                        'integration_id' => $integration->id,
                        'provider' => $integration->provider,
                        'can_reconnect' => $tokenValidation['can_reconnect'],
                        'reconnect_url' => $tokenValidation['reconnect_url'] ?? null,
                    ]);
                }

                // Check rate limits for calendar operations
                $rateLimitCheck = $this->checkRateLimits($request, $integration);
                if (!$rateLimitCheck['allowed']) {
                    return $this->error($rateLimitCheck['message'], 429, [
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                        'retry_after' => $rateLimitCheck['retry_after'],
                        'limit_type' => $rateLimitCheck['limit_type'],
                    ]);
                }

                // Check provider-specific requirements
                $providerValidation = $this->validateProviderRequirements($integration, $request);
                if (!$providerValidation['valid']) {
                    return $this->error($providerValidation['message'], $providerValidation['status_code'], [
                        'error_code' => $providerValidation['error_code'],
                        'provider' => $integration->provider,
                    ]);
                }

                // Add integration to request for downstream use
                $request->attributes->set('calendar_integration', $integration);
                $request->attributes->set('token_refreshed', $tokenValidation['token_refreshed'] ?? false);
            }

            // Add user calendar capabilities to request
            $request->attributes->set('calendar_capabilities', $this->getUserCalendarCapabilities($user));

            return $next($request);

        } catch (Exception $e) {
            Log::error('Calendar token validation middleware error', [
                'user_id' => $user?->id,
                'route' => $request->route()?->getName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Calendar validation failed', 500, [
                'error_code' => 'VALIDATION_ERROR',
            ]);
        }
    }

    /**
     * Get calendar integration from request
     */
    private function getCalendarIntegration(Request $request): ?CalendarIntegration
    {
        // Try to get from route parameters
        $integrationId = $request->route('integration')?->id ??
            $request->route('calendar_integration')?->id ??
            $request->input('calendar_integration_id');

        if ($integrationId) {
            return CalendarIntegration::find($integrationId);
        }

        // Try to get from request body
        if ($request->has('calendar_integration_id')) {
            return CalendarIntegration::find($request->input('calendar_integration_id'));
        }

        return null;
    }

    /**
     * Check if user can access calendar integration
     */
    private function canAccessIntegration($user, CalendarIntegration $integration): bool
    {
        // Owner can always access
        if ($integration->user_id === $user->id) {
            return true;
        }

        // Check admin permissions
        return $user->hasPermission('manage_all_calendar_integrations') ||
            $user->hasPermission('view_all_calendar_integrations');
    }

    /**
     * Validate calendar tokens
     */
    private function validateTokens(CalendarIntegration $integration, bool $allowExpired, bool $autoRefresh): array
    {
        // iCal doesn't use OAuth tokens
        if ($integration->provider === CalendarProviders::ICAL) {
            return $this->validateICalConnection($integration);
        }

        // Check if tokens exist
        if (empty($integration->access_token)) {
            return [
                'valid' => false,
                'message' => 'Calendar not connected. Please connect your calendar first.',
                'status_code' => 401,
                'error_code' => 'NO_ACCESS_TOKEN',
                'can_reconnect' => true,
                'reconnect_url' => $this->getReconnectUrl($integration),
            ];
        }

        // Check token expiration
        $tokenExpired = $integration->token_expires_at && $integration->token_expires_at->isPast();
        $tokenExpiresSoon = $integration->token_expires_at && $integration->token_expires_at->lt(now()->addMinutes(10));

        if ($tokenExpired && !$allowExpired) {
            // Try to refresh token if possible
            if ($autoRefresh && $this->canRefreshToken($integration)) {
                $refreshResult = $this->attemptTokenRefresh($integration);

                if ($refreshResult['success']) {
                    Log::info('Calendar token automatically refreshed', [
                        'integration_id' => $integration->id,
                        'provider' => $integration->provider,
                        'user_id' => $integration->user_id,
                    ]);

                    return [
                        'valid' => true,
                        'token_refreshed' => true,
                        'message' => 'Token refreshed successfully',
                    ];
                } else {
                    return [
                        'valid' => false,
                        'message' => 'Calendar access expired and refresh failed. Please reconnect your calendar.',
                        'status_code' => 401,
                        'error_code' => 'TOKEN_REFRESH_FAILED',
                        'can_reconnect' => true,
                        'reconnect_url' => $this->getReconnectUrl($integration),
                        'refresh_error' => $refreshResult['error'],
                    ];
                }
            }

            return [
                'valid' => false,
                'message' => 'Calendar access expired. Please reconnect your calendar.',
                'status_code' => 401,
                'error_code' => 'TOKEN_EXPIRED',
                'can_reconnect' => true,
                'reconnect_url' => $this->getReconnectUrl($integration),
            ];
        }

        // Warn about soon-to-expire tokens
        if ($tokenExpiresSoon && $autoRefresh && $this->canRefreshToken($integration)) {
            // Schedule background token refresh
            $this->scheduleTokenRefresh($integration);
        }

        // Validate token format and structure
        $tokenValidation = $this->validateTokenStructure($integration);
        if (!$tokenValidation['valid']) {
            return $tokenValidation;
        }

        // Test token with a lightweight API call
        if ($this->shouldTestToken($integration)) {
            $testResult = $this->testTokenWithProvider($integration);
            if (!$testResult['valid']) {
                return $testResult;
            }
        }

        return [
            'valid' => true,
            'message' => 'Token validation successful',
        ];
    }

    /**
     * Validate iCal connection
     */
    private function validateICalConnection(CalendarIntegration $integration): array
    {
        if (empty($integration->calendar_id)) {
            return [
                'valid' => false,
                'message' => 'iCal calendar URL not configured',
                'status_code' => 422,
                'error_code' => 'ICAL_URL_MISSING',
                'can_reconnect' => true,
            ];
        }

        // Validate URL format
        if (!filter_var($integration->calendar_id, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'message' => 'Invalid iCal calendar URL format',
                'status_code' => 422,
                'error_code' => 'ICAL_URL_INVALID',
                'can_reconnect' => true,
            ];
        }

        return [
            'valid' => true,
            'message' => 'iCal connection valid',
        ];
    }

    /**
     * Check if token can be refreshed
     */
    private function canRefreshToken(CalendarIntegration $integration): bool
    {
        return !empty($integration->refresh_token) &&
            $integration->provider !== CalendarProviders::ICAL;
    }

    /**
     * Attempt to refresh access token
     */
    private function attemptTokenRefresh(CalendarIntegration $integration): array
    {
        try {
            $result = $this->authService->refreshToken($integration);

            if ($result['success']) {
                // Reset error count on successful refresh
                $integration->update([
                    'sync_error_count' => 0,
                    'last_sync_error' => null,
                ]);

                return ['success' => true];
            }

            return [
                'success' => false,
                'error' => $result['error'] ?? 'Unknown refresh error',
            ];

        } catch (Exception $e) {
            Log::error('Token refresh failed', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Schedule background token refresh
     */
    private function scheduleTokenRefresh(CalendarIntegration $integration): void
    {
        // Dispatch a job to refresh the token in the background
        // This prevents blocking the current request
        try {
            \App\Jobs\Calendar\RefreshCalendarTokens::dispatch($integration)
                ->delay(now()->addMinutes(1))
                ->onQueue('calendar-maintenance');

            Log::info('Background token refresh scheduled', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
            ]);
        } catch (Exception $e) {
            Log::warning('Failed to schedule token refresh', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate token structure
     */
    private function validateTokenStructure(CalendarIntegration $integration): array
    {
        $token = $integration->access_token;

        // Basic token format validation
        if (strlen($token) < 10) {
            return [
                'valid' => false,
                'message' => 'Invalid token format',
                'status_code' => 401,
                'error_code' => 'INVALID_TOKEN_FORMAT',
                'can_reconnect' => true,
            ];
        }

        // Provider-specific token validation
        switch ($integration->provider) {
            case CalendarProviders::GOOGLE:
                // Google tokens are typically JWT or opaque strings
                if (!$this->isValidGoogleToken($token)) {
                    return [
                        'valid' => false,
                        'message' => 'Invalid Google Calendar token format',
                        'status_code' => 401,
                        'error_code' => 'INVALID_GOOGLE_TOKEN',
                        'can_reconnect' => true,
                    ];
                }
                break;

            case CalendarProviders::OUTLOOK:
                // Outlook tokens have specific format requirements
                if (!$this->isValidOutlookToken($token)) {
                    return [
                        'valid' => false,
                        'message' => 'Invalid Outlook Calendar token format',
                        'status_code' => 401,
                        'error_code' => 'INVALID_OUTLOOK_TOKEN',
                        'can_reconnect' => true,
                    ];
                }
                break;
        }

        return ['valid' => true];
    }

    /**
     * Check if should test token with API call
     */
    private function shouldTestToken(CalendarIntegration $integration): bool
    {
        // Test tokens that haven't been validated recently
        $lastValidation = $integration->updated_at;
        $validationInterval = $this->getTokenValidationInterval($integration);

        return $lastValidation->lt(now()->subMinutes($validationInterval));
    }

    /**
     * Test token with lightweight provider API call
     */
    private function testTokenWithProvider(CalendarIntegration $integration): array
    {
        try {
            $result = $this->authService->testToken($integration);

            if ($result['valid']) {
                return ['valid' => true];
            }

            return [
                'valid' => false,
                'message' => 'Calendar access test failed. Please reconnect your calendar.',
                'status_code' => 401,
                'error_code' => 'TOKEN_TEST_FAILED',
                'can_reconnect' => true,
                'reconnect_url' => $this->getReconnectUrl($integration),
                'test_error' => $result['error'] ?? null,
            ];

        } catch (Exception $e) {
            Log::warning('Token test failed', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'error' => $e->getMessage(),
            ]);

            // Don't fail validation for test errors, just log them
            return ['valid' => true];
        }
    }

    /**
     * Check rate limits for calendar operations
     */
    private function checkRateLimits(Request $request, CalendarIntegration $integration): array
    {
        $user = $request->user();

        // Different rate limits for different operations
        $operationType = $this->getOperationType($request);
        $rateLimitKey = "calendar_{$operationType}_user_{$user->id}_integration_{$integration->id}";

        $limits = $this->getRateLimits($operationType, $user, $integration);

        // Check if rate limit exceeded
        if (RateLimiter::tooManyAttempts($rateLimitKey, $limits['max_attempts'])) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            Log::warning('Calendar operation rate limit exceeded', [
                'user_id' => $user->id,
                'integration_id' => $integration->id,
                'operation_type' => $operationType,
                'attempts' => RateLimiter::attempts($rateLimitKey),
                'limit' => $limits['max_attempts'],
                'retry_after' => $retryAfter,
            ]);

            return [
                'allowed' => false,
                'message' => "Too many {$operationType} requests. Please wait {$retryAfter} seconds before trying again.",
                'retry_after' => $retryAfter,
                'limit_type' => $operationType,
            ];
        }

        // Increment rate limit counter
        RateLimiter::hit($rateLimitKey, $limits['decay_seconds']);

        return ['allowed' => true];
    }

    /**
     * Validate provider-specific requirements
     */
    private function validateProviderRequirements(CalendarIntegration $integration, Request $request): array
    {
        switch ($integration->provider) {
            case CalendarProviders::GOOGLE:
                return $this->validateGoogleRequirements($integration, $request);

            case CalendarProviders::OUTLOOK:
                return $this->validateOutlookRequirements($integration, $request);

            case CalendarProviders::APPLE:
                return $this->validateAppleRequirements($integration, $request);

            case CalendarProviders::ICAL:
                return $this->validateICalRequirements($integration, $request);

            default:
                return ['valid' => true];
        }
    }

    /**
     * Validate Google Calendar specific requirements
     */
    private function validateGoogleRequirements(CalendarIntegration $integration, Request $request): array
    {
        // Check if calendar ID is accessible
        if (empty($integration->calendar_id)) {
            return [
                'valid' => false,
                'message' => 'Google Calendar ID not configured',
                'status_code' => 422,
                'error_code' => 'GOOGLE_CALENDAR_ID_MISSING',
            ];
        }

        // Check for required scopes in token
        $requiredScopes = ['https://www.googleapis.com/auth/calendar'];
        $tokenScopes = $this->getTokenScopes($integration);

        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes)) {
                return [
                    'valid' => false,
                    'message' => 'Insufficient Google Calendar permissions. Please reconnect with full calendar access.',
                    'status_code' => 403,
                    'error_code' => 'INSUFFICIENT_GOOGLE_SCOPES',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate Outlook Calendar specific requirements
     */
    private function validateOutlookRequirements(CalendarIntegration $integration, Request $request): array
    {
        // Check for Graph API permissions
        $tokenScopes = $this->getTokenScopes($integration);
        $requiredScopes = ['https://graph.microsoft.com/calendars.readwrite'];

        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes)) {
                return [
                    'valid' => false,
                    'message' => 'Insufficient Outlook Calendar permissions. Please reconnect with calendar access.',
                    'status_code' => 403,
                    'error_code' => 'INSUFFICIENT_OUTLOOK_SCOPES',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate Apple Calendar specific requirements
     */
    private function validateAppleRequirements(CalendarIntegration $integration, Request $request): array
    {
        // Apple Calendar validation logic
        return ['valid' => true];
    }

    /**
     * Validate iCal specific requirements
     */
    private function validateICalRequirements(CalendarIntegration $integration, Request $request): array
    {
        // iCal is read-only, check if operation requires write access
        $operationType = $this->getOperationType($request);

        if (in_array($operationType, ['sync_bookings', 'create_event', 'update_event', 'delete_event'])) {
            return [
                'valid' => false,
                'message' => 'iCal calendars are read-only. Cannot perform write operations.',
                'status_code' => 405,
                'error_code' => 'ICAL_READ_ONLY',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get user calendar capabilities
     */
    private function getUserCalendarCapabilities($user): array
    {
        return [
            'can_connect_calendars' => $user->hasPermission('manage_calendar_integrations'),
            'can_sync_calendars' => $user->hasPermission('sync_calendar_integrations'),
            'can_force_sync' => $user->hasPermission('force_calendar_sync'),
            'can_view_all_integrations' => $user->hasPermission('view_all_calendar_integrations'),
            'can_manage_all_integrations' => $user->hasPermission('manage_all_calendar_integrations'),
            'max_integrations' => $this->getMaxIntegrationsForUser($user),
            'allowed_providers' => $this->getAllowedProvidersForUser($user),
        ];
    }

    /**
     * Helper methods
     */
    private function getReconnectUrl(CalendarIntegration $integration): string
    {
        return route('calendar.connect', ['provider' => $integration->provider]);
    }

    private function getOperationType(Request $request): string
    {
        $route = $request->route()?->getName();

        if (str_contains($route, 'sync')) {
            return 'sync';
        }

        if (str_contains($route, 'webhook')) {
            return 'webhook';
        }

        return 'general';
    }

    private function getRateLimits(string $operationType, $user, CalendarIntegration $integration): array
    {
        $baseLimits = [
            'sync' => ['max_attempts' => 10, 'decay_seconds' => 3600], // 10 per hour
            'webhook' => ['max_attempts' => 100, 'decay_seconds' => 3600], // 100 per hour
            'general' => ['max_attempts' => 60, 'decay_seconds' => 3600], // 60 per hour
        ];

        $limits = $baseLimits[$operationType] ?? $baseLimits['general'];

        // Adjust based on user role
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            $limits['max_attempts'] *= 3;
        } elseif ($user->hasRole('vendor')) {
            $limits['max_attempts'] *= 2;
        }

        return $limits;
    }

    private function getTokenValidationInterval(CalendarIntegration $integration): int
    {
        // Different validation intervals based on provider and error history
        $baseInterval = 60; // 1 hour default

        if ($integration->sync_error_count > 0) {
            $baseInterval = 30; // Validate more frequently if there have been errors
        }

        return $baseInterval;
    }

    private function isValidGoogleToken(string $token): bool
    {
        // Basic Google token format validation
        return strlen($token) > 20 && (str_contains($token, '.') || strlen($token) > 50);
    }

    private function isValidOutlookToken(string $token): bool
    {
        // Basic Outlook token format validation
        return strlen($token) > 20;
    }

    private function getTokenScopes(CalendarIntegration $integration): array
    {
        // Extract scopes from token or integration settings
        $settings = $integration->sync_settings_display ?? [];
        return $settings['granted_scopes'] ?? [];
    }

    private function getMaxIntegrationsForUser($user): int
    {
        if ($user->hasRole('super_admin') || $user->hasRole('admin')) {
            return 10;
        }

        if ($user->hasRole('vendor')) {
            return 5;
        }

        return 3;
    }

    private function getAllowedProvidersForUser($user): array
    {
        // All users can use all providers by default
        return CalendarProviders::ALL;
    }
}

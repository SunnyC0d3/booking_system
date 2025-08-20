<?php

namespace App\Services\V1\Calendar;

use App\Constants\CalendarProviders;
use App\Models\User;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalendarAuthService
{
    use ApiResponses;

    private GoogleCalendarService $googleService;
    private ICalService $icalService;

    public function __construct(
        GoogleCalendarService $googleService,
        ICalService $icalService
    ) {
        $this->googleService = $googleService;
        $this->icalService = $icalService;
    }

    /**
     * Initiate OAuth flow for calendar provider
     */
    public function initiateOAuth(string $provider, User $user, ?int $serviceId = null): array
    {
        try {
            // Verify user has permission to create calendar integrations
            $currentUser = auth()->user();
            if ($currentUser && $currentUser->id !== $user->id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
                Log::warning('Unauthorized calendar OAuth initiation attempt', [
                    'user_id' => $currentUser->id,
                    'target_user_id' => $user->id,
                ]);
                throw new Exception('You can only create calendar integrations for yourself');
            }

            if (!$this->isProviderSupported($provider)) {
                throw new Exception('Unsupported calendar provider: ' . $provider);
            }

            // Generate secure state parameter
            $state = $this->generateSecureState($user->id, $serviceId, $provider);

            // Store state information in cache
            $this->storeOAuthState($state, [
                'user_id' => $user->id,
                'service_id' => $serviceId,
                'provider' => $provider,
                'initiated_at' => now()->toISOString(),
                'expires_at' => now()->addMinutes(10)->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Get authorization URL from provider service
            $authUrl = $this->getProviderAuthUrl($provider, $state);

            Log::info('Calendar OAuth flow initiated', [
                'provider' => $provider,
                'user_id' => $user->id,
                'service_id' => $serviceId,
                'state' => $state,
            ]);

            return [
                'authorization_url' => $authUrl,
                'state' => $state,
                'provider' => $provider,
                'expires_at' => now()->addMinutes(10)->toISOString(),
                'instructions' => $this->getProviderInstructions($provider),
            ];

        } catch (Exception $e) {
            Log::error('Failed to initiate calendar OAuth', [
                'provider' => $provider,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle OAuth callback from provider
     */
    public function handleOAuthCallback(Request $request): array
    {
        try {
            $code = $request->input('code');
            $state = $request->input('state');
            $error = $request->input('error');

            // Handle OAuth errors
            if ($error) {
                $this->logOAuthError($error, $request);
                throw new Exception($this->getOAuthErrorMessage($error));
            }

            // Validate required parameters
            if (!$code || !$state) {
                throw new Exception('Missing required OAuth parameters');
            }

            // Validate and retrieve state data
            $stateData = $this->validateOAuthState($state, $request);

            // Exchange code for tokens
            $tokenData = $this->exchangeCodeForTokens($stateData['provider'], $code);

            // Get calendar information
            $calendarInfo = $this->getCalendarInfo($stateData['provider'], $tokenData['access_token']);

            // Prepare integration data
            $integrationData = [
                'user_id' => $stateData['user_id'],
                'service_id' => $stateData['service_id'],
                'provider' => $stateData['provider'],
                'calendar_id' => $calendarInfo['id'],
                'calendar_name' => $calendarInfo['name'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenData['expires_in'])
                    ? now()->addSeconds($tokenData['expires_in'])
                    : null,
            ];

            Log::info('Calendar OAuth callback processed successfully', [
                'provider' => $stateData['provider'],
                'user_id' => $stateData['user_id'],
                'calendar_name' => $calendarInfo['name'],
            ]);

            return [
                'success' => true,
                'integration_data' => $integrationData,
                'calendar_info' => $calendarInfo,
                'provider' => $stateData['provider'],
            ];

        } catch (Exception $e) {
            Log::error('OAuth callback processing failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['state', 'error', 'error_description']),
            ]);

            throw $e;
        }
    }

    /**
     * Revoke calendar access and cleanup tokens
     */
    public function revokeAccess(string $provider, string $accessToken): bool
    {
        try {
            $success = match ($provider) {
                CalendarProviders::GOOGLE => $this->revokeGoogleAccess($accessToken),
                CalendarProviders::ICAL => true, // iCal doesn't need token revocation
                default => false
            };

            Log::info('Calendar access revoked', [
                'provider' => $provider,
                'success' => $success,
            ]);

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to revoke calendar access', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate OAuth state parameter
     */
    public function validateOAuthState(string $state, Request $request): array
    {
        // Retrieve state data from cache
        $stateData = Cache::get("calendar_oauth_state:{$state}");

        if (!$stateData) {
            throw new Exception('Invalid or expired OAuth state parameter');
        }

        // Remove state from cache to prevent reuse
        Cache::forget("calendar_oauth_state:{$state}");

        // Validate request origin (optional security check)
        $this->validateRequestOrigin($stateData, $request);

        // Check state expiration
        $expiresAt = Carbon::parse($stateData['expires_at']);
        if ($expiresAt->isPast()) {
            throw new Exception('OAuth state has expired');
        }

        return $stateData;
    }

    /**
     * Encrypt and store sensitive token data
     */
    public function encryptTokenData(array $tokenData): array
    {
        try {
            $encrypted = [];

            if (isset($tokenData['access_token'])) {
                $encrypted['access_token'] = Crypt::encrypt($tokenData['access_token']);
            }

            if (isset($tokenData['refresh_token'])) {
                $encrypted['refresh_token'] = Crypt::encrypt($tokenData['refresh_token']);
            }

            // Copy non-sensitive data as-is
            foreach (['expires_in', 'token_type', 'scope'] as $key) {
                if (isset($tokenData[$key])) {
                    $encrypted[$key] = $tokenData[$key];
                }
            }

            return $encrypted;

        } catch (Exception $e) {
            Log::error('Failed to encrypt token data', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Token encryption failed');
        }
    }

    /**
     * Decrypt stored token data
     */
    public function decryptTokenData(array $encryptedData): array
    {
        try {
            $decrypted = [];

            if (isset($encryptedData['access_token'])) {
                $decrypted['access_token'] = Crypt::decrypt($encryptedData['access_token']);
            }

            if (isset($encryptedData['refresh_token'])) {
                $decrypted['refresh_token'] = Crypt::decrypt($encryptedData['refresh_token']);
            }

            // Copy non-encrypted data as-is
            foreach (['expires_in', 'token_type', 'scope'] as $key) {
                if (isset($encryptedData[$key])) {
                    $decrypted[$key] = $encryptedData[$key];
                }
            }

            return $decrypted;

        } catch (Exception $e) {
            Log::error('Failed to decrypt token data', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Token decryption failed');
        }
    }

    /**
     * Check if provider is supported
     */
    private function isProviderSupported(string $provider): bool
    {
        return in_array($provider, [
            CalendarProviders::GOOGLE,
            CalendarProviders::ICAL,
        ]);
    }

    /**
     * Generate secure state parameter
     */
    private function generateSecureState(int $userId, ?int $serviceId, string $provider): string
    {
        $data = [
            'user_id' => $userId,
            'service_id' => $serviceId,
            'provider' => $provider,
            'timestamp' => time(),
            'random' => Str::random(32),
            'checksum' => hash('sha256', $userId . $serviceId . $provider . config('app.key')),
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * Store OAuth state in cache
     */
    private function storeOAuthState(string $state, array $data): void
    {
        Cache::put("calendar_oauth_state:{$state}", $data, 600); // 10 minutes

        // Also store by user ID for cleanup
        $userStates = Cache::get("user_oauth_states:{$data['user_id']}", []);
        $userStates[] = $state;
        Cache::put("user_oauth_states:{$data['user_id']}", $userStates, 3600); // 1 hour
    }

    /**
     * Get authorization URL from provider service
     */
    private function getProviderAuthUrl(string $provider, string $state): string
    {
        return match ($provider) {
            CalendarProviders::GOOGLE => $this->googleService->getAuthUrl($state),
            CalendarProviders::ICAL => $this->icalService->getAuthUrl($state),
            default => throw new Exception('Unsupported provider: ' . $provider)
        };
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchangeCodeForTokens(string $provider, string $code): array
    {
        return match ($provider) {
            CalendarProviders::GOOGLE => $this->googleService->exchangeCodeForTokens($code),
            CalendarProviders::ICAL => $this->icalService->exchangeCodeForTokens($code),
            default => throw new Exception('Unsupported provider: ' . $provider)
        };
    }

    /**
     * Get calendar information from provider
     */
    private function getCalendarInfo(string $provider, string $accessToken): array
    {
        return match ($provider) {
            CalendarProviders::GOOGLE => $this->googleService->getCalendarInfo($accessToken),
            CalendarProviders::ICAL => $this->icalService->getCalendarInfo($accessToken),
            default => throw new Exception('Unsupported provider: ' . $provider)
        };
    }

    /**
     * Validate request origin for security
     */
    private function validateRequestOrigin(array $stateData, Request $request): void
    {
        // Optional: Add IP address validation for extra security
        if (config('calendar.strict_ip_validation', false)) {
            $originalIp = $stateData['ip_address'] ?? null;
            $currentIp = $request->ip();

            if ($originalIp && $originalIp !== $currentIp) {
                Log::warning('OAuth callback from different IP address', [
                    'original_ip' => $originalIp,
                    'current_ip' => $currentIp,
                ]);
                // Note: Uncomment below to enforce strict IP validation
                // throw new Exception('OAuth callback from unauthorized IP address');
            }
        }
    }

    /**
     * Log OAuth errors for monitoring
     */
    private function logOAuthError(string $error, Request $request): void
    {
        Log::warning('OAuth error received', [
            'error' => $error,
            'error_description' => $request->input('error_description'),
            'error_uri' => $request->input('error_uri'),
            'state' => $request->input('state'),
        ]);
    }

    /**
     * Get user-friendly OAuth error message
     */
    private function getOAuthErrorMessage(string $error): string
    {
        return match ($error) {
            'access_denied' => 'Calendar access was denied. Please try again and grant the necessary permissions.',
            'invalid_request' => 'Invalid authorization request. Please try connecting your calendar again.',
            'invalid_client' => 'Calendar integration is not properly configured. Please contact support.',
            'invalid_grant' => 'Authorization expired. Please try connecting your calendar again.',
            'unsupported_response_type' => 'Calendar provider configuration error. Please contact support.',
            default => "Calendar authorization failed: {$error}. Please try again."
        };
    }

    /**
     * Get provider-specific instructions
     */
    private function getProviderInstructions(string $provider): array
    {
        return match ($provider) {
            CalendarProviders::GOOGLE => [
                'title' => 'Connect Google Calendar',
                'steps' => [
                    'You will be redirected to Google',
                    'Sign in to your Google account',
                    'Grant calendar access permissions',
                    'You will be redirected back to complete setup',
                ],
                'permissions' => [
                    'View your calendar events',
                    'Create new calendar events',
                    'Update existing events',
                    'Delete events created by this app',
                ],
            ],
            CalendarProviders::ICAL => [
                'title' => 'Connect iCal Calendar',
                'steps' => [
                    'You will be redirected to calendar setup',
                    'Enter your calendar URL or upload iCal file',
                    'Configure sync preferences',
                    'Complete integration setup',
                ],
                'permissions' => [
                    'Read calendar events from your URL',
                    'Generate calendar files for import',
                ],
            ],
            default => []
        };
    }

    /**
     * Revoke Google Calendar access
     */
    private function revokeGoogleAccess(string $accessToken): bool
    {
        try {
            $response = Http::post('https://oauth2.googleapis.com/revoke', [
                'token' => $accessToken,
            ]);

            return $response->successful();

        } catch (Exception $e) {
            Log::error('Failed to revoke Google access', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Cleanup expired OAuth states
     */
    public function cleanupExpiredStates(): int
    {
        $cleaned = 0;

        try {
            // This would need a more sophisticated implementation
            // For now, states automatically expire from cache

            Log::info('OAuth state cleanup completed', [
                'cleaned_count' => $cleaned,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to cleanup expired OAuth states', [
                'error' => $e->getMessage(),
            ]);
        }

        return $cleaned;
    }
}

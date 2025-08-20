<?php

namespace App\Services\V1\Calendar;

use App\Constants\CalendarProviders;
use App\Models\CalendarIntegration;
use App\Models\User;
use App\Models\Service;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class CalendarIntegrationService
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
     * Get authorization URL for calendar provider
     */
    public function getAuthorizationUrl(string $provider, int $userId, ?int $serviceId = null): array
    {
        try {
            $state = $this->generateSecureState($userId, $serviceId);

            $authUrl = match ($provider) {
                CalendarProviders::GOOGLE => $this->googleService->getAuthUrl($state),
                CalendarProviders::ICAL => $this->icalService->getAuthUrl($state),
                default => throw new Exception('Unsupported calendar provider: ' . $provider)
            };

            // Store state in cache for verification
            Cache::put("calendar_oauth_state:{$state}", [
                'user_id' => $userId,
                'service_id' => $serviceId,
                'provider' => $provider,
                'created_at' => now(),
            ], 600); // 10 minutes

            Log::info('Calendar authorization URL generated', [
                'provider' => $provider,
                'user_id' => $userId,
                'service_id' => $serviceId,
                'state' => $state,
            ]);

            return [
                'authorization_url' => $authUrl,
                'state' => $state,
                'provider' => $provider,
                'expires_at' => now()->addMinutes(10)->toISOString(),
            ];

        } catch (Exception $e) {
            Log::error('Failed to generate calendar authorization URL', [
                'provider' => $provider,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle OAuth callback and create integration
     */
    public function handleOAuthCallback(Request $request): array
    {
        try {
            $code = $request->input('code');
            $state = $request->input('state');
            $error = $request->input('error');

            if ($error) {
                throw new Exception('OAuth authorization failed: ' . $error);
            }

            if (!$code || !$state) {
                throw new Exception('Missing authorization code or state parameter');
            }

            // Verify state and get stored data
            $stateData = Cache::get("calendar_oauth_state:{$state}");
            if (!$stateData) {
                throw new Exception('Invalid or expired state parameter');
            }

            // Remove state from cache
            Cache::forget("calendar_oauth_state:{$state}");

            $provider = $stateData['provider'];
            $userId = $stateData['user_id'];
            $serviceId = $stateData['service_id'];

            // Exchange code for tokens
            $tokenData = match ($provider) {
                CalendarProviders::GOOGLE => $this->googleService->exchangeCodeForTokens($code),
                CalendarProviders::ICAL => $this->icalService->exchangeCodeForTokens($code),
                default => throw new Exception('Unsupported provider: ' . $provider)
            };

            // Get calendar information
            $calendarInfo = match ($provider) {
                CalendarProviders::GOOGLE => $this->googleService->getCalendarInfo($tokenData['access_token']),
                CalendarProviders::ICAL => $this->icalService->getCalendarInfo($tokenData['access_token']),
                default => throw new Exception('Unsupported provider: ' . $provider)
            };

            // Create calendar integration
            $integration = $this->createIntegration([
                'user_id' => $userId,
                'service_id' => $serviceId,
                'provider' => $provider,
                'calendar_id' => $calendarInfo['id'],
                'calendar_name' => $calendarInfo['name'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenData['expires_in'])
                    ? now()->addSeconds($tokenData['expires_in'])
                    : null,
            ]);

            Log::info('Calendar integration created successfully', [
                'integration_id' => $integration->id,
                'provider' => $provider,
                'user_id' => $userId,
                'calendar_name' => $calendarInfo['name'],
            ]);

            return [
                'integration' => $integration,
                'calendar_info' => $calendarInfo,
                'message' => 'Calendar integrated successfully',
            ];

        } catch (Exception $e) {
            Log::error('OAuth callback failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['state', 'error']),
            ]);

            throw $e;
        }
    }

    /**
     * Create calendar integration
     */
    public function createIntegration(array $data): CalendarIntegration
    {
        // Verify user ownership
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $data['user_id']) {
            throw new Exception('You can only create calendar integrations for yourself');
        }

        return DB::transaction(function () use ($data) {
            // Check for existing integration
            $existingIntegration = CalendarIntegration::where('user_id', $data['user_id'])
                ->where('service_id', $data['service_id'])
                ->where('provider', $data['provider'])
                ->where('calendar_id', $data['calendar_id'])
                ->first();

            if ($existingIntegration) {
                // Update existing integration with new tokens
                $existingIntegration->updateTokens(
                    $data['access_token'],
                    $data['refresh_token'] ?? null,
                    $data['token_expires_at'] ?? null
                );

                return $existingIntegration;
            }

            // Create new integration
            return CalendarIntegration::create([
                'user_id' => $data['user_id'],
                'service_id' => $data['service_id'],
                'provider' => $data['provider'],
                'calendar_id' => $data['calendar_id'],
                'calendar_name' => $data['calendar_name'],
                'access_token' => Crypt::encrypt($data['access_token']),
                'refresh_token' => $data['refresh_token'] ? Crypt::encrypt($data['refresh_token']) : null,
                'token_expires_at' => $data['token_expires_at'],
                'is_active' => true,
                'sync_bookings' => true,
                'sync_availability' => false,
                'auto_block_external_events' => false,
                'sync_settings' => $this->getDefaultSyncSettings($data['provider']),
            ]);
        });
    }

    /**
     * Refresh access tokens for integration
     */
    public function refreshTokens(CalendarIntegration $integration): bool
    {
        try {
            if (!$integration->refresh_token) {
                throw new Exception('No refresh token available for integration');
            }

            $refreshToken = Crypt::decrypt($integration->refresh_token);

            $tokenData = match ($integration->provider) {
                CalendarProviders::GOOGLE => $this->googleService->refreshTokens($refreshToken),
                CalendarProviders::ICAL => $this->icalService->refreshTokens($refreshToken),
                default => throw new Exception('Unsupported provider: ' . $integration->provider)
            };

            $expiresAt = isset($tokenData['expires_in'])
                ? now()->addSeconds($tokenData['expires_in'])
                : null;

            $integration->updateTokens(
                $tokenData['access_token'],
                $tokenData['refresh_token'] ?? $refreshToken, // Use new refresh token or keep existing
                $expiresAt
            );

            Log::info('Calendar tokens refreshed successfully', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'expires_at' => $expiresAt?->toISOString(),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to refresh calendar tokens', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'error' => $e->getMessage(),
            ]);

            // Mark integration as inactive if refresh fails
            $integration->update([
                'is_active' => false,
                'last_sync_error' => 'Token refresh failed: ' . $e->getMessage(),
                'sync_error_count' => $integration->sync_error_count + 1,
            ]);

            return false;
        }
    }

    /**
     * Get active integrations for user
     */
    public function getUserIntegrations(int $userId, ?int $serviceId = null): array
    {
        // Verify user access
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $userId && !$currentUser->hasPermission('view_all_calendar_integrations')) {
            throw new Exception('You can only view your own calendar integrations');
        }

        $query = CalendarIntegration::where('user_id', $userId)
            ->where('is_active', true);

        if ($serviceId) {
            $query->where(function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId)
                    ->orWhereNull('service_id');
            });
        }

        $integrations = $query->orderBy('created_at', 'desc')->get();

        return $integrations->map(function ($integration) {
            return [
                'id' => $integration->id,
                'provider' => $integration->provider,
                'calendar_name' => $integration->calendar_name,
                'sync_settings' => $integration->sync_settings_display,
                'last_sync_at' => $integration->last_sync_at?->toISOString(),
                'is_healthy' => $this->isIntegrationHealthy($integration),
                'next_sync_at' => $this->getNextSyncTime($integration)?->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Update integration settings
     */
    public function updateIntegrationSettings(CalendarIntegration $integration, array $settings): bool
    {
        // Verify user ownership
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
            throw new Exception('You can only update your own calendar integrations');
        }

        try {
            $allowedSettings = [
                'sync_bookings',
                'sync_availability',
                'auto_block_external_events',
                'sync_settings',
            ];

            $updateData = array_intersect_key($settings, array_flip($allowedSettings));

            if (isset($settings['sync_settings'])) {
                $integration->updateSyncSettings($settings['sync_settings']);
                unset($updateData['sync_settings']);
            }

            if (!empty($updateData)) {
                $integration->update($updateData);
            }

            Log::info('Calendar integration settings updated', [
                'integration_id' => $integration->id,
                'updated_settings' => array_keys($updateData),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to update integration settings', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete calendar integration
     */
    public function deleteIntegration(CalendarIntegration $integration): bool
    {
        // Verify user ownership
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->id !== $integration->user_id && !$currentUser->hasPermission('manage_all_calendar_integrations')) {
            throw new Exception('You can only delete your own calendar integrations');
        }

        try {
            DB::transaction(function () use ($integration) {
                // Delete related calendar events
                $integration->calendarEvents()->delete();

                // Delete sync jobs
                $integration->syncJobs()->delete();

                // Delete the integration
                $integration->delete();
            });

            Log::info('Calendar integration deleted', [
                'integration_id' => $integration->id,
                'provider' => $integration->provider,
                'user_id' => $integration->user_id,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to delete calendar integration', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check integration health
     */
    public function isIntegrationHealthy(CalendarIntegration $integration): bool
    {
        // Check if active
        if (!$integration->is_active) {
            return false;
        }

        // Check token expiry
        if ($integration->token_expires_at && $integration->token_expires_at->isPast()) {
            return false;
        }

        // Check error count
        if ($integration->sync_error_count > 5) {
            return false;
        }

        // Check last sync (should sync at least once per day)
        if ($integration->last_sync_at && $integration->last_sync_at->lt(now()->subDay())) {
            return false;
        }

        return true;
    }

    /**
     * Get next sync time for integration
     */
    public function getNextSyncTime(CalendarIntegration $integration): ?Carbon
    {
        if (!$integration->is_active) {
            return null;
        }

        $syncFrequency = $integration->sync_settings_display['sync_frequency'] ?? 30; // minutes
        $lastSync = $integration->last_sync_at ?? $integration->created_at;

        return $lastSync->addMinutes($syncFrequency);
    }

    /**
     * Process integrations needing token refresh
     */
    public function processTokenRefresh(): array
    {
        $results = ['refreshed' => 0, 'failed' => 0];

        $integrations = CalendarIntegration::getIntegrationsNeedingRefresh();

        foreach ($integrations as $integration) {
            if ($this->refreshTokens($integration)) {
                $results['refreshed']++;
            } else {
                $results['failed']++;
            }
        }

        Log::info('Token refresh batch completed', $results);

        return $results;
    }

    /**
     * Generate secure state parameter
     */
    private function generateSecureState(int $userId, ?int $serviceId): string
    {
        $data = [
            'user_id' => $userId,
            'service_id' => $serviceId,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16)),
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * Get default sync settings for provider
     */
    private function getDefaultSyncSettings(string $provider): array
    {
        return [
            'sync_frequency' => 30, // minutes
            'event_title_template' => '{service_name} - {client_name}',
            'include_client_name' => true,
            'include_location' => true,
            'include_notes' => false,
            'calendar_color' => '#4285F4',
            'reminder_minutes' => [15, 60], // 15 min and 1 hour before
            'max_events_per_sync' => 100,
            'sync_past_days' => 7,
            'sync_future_days' => 90,
        ];
    }
}

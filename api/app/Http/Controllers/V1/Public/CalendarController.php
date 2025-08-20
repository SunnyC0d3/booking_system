<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Requests\V1\CalendarOAuthRequest;
use App\Requests\V1\UpdateCalendarIntegrationRequest;
use App\Models\CalendarIntegration;
use App\Resources\V1\CalendarIntegrationResource;
use App\Services\V1\Calendar\CalendarIntegrationService;
use App\Services\V1\Calendar\CalendarAuthService;
use App\Services\V1\Calendar\CalendarSyncService;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    use ApiResponses;

    private CalendarIntegrationService $integrationService;
    private CalendarAuthService $authService;
    private CalendarSyncService $syncService;

    public function __construct(
        CalendarIntegrationService $integrationService,
        CalendarAuthService $authService,
        CalendarSyncService $syncService
    ) {
        $this->integrationService = $integrationService;
        $this->authService = $authService;
        $this->syncService = $syncService;
    }

    /**
     * Get user's calendar integrations
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_own_calendar_integrations')) {
                return $this->error('You do not have permission to view calendar integrations.', 403);
            }

            $serviceId = $request->input('service_id');
            $includeInactive = $request->boolean('include_inactive', false);

            $query = CalendarIntegration::where('user_id', $user->id);

            if ($serviceId) {
                $query->where(function ($q) use ($serviceId) {
                    $q->where('service_id', $serviceId)->orWhereNull('service_id');
                });
            }

            if (!$includeInactive) {
                $query->where('is_active', true);
            }

            $integrations = $query->with(['service'])
                ->orderBy('created_at', 'desc')
                ->get();

            return CalendarIntegrationResource::collection($integrations)->additional([
                'message' => 'Calendar integrations retrieved successfully',
                'status' => 200,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get calendar integrations', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Show specific calendar integration
     */
    public function show(Request $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            // Check user owns the integration
            if ($user->id !== $integration->user_id && !$user->hasPermission('view_all_calendar_integrations')) {
                return $this->error('You can only view your own calendar integrations.', 403);
            }

            $integration->load(['service']);

            return $this->ok(
                'Calendar integration retrieved successfully',
                new CalendarIntegrationResource($integration)
            );

        } catch (Exception $e) {
            Log::error('Failed to show calendar integration', [
                'integration_id' => $integration->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Initiate OAuth flow for calendar provider
     */
    public function initiateOAuth(CalendarOAuthRequest $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('create_calendar_integrations')) {
                return $this->error('You do not have permission to create calendar integrations.', 403);
            }

            $data = $request->validated();

            $result = $this->authService->initiateOAuth(
                $data['provider'],
                $user,
                $data['service_id'] ?? null
            );

            Log::info('OAuth flow initiated', [
                'user_id' => $user->id,
                'provider' => $data['provider'],
                'service_id' => $data['service_id'] ?? null,
            ]);

            return $this->ok('OAuth flow initiated successfully', $result);

        } catch (Exception $e) {
            Log::error('Failed to initiate OAuth flow', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Handle OAuth callback from calendar provider
     */
    public function handleOAuthCallback(Request $request)
    {
        try {
            // Process OAuth callback
            $result = $this->authService->handleOAuthCallback($request);

            if (!$result['success']) {
                return $this->error('OAuth callback failed', 422);
            }

            // Create calendar integration
            $integration = $this->integrationService->createIntegration($result['integration_data']);

            Log::info('Calendar integration created via OAuth', [
                'integration_id' => $integration->id,
                'provider' => $result['provider'],
                'user_id' => $result['integration_data']['user_id'],
            ]);

            return $this->ok(
                'Calendar connected successfully',
                [
                    'integration' => new CalendarIntegrationResource($integration),
                    'calendar_info' => $result['calendar_info'],
                ]
            );

        } catch (Exception $e) {
            Log::error('OAuth callback failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->only(['state', 'error']),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Update calendar integration settings
     */
    public function update(UpdateCalendarIntegrationRequest $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            // Check user owns the integration
            if ($user->id !== $integration->user_id && !$user->hasPermission('edit_all_calendar_integrations')) {
                return $this->error('You can only update your own calendar integrations.', 403);
            }

            $data = $request->validated();

            $success = $this->integrationService->updateIntegrationSettings($integration, $data);

            if (!$success) {
                return $this->error('Failed to update calendar integration settings', 422);
            }

            $integration->refresh();

            Log::info('Calendar integration updated', [
                'integration_id' => $integration->id,
                'user_id' => $user->id,
                'updated_fields' => array_keys($data),
            ]);

            return $this->ok(
                'Calendar integration updated successfully',
                new CalendarIntegrationResource($integration)
            );

        } catch (Exception $e) {
            Log::error('Failed to update calendar integration', [
                'integration_id' => $integration->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete calendar integration
     */
    public function destroy(Request $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            // Check user owns the integration
            if ($user->id !== $integration->user_id && !$user->hasPermission('delete_all_calendar_integrations')) {
                return $this->error('You can only delete your own calendar integrations.', 403);
            }

            if (!$user->hasPermission('delete_calendar_integrations')) {
                return $this->error('You do not have permission to delete calendar integrations.', 403);
            }

            $success = $this->integrationService->deleteIntegration($integration);

            if (!$success) {
                return $this->error('Failed to delete calendar integration', 422);
            }

            Log::info('Calendar integration deleted', [
                'integration_id' => $integration->id,
                'user_id' => $user->id,
                'provider' => $integration->provider,
            ]);

            return $this->ok('Calendar integration deleted successfully');

        } catch (Exception $e) {
            Log::error('Failed to delete calendar integration', [
                'integration_id' => $integration->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Trigger manual sync for integration
     */
    public function triggerSync(Request $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            // Check user owns the integration
            if ($user->id !== $integration->user_id && !$user->hasPermission('manage_all_calendar_integrations')) {
                return $this->error('You can only sync your own calendar integrations.', 403);
            }

            if (!$user->hasPermission('sync_calendar_integrations')) {
                return $this->error('You do not have permission to sync calendar integrations.', 403);
            }

            $result = $this->syncService->syncExternalEvents($integration);

            if (isset($result['error'])) {
                return $this->error('Sync failed: ' . $result['error'], 422);
            }

            Log::info('Manual calendar sync triggered', [
                'integration_id' => $integration->id,
                'user_id' => $user->id,
                'sync_results' => $result,
            ]);

            return $this->ok('Calendar sync completed successfully', $result);

        } catch (Exception $e) {
            Log::error('Failed to trigger calendar sync', [
                'integration_id' => $integration->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get calendar sync status
     */
    public function getSyncStatus(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_own_calendar_integrations')) {
                return $this->error('You do not have permission to view sync status.', 403);
            }

            $status = $this->syncService->getSyncStatus($user->id);

            if (isset($status['error'])) {
                return $this->error($status['error'], 403);
            }

            return $this->ok('Sync status retrieved successfully', [
                'integrations' => $status,
                'total_integrations' => count($status),
                'healthy_integrations' => count(array_filter($status, fn($s) => $s['is_healthy'])),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get sync status', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Check availability across user's calendars
     */
    public function checkAvailability(Request $request)
    {
        try {
            $request->validate([
                'service_id' => 'required|exists:services,id',
                'start_time' => 'required|date|after:now',
                'end_time' => 'required|date|after:start_time',
            ]);

            $user = $request->user();

            if (!$user->hasPermission('view_own_calendar_integrations')) {
                return $this->error('You do not have permission to check calendar availability.', 403);
            }

            $service = \App\Models\Service::findOrFail($request->input('service_id'));
            $startTime = \Carbon\Carbon::parse($request->input('start_time'));
            $endTime = \Carbon\Carbon::parse($request->input('end_time'));

            $availability = $this->syncService->checkAvailabilityAcrossCalendars(
                $user,
                $service,
                $startTime,
                $endTime
            );

            return $this->ok('Availability checked successfully', $availability);

        } catch (Exception $e) {
            Log::error('Failed to check calendar availability', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Refresh tokens for integration
     */
    public function refreshTokens(Request $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            // Check user owns the integration
            if ($user->id !== $integration->user_id && !$user->hasPermission('manage_all_calendar_integrations')) {
                return $this->error('You can only refresh tokens for your own calendar integrations.', 403);
            }

            $success = $this->integrationService->refreshTokens($integration);

            if (!$success) {
                return $this->error('Failed to refresh calendar tokens', 422);
            }

            $integration->refresh();

            Log::info('Calendar tokens refreshed', [
                'integration_id' => $integration->id,
                'user_id' => $user->id,
            ]);

            return $this->ok(
                'Calendar tokens refreshed successfully',
                new CalendarIntegrationResource($integration)
            );

        } catch (Exception $e) {
            Log::error('Failed to refresh calendar tokens', [
                'integration_id' => $integration->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}

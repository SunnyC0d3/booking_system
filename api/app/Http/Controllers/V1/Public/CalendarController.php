<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Jobs\Calendar\ProcessCalendarWebhook;
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

    /**
     * Handle Google Calendar webhook
     */
    public function handleGoogleWebhook(Request $request)
    {
        try {
            Log::info('Google Calendar webhook received', [
                'headers' => $request->headers->all(),
                'body_size' => strlen($request->getContent()),
            ]);

            // Extract Google-specific headers
            $resourceId = $request->header('X-Goog-Resource-ID');
            $resourceState = $request->header('X-Goog-Resource-State', 'exists');
            $channelId = $request->header('X-Goog-Channel-ID');

            if (!$resourceId) {
                Log::warning('Google webhook missing resource ID');
                return response()->json(['error' => 'Missing resource ID'], 400);
            }

            // Find integration by resource ID
            $integration = CalendarIntegration::where('provider', 'google')
                ->where(function ($query) use ($resourceId, $channelId) {
                    $query->whereJsonContains('sync_settings->resource_id', $resourceId)
                        ->orWhereJsonContains('sync_settings->channel_id', $channelId);
                })
                ->first();

            if (!$integration) {
                Log::warning('Google webhook integration not found', [
                    'resource_id' => $resourceId,
                    'channel_id' => $channelId,
                ]);
                return response()->json(['error' => 'Integration not found'], 404);
            }

            // Prepare webhook data for processing
            $webhookData = [
                'provider' => 'google',
                'headers' => $request->headers->all(),
                'body' => json_decode($request->getContent(), true) ?? [],
                'resource_state' => $resourceState,
                'resource_id' => $resourceId,
                'channel_id' => $channelId,
                'received_at' => now()->toISOString(),
            ];

            // Dispatch webhook processing job
            ProcessCalendarWebhook::dispatch(
                $integration,
                $webhookData,
                $request->header('X-Goog-Channel-Token'),
                ['priority' => $this->getWebhookPriority($resourceState)]
            );

            Log::info('Google webhook queued for processing', [
                'integration_id' => $integration->id,
                'resource_state' => $resourceState,
            ]);

            return response()->json(['status' => 'queued'], 200);

        } catch (\Exception $e) {
            Log::error('Google webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Outlook Calendar webhook
     */
    public function handleOutlookWebhook(Request $request)
    {
        try {
            Log::info('Outlook Calendar webhook received', [
                'headers' => $request->headers->all(),
                'body_size' => strlen($request->getContent()),
            ]);

            $body = json_decode($request->getContent(), true);

            if (!$body || !isset($body['value'])) {
                Log::warning('Outlook webhook invalid body structure');
                return response()->json(['error' => 'Invalid webhook body'], 400);
            }

            $clientState = $request->header('ClientState');

            if (!$clientState) {
                Log::warning('Outlook webhook missing client state');
                return response()->json(['error' => 'Missing client state'], 400);
            }

            // Find integration by client state
            $integration = CalendarIntegration::where('provider', 'outlook')
                ->whereJsonContains('sync_settings->client_state', $clientState)
                ->first();

            if (!$integration) {
                Log::warning('Outlook webhook integration not found', [
                    'client_state' => $clientState,
                ]);
                return response()->json(['error' => 'Integration not found'], 404);
            }

            // Prepare webhook data for processing
            $webhookData = [
                'provider' => 'outlook',
                'headers' => $request->headers->all(),
                'body' => $body,
                'client_state' => $clientState,
                'notifications' => $body['value'],
                'received_at' => now()->toISOString(),
            ];

            // Dispatch webhook processing job
            ProcessCalendarWebhook::dispatch(
                $integration,
                $webhookData,
                null, // Outlook doesn't use signature verification
                ['priority' => $this->getWebhookPriority('change')]
            );

            Log::info('Outlook webhook queued for processing', [
                'integration_id' => $integration->id,
                'notification_count' => count($body['value']),
            ]);

            return response()->json(['status' => 'queued'], 200);

        } catch (\Exception $e) {
            Log::error('Outlook webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Outlook subscription validation
     */
    public function validateOutlookSubscription(Request $request)
    {
        try {
            $validationToken = $request->query('validationToken');

            if (!$validationToken) {
                Log::warning('Outlook subscription validation missing token');
                return response()->json(['error' => 'Missing validation token'], 400);
            }

            Log::info('Outlook subscription validation received', [
                'validation_token' => substr($validationToken, 0, 10) . '...',
            ]);

            // Return the validation token as plain text (required by Outlook)
            return response($validationToken, 200)
                ->header('Content-Type', 'text/plain');

        } catch (\Exception $e) {
            Log::error('Outlook subscription validation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Validation failed'], 500);
        }
    }

    /**
     * Handle iCal webhook
     */
    public function handleICalWebhook(Request $request, CalendarIntegration $integration)
    {
        try {
            Log::info('iCal webhook received', [
                'integration_id' => $integration->id,
                'headers' => $request->headers->all(),
            ]);

            if ($integration->provider !== 'ical') {
                return response()->json(['error' => 'Invalid provider for iCal webhook'], 400);
            }

            if (!$integration->is_active) {
                Log::warning('iCal webhook for inactive integration', [
                    'integration_id' => $integration->id,
                ]);
                return response()->json(['error' => 'Integration not active'], 400);
            }

            // Prepare webhook data for processing
            $webhookData = [
                'provider' => 'ical',
                'headers' => $request->headers->all(),
                'body' => json_decode($request->getContent(), true) ?? [],
                'integration_id' => $integration->id,
                'trigger_type' => $request->input('trigger_type', 'update'),
                'received_at' => now()->toISOString(),
            ];

            // Dispatch webhook processing job
            ProcessCalendarWebhook::dispatch(
                $integration,
                $webhookData,
                null, // iCal doesn't use signature verification
                ['priority' => 'normal']
            );

            Log::info('iCal webhook queued for processing', [
                'integration_id' => $integration->id,
            ]);

            return response()->json(['status' => 'queued'], 200);

        } catch (\Exception $e) {
            Log::error('iCal webhook processing failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle Apple Calendar webhook (future support)
     */
    public function handleAppleWebhook(Request $request)
    {
        try {
            Log::info('Apple Calendar webhook received', [
                'headers' => $request->headers->all(),
            ]);

            // Apple Calendar webhook implementation would go here
            // Currently not implemented as Apple doesn't provide webhooks

            return response()->json([
                'error' => 'Apple Calendar webhooks not yet supported'
            ], 501);

        } catch (\Exception $e) {
            Log::error('Apple webhook processing failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Handle test webhook for development
     */
    public function handleTestWebhook(Request $request)
    {
        try {
            if (!app()->environment(['local', 'testing'])) {
                return response()->json(['error' => 'Test webhooks only available in local/testing'], 403);
            }

            Log::info('Test webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
            ]);

            return response()->json([
                'status' => 'test_webhook_received',
                'timestamp' => now()->toISOString(),
                'data_received' => $request->all(),
            ]);

        } catch (\Exception $e) {
            Log::error('Test webhook failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Test webhook failed'], 500);
        }
    }

    /**
     * Get webhook processing status
     */
    public function getWebhookStatus(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_own_calendar_integrations')) {
                return $this->error('You do not have permission to view webhook status.', 403);
            }

            // Get recent webhook processing stats
            $recentCutoff = now()->subHours(24);

            $integrations = CalendarIntegration::where('user_id', $user->id)
                ->with(['syncJobs' => function ($query) use ($recentCutoff) {
                    $query->where('created_at', '>=', $recentCutoff)
                        ->where('job_type', 'webhook_sync')
                        ->orderBy('created_at', 'desc');
                }])
                ->get();

            $status = [
                'webhook_health' => 'healthy',
                'total_integrations' => $integrations->count(),
                'active_integrations' => $integrations->where('is_active', true)->count(),
                'recent_webhooks' => 0,
                'successful_webhooks' => 0,
                'failed_webhooks' => 0,
                'integrations' => [],
            ];

            foreach ($integrations as $integration) {
                $recentJobs = $integration->syncJobs;
                $successful = $recentJobs->where('status', 'completed')->count();
                $failed = $recentJobs->where('status', 'failed')->count();

                $status['recent_webhooks'] += $recentJobs->count();
                $status['successful_webhooks'] += $successful;
                $status['failed_webhooks'] += $failed;

                $status['integrations'][] = [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'is_active' => $integration->is_active,
                    'recent_webhooks' => $recentJobs->count(),
                    'successful_webhooks' => $successful,
                    'failed_webhooks' => $failed,
                    'last_webhook' => $recentJobs->first()?->created_at,
                    'health' => $failed > 0 ? 'degraded' : 'healthy',
                ];
            }

            // Determine overall health
            if ($status['failed_webhooks'] > 0) {
                $status['webhook_health'] = 'degraded';
            }

            return $this->ok('Webhook status retrieved successfully', $status);

        } catch (\Exception $e) {
            Log::error('Failed to get webhook status', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Helper: Get webhook priority based on resource state
     */
    private function getWebhookPriority(string $resourceState): string
    {
        return match ($resourceState) {
            'sync', 'exists' => 'normal',
            'not_exists' => 'low',
            default => 'normal'
        };
    }
}

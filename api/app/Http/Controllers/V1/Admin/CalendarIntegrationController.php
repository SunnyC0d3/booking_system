<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Requests\V1\StoreCalendarIntegrationRequest;
use App\Requests\V1\UpdateCalendarIntegrationRequest;
use App\Models\CalendarIntegration;
use App\Models\User;
use App\Models\Service;
use App\Resources\V1\CalendarIntegrationResource;
use App\Services\V1\Calendar\CalendarIntegrationService;
use App\Services\V1\Calendar\CalendarSyncService;
use App\Services\V1\Calendar\CalendarEventService;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalendarIntegrationController extends Controller
{
    use ApiResponses;

    private CalendarIntegrationService $integrationService;
    private CalendarSyncService $syncService;
    private CalendarEventService $eventService;

    public function __construct(
        CalendarIntegrationService $integrationService,
        CalendarSyncService $syncService,
        CalendarEventService $eventService
    ) {
        $this->integrationService = $integrationService;
        $this->syncService = $syncService;
        $this->eventService = $eventService;
    }

    /**
     * Get all calendar integrations (Admin)
     *
     * @group Admin - Calendar Management
     * @authenticated
     *
     * @queryParam user_id integer optional Filter by user ID. Example: 1
     * @queryParam service_id integer optional Filter by service ID. Example: 1
     * @queryParam provider string optional Filter by provider (google, ical). Example: google
     * @queryParam is_active boolean optional Filter by active status. Example: true
     * @queryParam has_errors boolean optional Filter integrations with sync errors. Example: false
     * @queryParam per_page integer optional Items per page (max 100). Example: 15
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_all_calendar_integrations')) {
                return $this->error('You do not have permission to view all calendar integrations.', 403);
            }

            $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'service_id' => 'nullable|exists:services,id',
                'provider' => 'nullable|in:google,ical',
                'is_active' => 'nullable|boolean',
                'has_errors' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = CalendarIntegration::with(['user', 'service']);

            // Apply filters
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->filled('service_id')) {
                $query->where('service_id', $request->input('service_id'));
            }

            if ($request->filled('provider')) {
                $query->where('provider', $request->input('provider'));
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('has_errors')) {
                $hasErrors = $request->boolean('has_errors');
                if ($hasErrors) {
                    $query->where('sync_error_count', '>', 0);
                } else {
                    $query->where('sync_error_count', 0);
                }
            }

            $perPage = $request->input('per_page', 15);
            $integrations = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return CalendarIntegrationResource::collection($integrations)->additional([
                'message' => 'Calendar integrations retrieved successfully',
                'status' => 200,
                'stats' => $this->getSystemStats(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get admin calendar integrations', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Show specific calendar integration (Admin)
     *
     * @group Admin - Calendar Management
     * @authenticated
     *
     * @urlParam integration integer required The calendar integration ID. Example: 1
     */
    public function show(Request $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_all_calendar_integrations')) {
                return $this->error('You do not have permission to view calendar integrations.', 403);
            }

            $integration->load(['user', 'service', 'calendarEvents' => function ($query) {
                $query->orderBy('starts_at', 'desc')->limit(10);
            }]);

            // Get additional admin data
            $adminData = [
                'sync_history' => $this->getSyncHistory($integration),
                'error_logs' => $this->getErrorLogs($integration),
                'usage_stats' => $this->getUsageStats($integration),
                'health_check' => $this->getHealthCheck($integration),
            ];

            return $this->ok(
                'Calendar integration retrieved successfully',
                [
                    'integration' => new CalendarIntegrationResource($integration),
                    'admin_data' => $adminData,
                ]
            );

        } catch (Exception $e) {
            Log::error('Failed to show admin calendar integration', [
                'integration_id' => $integration->id,
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create calendar integration for user (Admin)
     *
     * @group Admin - Calendar Management
     * @authenticated
     *
     * @bodyParam user_id integer required User ID to create integration for. Example: 1
     * @bodyParam provider string required Calendar provider. Example: google
     * @bodyParam calendar_id string required External calendar ID. Example: primary
     * @bodyParam calendar_name string required Calendar display name. Example: "Main Calendar"
     * @bodyParam service_id integer optional Service ID to associate. Example: 1
     */
    public function store(StoreCalendarIntegrationRequest $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('create_all_calendar_integrations')) {
                return $this->error('You do not have permission to create calendar integrations for users.', 403);
            }

            $data = $request->validated();

            // Verify target user exists
            $targetUser = User::findOrFail($data['user_id']);

            // Admin can create integrations without OAuth flow
            $integrationData = [
                'user_id' => $data['user_id'],
                'service_id' => $data['service_id'] ?? null,
                'provider' => $data['provider'],
                'calendar_id' => $data['calendar_id'],
                'calendar_name' => $data['calendar_name'],
                'access_token' => $data['access_token'] ?? 'admin_created',
                'refresh_token' => $data['refresh_token'] ?? null,
                'token_expires_at' => $data['token_expires_at'] ?? null,
            ];

            $integration = $this->integrationService->createIntegration($integrationData);

            Log::info('Admin created calendar integration', [
                'integration_id' => $integration->id,
                'admin_id' => $user->id,
                'user_id' => $data['user_id'],
                'provider' => $data['provider'],
            ]);

            return $this->ok(
                'Calendar integration created successfully',
                new CalendarIntegrationResource($integration->load(['user', 'service']))
            );

        } catch (Exception $e) {
            Log::error('Failed to create admin calendar integration', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Update calendar integration (Admin)
     *
     * @group Admin - Calendar Management
     * @authenticated
     *
     * @urlParam integration integer required The calendar integration ID. Example: 1
     */
    public function update(UpdateCalendarIntegrationRequest $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('edit_all_calendar_integrations')) {
                return $this->error('You do not have permission to edit calendar integrations.', 403);
            }

            $data = $request->validated();

            $success = $this->integrationService->updateIntegrationSettings($integration, $data);

            if (!$success) {
                return $this->error('Failed to update calendar integration', 422);
            }

            $integration->refresh();

            Log::info('Admin updated calendar integration', [
                'integration_id' => $integration->id,
                'admin_id' => $user->id,
                'updated_fields' => array_keys($data),
            ]);

            return $this->ok(
                'Calendar integration updated successfully',
                new CalendarIntegrationResource($integration->load(['user', 'service']))
            );

        } catch (Exception $e) {
            Log::error('Failed to update admin calendar integration', [
                'integration_id' => $integration->id,
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete calendar integration (Admin)
     *
     * @group Admin - Calendar Management
     * @authenticated
     *
     * @urlParam integration integer required The calendar integration ID. Example: 1
     */
    public function destroy(Request $request, CalendarIntegration $integration)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('delete_all_calendar_integrations')) {
                return $this->error('You do not have permission to delete calendar integrations.', 403);
            }

            $success = $this->integrationService->deleteIntegration($integration);

            if (!$success) {
                return $this->error('Failed to delete calendar integration', 422);
            }

            Log::info('Admin deleted calendar integration', [
                'integration_id' => $integration->id,
                'admin_id' => $user->id,
                'user_id' => $integration->user_id,
            ]);

            return $this->ok('Calendar integration deleted successfully');

        } catch (Exception $e) {
            Log::error('Failed to delete admin calendar integration', [
                'integration_id' => $integration->id,
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk operations on calendar integrations
     *
     * @group Admin - Calendar Management
     * @authenticated
     *
     * @bodyParam integration_ids array required Array of integration IDs. Example: [1,2,3]
     * @bodyParam action string required Action to perform (activate, deactivate, delete, sync). Example: activate
     */
    public function bulkAction(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('manage_all_calendar_integrations')) {
                return $this->error('You do not have permission to perform bulk calendar operations.', 403);
            }

            $request->validate([
                'integration_ids' => 'required|array|min:1|max:50',
                'integration_ids.*' => 'exists:calendar_integrations,id',
                'action' => 'required|in:activate,deactivate,delete,sync,refresh_tokens',
            ]);

            $integrationIds = $request->input('integration_ids');
            $action = $request->input('action');

            $results = ['success' => 0, 'failed' => 0, 'errors' => []];

            $integrations = CalendarIntegration::whereIn('id', $integrationIds)->get();

            foreach ($integrations as $integration) {
                try {
                    $success = match ($action) {
                        'activate' => $this->activateIntegration($integration),
                        'deactivate' => $this->deactivateIntegration($integration),
                        'delete' => $this->integrationService->deleteIntegration($integration),
                        'sync' => $this->syncIntegration($integration),
                        'refresh_tokens' => $this->integrationService->refreshTokens($integration),
                        default => false
                    };

                    if ($success) {
                        $results['success']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = "Failed to {$action} integration {$integration->id}";
                    }

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = "Error with integration {$integration->id}: " . $e->getMessage();
                }
            }

            Log::info('Admin bulk calendar action completed', [
                'admin_id' => $user->id,
                'action' => $action,
                'results' => $results,
            ]);

            return $this->ok("Bulk {$action} completed", $results);

        } catch (Exception $e) {
            Log::error('Failed to perform bulk calendar action', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get system-wide calendar statistics
     *
     * @group Admin - Calendar Management
     * @authenticated
     */
    public function getSystemStats(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_calendar_statistics')) {
                return $this->error('You do not have permission to view calendar statistics.', 403);
            }

            $stats = $this->getSystemStats();

            return $this->ok('Calendar statistics retrieved successfully', $stats);

        } catch (Exception $e) {
            Log::error('Failed to get calendar system stats', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Process scheduled syncs manually
     *
     * @group Admin - Calendar Management
     * @authenticated
     */
    public function processScheduledSyncs(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('manage_calendar_sync')) {
                return $this->error('You do not have permission to manage calendar sync.', 403);
            }

            $results = $this->syncService->processScheduledSyncs();

            Log::info('Admin triggered scheduled syncs', [
                'admin_id' => $user->id,
                'results' => $results,
            ]);

            return $this->ok('Scheduled syncs processed successfully', $results);

        } catch (Exception $e) {
            Log::error('Failed to process scheduled syncs', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get system health report
     *
     * @group Admin - Calendar Management
     * @authenticated
     */
    public function getHealthReport(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_calendar_health')) {
                return $this->error('You do not have permission to view calendar health.', 403);
            }

            $report = [
                'system_stats' => $this->getSystemStats(),
                'unhealthy_integrations' => $this->getUnhealthyIntegrations(),
                'sync_failures' => $this->getRecentSyncFailures(),
                'token_issues' => $this->getTokenIssues(),
                'recommendations' => $this->getHealthRecommendations(),
            ];

            return $this->ok('Calendar health report retrieved successfully', $report);

        } catch (Exception $e) {
            Log::error('Failed to get calendar health report', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Activate integration
     */
    private function activateIntegration(CalendarIntegration $integration): bool
    {
        $integration->update(['is_active' => true]);
        return true;
    }

    /**
     * Deactivate integration
     */
    private function deactivateIntegration(CalendarIntegration $integration): bool
    {
        $integration->update(['is_active' => false]);
        return true;
    }

    /**
     * Sync integration
     */
    private function syncIntegration(CalendarIntegration $integration): bool
    {
        $result = $this->syncService->syncExternalEvents($integration);
        return !isset($result['error']);
    }

    /**
     * Get sync history for integration
     */
    private function getSyncHistory(CalendarIntegration $integration): array
    {
        // Implementation would depend on sync job tracking
        return [
            'last_sync_at' => $integration->last_sync_at,
            'sync_error_count' => $integration->sync_error_count,
            'last_sync_error' => $integration->last_sync_error,
        ];
    }

    /**
     * Get error logs for integration
     */
    private function getErrorLogs(CalendarIntegration $integration): array
    {
        // Implementation would depend on logging structure
        return [];
    }

    /**
     * Get usage statistics for integration
     */
    private function getUsageStats(CalendarIntegration $integration): array
    {
        return [
            'events_synced' => $integration->calendarEvents()->count(),
            'bookings_synced' => $integration->calendarEvents()
                ->whereNotNull('external_event_id')
                ->count(),
        ];
    }

    /**
     * Get health check for integration
     */
    private function getHealthCheck(CalendarIntegration $integration): array
    {
        return [
            'is_healthy' => $this->integrationService->isIntegrationHealthy($integration),
            'token_valid' => $integration->token_expires_at ? $integration->token_expires_at->isFuture() : true,
            'recent_sync' => $integration->last_sync_at ? $integration->last_sync_at->isAfter(now()->subDay()) : false,
            'low_error_count' => $integration->sync_error_count < 3,
        ];
    }

    /**
     * Get unhealthy integrations
     */
    private function getUnhealthyIntegrations(): array
    {
        return CalendarIntegration::where('is_active', true)
            ->where(function ($query) {
                $query->where('sync_error_count', '>', 5)
                    ->orWhere('token_expires_at', '<', now())
                    ->orWhere('last_sync_at', '<', now()->subDays(2));
            })
            ->with(['user'])
            ->limit(10)
            ->get()
            ->map(function ($integration) {
                return [
                    'id' => $integration->id,
                    'user' => $integration->user->name,
                    'provider' => $integration->provider,
                    'error_count' => $integration->sync_error_count,
                    'last_sync' => $integration->last_sync_at,
                ];
            })
            ->toArray();
    }

    /**
     * Get recent sync failures
     */
    private function getRecentSyncFailures(): array
    {
        return CalendarIntegration::where('sync_error_count', '>', 0)
            ->whereNotNull('last_sync_error')
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get(['id', 'provider', 'last_sync_error', 'sync_error_count', 'updated_at'])
            ->toArray();
    }

    /**
     * Get token issues
     */
    private function getTokenIssues(): array
    {
        return [
            'expired_tokens' => CalendarIntegration::where('token_expires_at', '<', now())->count(),
            'expiring_soon' => CalendarIntegration::where('token_expires_at', '<', now()->addDays(7))->count(),
            'missing_refresh_tokens' => CalendarIntegration::where('provider', 'google')
                ->whereNull('refresh_token')
                ->count(),
        ];
    }

    /**
     * Get health recommendations
     */
    private function getHealthRecommendations(): array
    {
        $recommendations = [];

        $expiredTokens = CalendarIntegration::where('token_expires_at', '<', now())->count();
        if ($expiredTokens > 0) {
            $recommendations[] = "Refresh {$expiredTokens} expired tokens";
        }

        $highErrorCount = CalendarIntegration::where('sync_error_count', '>', 5)->count();
        if ($highErrorCount > 0) {
            $recommendations[] = "Investigate {$highErrorCount} integrations with high error counts";
        }

        $staleSyncs = CalendarIntegration::where('last_sync_at', '<', now()->subDays(2))->count();
        if ($staleSyncs > 0) {
            $recommendations[] = "Check {$staleSyncs} integrations with stale syncs";
        }

        return $recommendations;
    }
}

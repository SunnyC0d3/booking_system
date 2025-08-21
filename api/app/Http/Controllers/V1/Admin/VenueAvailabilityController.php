<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use App\Models\VenueAvailabilityWindow;
use App\Requests\V1\StoreVenueAvailabilityRequest;
use App\Requests\V1\UpdateVenueAvailabilityRequest;
use App\Resources\V1\VenueAvailabilityWindowResource;
use App\Services\V1\Venues\VenueAvailabilityService;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VenueAvailabilityController extends Controller
{
    use ApiResponses;

    private VenueAvailabilityService $availabilityService;

    public function __construct(VenueAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Display venue availability windows
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @queryParam window_type string optional Filter by window type (regular, special_event, maintenance, seasonal). Example: regular
     * @queryParam date_from date optional Filter from date. Example: 2024-01-01
     * @queryParam date_to date optional Filter to date. Example: 2024-12-31
     * @queryParam is_active boolean optional Filter by active status. Example: true
     */
    public function index(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_availability')) {
                return $this->error('You do not have permission to view venue availability.', 403);
            }

            $request->validate([
                'window_type' => 'nullable|in:regular,special_event,maintenance,seasonal',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'is_active' => 'boolean',
                'per_page' => 'integer|min:1|max:100',
            ]);

            $query = VenueAvailabilityWindow::where('service_location_id', $location->id);

            // Apply filters
            if ($request->has('window_type')) {
                $query->where('window_type', $request->input('window_type'));
            }

            if ($request->has('date_from')) {
                $dateFrom = $request->date('date_from');
                $query->where(function ($q) use ($dateFrom) {
                    $q->where('specific_date', '>=', $dateFrom)
                        ->orWhere('date_range_start', '>=', $dateFrom)
                        ->orWhereNull('specific_date'); // Include recurring windows
                });
            }

            if ($request->has('date_to')) {
                $dateTo = $request->date('date_to');
                $query->where(function ($q) use ($dateTo) {
                    $q->where('specific_date', '<=', $dateTo)
                        ->orWhere('date_range_end', '<=', $dateTo)
                        ->orWhereNull('specific_date'); // Include recurring windows
                });
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $perPage = $request->integer('per_page', 15);
            $windows = $query->orderBy('window_type')
                ->orderBy('day_of_week')
                ->orderBy('earliest_access')
                ->paginate($perPage);

            Log::info('Admin viewed venue availability windows', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'filters' => $request->only(['window_type', 'date_from', 'date_to', 'is_active']),
                'total_windows' => $windows->total(),
            ]);

            return VenueAvailabilityWindowResource::collection($windows)->additional([
                'message' => 'Venue availability windows retrieved successfully',
                'status' => 200,
                'location_info' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'type' => $location->type,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve venue availability windows', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Display specific venue availability window
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam window integer required The availability window ID. Example: 1
     */
    public function show(Request $request, ServiceLocation $location, VenueAvailabilityWindow $window)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_availability')) {
                return $this->error('You do not have permission to view venue availability.', 403);
            }

            // Verify the window belongs to the specified location
            if ($window->service_location_id !== $location->id) {
                return $this->error('Availability window does not belong to the specified location.', 400);
            }

            // Get conflicts and usage statistics
            $conflictInfo = $this->availabilityService->getWindowConflicts($window);
            $usageStats = $this->availabilityService->getWindowUsageStats($window);

            Log::info('Admin viewed venue availability window', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'window_id' => $window->id,
            ]);

            return $this->ok('Availability window retrieved successfully', [
                'window' => new VenueAvailabilityWindowResource($window),
                'conflicts' => $conflictInfo,
                'usage_stats' => $usageStats,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve venue availability window', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create new venue availability window
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function store(StoreVenueAvailabilityRequest $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('create_venue_availability')) {
                return $this->error('You do not have permission to create venue availability.', 403);
            }

            $data = $request->validated();
            $data['service_location_id'] = $location->id;

            return DB::transaction(function () use ($data, $location, $user) {
                // Check for overlapping windows
                $conflicts = $this->availabilityService->checkOverlappingWindows($location, $data);

                if (!empty($conflicts)) {
                    return $this->error('Availability window conflicts with existing windows', 409, [
                        'conflicts' => $conflicts,
                    ]);
                }

                // Create availability window
                $window = $this->availabilityService->createAvailabilityWindow($data);

                Log::info('Admin created venue availability window', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'window_id' => $window->id,
                    'window_type' => $window->window_type,
                ]);

                return $this->created(
                    'Venue availability window created successfully',
                    new VenueAvailabilityWindowResource($window)
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to create venue availability window', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Update venue availability window
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam window integer required The availability window ID. Example: 1
     */
    public function update(UpdateVenueAvailabilityRequest $request, ServiceLocation $location, VenueAvailabilityWindow $window)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('edit_venue_availability')) {
                return $this->error('You do not have permission to edit venue availability.', 403);
            }

            // Verify the window belongs to the specified location
            if ($window->service_location_id !== $location->id) {
                return $this->error('Availability window does not belong to the specified location.', 400);
            }

            $data = $request->validated();

            return DB::transaction(function () use ($window, $data, $location, $user) {
                // Check for overlapping windows (excluding current window)
                $conflicts = $this->availabilityService->checkOverlappingWindows($location, $data, $window->id);

                if (!empty($conflicts)) {
                    return $this->error('Updated availability window conflicts with existing windows', 409, [
                        'conflicts' => $conflicts,
                    ]);
                }

                // Check impact on existing bookings
                $bookingImpact = $this->availabilityService->assessBookingImpact($window, $data);

                if ($bookingImpact['has_conflicts']) {
                    Log::warning('Availability window update affects existing bookings', [
                        'admin_id' => $user->id,
                        'window_id' => $window->id,
                        'affected_bookings' => count($bookingImpact['affected_bookings']),
                    ]);

                    if (!$request->boolean('force_update', false)) {
                        return $this->error(
                            'Update affects existing bookings. Use force_update=true to proceed.',
                            409,
                            ['booking_impact' => $bookingImpact]
                        );
                    }
                }

                // Update availability window
                $updatedWindow = $this->availabilityService->updateAvailabilityWindow($window, $data);

                // Handle affected bookings if forced update
                if ($bookingImpact['has_conflicts'] && $request->boolean('force_update', false)) {
                    $this->availabilityService->handleAffectedBookings(
                        $bookingImpact['affected_bookings'],
                        $user
                    );
                }

                Log::info('Admin updated venue availability window', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'window_id' => $window->id,
                    'updated_fields' => array_keys($data),
                    'force_update' => $request->boolean('force_update', false),
                ]);

                return $this->ok(
                    'Venue availability window updated successfully',
                    [
                        'window' => new VenueAvailabilityWindowResource($updatedWindow),
                        'booking_impact' => $bookingImpact,
                    ]
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to update venue availability window', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete venue availability window
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam window integer required The availability window ID. Example: 1
     */
    public function destroy(Request $request, ServiceLocation $location, VenueAvailabilityWindow $window)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('delete_venue_availability')) {
                return $this->error('You do not have permission to delete venue availability.', 403);
            }

            // Verify the window belongs to the specified location
            if ($window->service_location_id !== $location->id) {
                return $this->error('Availability window does not belong to the specified location.', 400);
            }

            return DB::transaction(function () use ($window, $location, $user, $request) {
                // Check impact on existing bookings
                $bookingImpact = $this->availabilityService->assessDeletionImpact($window);

                if ($bookingImpact['has_conflicts']) {
                    Log::warning('Availability window deletion affects existing bookings', [
                        'admin_id' => $user->id,
                        'window_id' => $window->id,
                        'affected_bookings' => count($bookingImpact['affected_bookings']),
                    ]);

                    if (!$request->boolean('force_delete', false)) {
                        return $this->error(
                            'Deletion affects existing bookings. Use force_delete=true to proceed.',
                            409,
                            ['booking_impact' => $bookingImpact]
                        );
                    }
                }

                // Delete availability window
                $this->availabilityService->deleteAvailabilityWindow($window);

                // Handle affected bookings if forced deletion
                if ($bookingImpact['has_conflicts'] && $request->boolean('force_delete', false)) {
                    $this->availabilityService->handleAffectedBookings(
                        $bookingImpact['affected_bookings'],
                        $user
                    );
                }

                Log::info('Admin deleted venue availability window', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'window_id' => $window->id,
                    'window_type' => $window->window_type,
                    'force_delete' => $request->boolean('force_delete', false),
                ]);

                return $this->ok('Venue availability window deleted successfully', [
                    'booking_impact' => $bookingImpact,
                ]);
            });

        } catch (Exception $e) {
            Log::error('Failed to delete venue availability window', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'window_id' => $window->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Bulk update availability windows
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @bodyParam window_ids array required Array of window IDs. Example: [1,2,3]
     * @bodyParam action string required Action to perform (activate, deactivate, delete). Example: activate
     */
    public function bulkAction(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('edit_venue_availability')) {
                return $this->error('You do not have permission to perform bulk availability actions.', 403);
            }

            $request->validate([
                'window_ids' => 'required|array|min:1|max:50',
                'window_ids.*' => 'exists:venue_availability_windows,id',
                'action' => 'required|in:activate,deactivate,delete',
                'force_action' => 'boolean',
            ]);

            $windowIds = $request->input('window_ids');
            $action = $request->input('action');
            $forceAction = $request->boolean('force_action', false);

            return DB::transaction(function () use ($windowIds, $action, $forceAction, $location, $user) {
                $windows = VenueAvailabilityWindow::whereIn('id', $windowIds)
                    ->where('service_location_id', $location->id)
                    ->get();

                if ($windows->count() !== count($windowIds)) {
                    return $this->error('Some availability windows not found or do not belong to this location.', 400);
                }

                $results = $this->availabilityService->performBulkAction(
                    $windows,
                    $action,
                    $forceAction,
                    $user
                );

                Log::info('Admin performed bulk availability action', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'action' => $action,
                    'window_count' => count($windowIds),
                    'success_count' => $results['success_count'],
                    'error_count' => $results['error_count'],
                ]);

                return $this->ok("Bulk {$action} completed", $results);
            });

        } catch (Exception $e) {
            Log::error('Failed to perform bulk availability action', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get availability calendar view
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @queryParam month integer optional Month (1-12). Example: 3
     * @queryParam year integer optional Year. Example: 2024
     */
    public function getCalendarView(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_availability')) {
                return $this->error('You do not have permission to view venue availability.', 403);
            }

            $request->validate([
                'month' => 'integer|min:1|max:12',
                'year' => 'integer|min:2024|max:2030',
            ]);

            $month = $request->integer('month', now()->month);
            $year = $request->integer('year', now()->year);

            $calendar = $this->availabilityService->generateCalendarView(
                $location,
                $month,
                $year
            );

            Log::info('Admin viewed venue availability calendar', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'month' => $month,
                'year' => $year,
            ]);

            return $this->ok('Availability calendar retrieved successfully', $calendar);

        } catch (Exception $e) {
            Log::error('Failed to generate availability calendar', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

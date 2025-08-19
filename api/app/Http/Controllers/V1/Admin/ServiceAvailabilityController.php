<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\ServiceAvailabilityWindow;
use App\Resources\V1\ServiceAvailabilityWindowResource;
use App\Requests\V1\StoreServiceAvailabilityWindowRequest;
use App\Requests\V1\UpdateServiceAvailabilityWindowRequest;
use App\Services\V1\Bookings\TimeSlotService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class ServiceAvailabilityController extends Controller
{
    use ApiResponses;

    private TimeSlotService $timeSlotService;

    public function __construct(TimeSlotService $timeSlotService)
    {
        $this->timeSlotService = $timeSlotService;
    }

    /**
     * Get all availability windows for a service
     */
    public function index(Request $request, Service $service)
    {
        try {
            $request->validate([
                'location_id' => 'nullable|exists:service_locations,id',
                'type' => 'nullable|in:regular,exception,special_hours,blocked',
                'pattern' => 'nullable|in:weekly,daily,date_range,specific_date',
                'day_of_week' => 'nullable|integer|min:0|max:6',
                'status' => 'nullable|in:active,inactive,all',
                'sort' => 'nullable|in:day_asc,day_desc,time_asc,time_desc,created_asc,created_desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_availability')) {
                return $this->error('You do not have permission to view service availability.', 403);
            }

            // Check if user can view this service's availability
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view availability for your own services.', 403);
            }

            $query = $service->availabilityWindows()->with(['service', 'serviceLocation']);

            // Filter by location
            if ($request->location_id) {
                $query->where('service_location_id', $request->location_id);
            }

            // Filter by type
            if ($request->type) {
                $query->where('type', $request->type);
            }

            // Filter by pattern
            if ($request->pattern) {
                $query->where('pattern', $request->pattern);
            }

            // Filter by day of week
            if ($request->has('day_of_week')) {
                $query->where('day_of_week', $request->day_of_week);
            }

            // Filter by status
            switch ($request->status) {
                case 'active':
                    $query->where('is_active', true);
                    break;
                case 'inactive':
                    $query->where('is_active', false);
                    break;
                default:
                    // Show all
                    break;
            }

            // Sorting
            switch ($request->sort) {
                case 'day_asc':
                    $query->orderBy('day_of_week', 'asc');
                    break;
                case 'day_desc':
                    $query->orderBy('day_of_week', 'desc');
                    break;
                case 'time_asc':
                    $query->orderBy('start_time', 'asc');
                    break;
                case 'time_desc':
                    $query->orderBy('start_time', 'desc');
                    break;
                case 'created_asc':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'created_desc':
                    $query->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('day_of_week', 'asc')
                        ->orderBy('start_time', 'asc');
            }

            $perPage = $request->input('per_page', 20);
            $availabilityWindows = $query->paginate($perPage);

            return ServiceAvailabilityWindowResource::collection($availabilityWindows)->additional([
                'message' => 'Service availability windows retrieved successfully',
                'status' => 200,
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category' => $service->category,
                ],
                'statistics' => [
                    'total_windows' => $service->availabilityWindows()->count(),
                    'active_windows' => $service->availabilityWindows()->where('is_active', true)->count(),
                    'regular_windows' => $service->availabilityWindows()->where('type', 'regular')->count(),
                    'exception_windows' => $service->availabilityWindows()->where('type', 'exception')->count(),
                    'blocked_windows' => $service->availabilityWindows()->where('type', 'blocked')->count(),
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new availability window
     */
    public function store(StoreServiceAvailabilityWindowRequest $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_service_availability')) {
                return $this->error('You do not have permission to create service availability.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only create availability for your own services.', 403);
            }

            return DB::transaction(function () use ($request, $service, $user) {
                $data = $request->validated();
                $data['service_id'] = $service->id;

                // Validate location belongs to service if specified
                if (!empty($data['service_location_id'])) {
                    $location = $service->serviceLocations()->find($data['service_location_id']);
                    if (!$location) {
                        throw new Exception('Location does not belong to this service.', 422);
                    }
                }

                // Check for overlapping windows
                $this->validateNoOverlappingWindows($service, $data);

                $availabilityWindow = ServiceAvailabilityWindow::create($data);

                Log::info('Service availability window created', [
                    'window_id' => $availabilityWindow->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'type' => $availabilityWindow->type,
                    'pattern' => $availabilityWindow->pattern,
                    'day_of_week' => $availabilityWindow->day_of_week,
                    'start_time' => $availabilityWindow->start_time,
                    'end_time' => $availabilityWindow->end_time,
                    'created_by' => $user->id,
                ]);

                return $this->ok('Service availability window created successfully', [
                    'availability_window' => new ServiceAvailabilityWindowResource($availabilityWindow->load(['service', 'serviceLocation']))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get availability window details
     */
    public function show(Request $request, Service $service, ServiceAvailabilityWindow $serviceAvailabilityWindow)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_availability')) {
                return $this->error('You do not have permission to view service availability.', 403);
            }

            // Check if user can view this service's availability
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view availability for your own services.', 403);
            }

            // Ensure window belongs to this service
            if ($serviceAvailabilityWindow->service_id !== $service->id) {
                return $this->error('Availability window does not belong to this service.', 404);
            }

            $serviceAvailabilityWindow->load(['service', 'serviceLocation']);

            // Get usage statistics for this window
            $windowStats = $this->getWindowStatistics($serviceAvailabilityWindow);

            return $this->ok('Service availability window details retrieved', [
                'availability_window' => new ServiceAvailabilityWindowResource($serviceAvailabilityWindow),
                'statistics' => $windowStats,
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an availability window
     */
    public function update(UpdateServiceAvailabilityWindowRequest $request, Service $service, ServiceAvailabilityWindow $serviceAvailabilityWindow)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_availability')) {
                return $this->error('You do not have permission to edit service availability.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit availability for your own services.', 403);
            }

            // Ensure window belongs to this service
            if ($serviceAvailabilityWindow->service_id !== $service->id) {
                return $this->error('Availability window does not belong to this service.', 404);
            }

            return DB::transaction(function () use ($request, $serviceAvailabilityWindow, $user, $service) {
                $data = $request->validated();

                // Validate location belongs to service if specified
                if (!empty($data['service_location_id'])) {
                    $location = $service->serviceLocations()->find($data['service_location_id']);
                    if (!$location) {
                        throw new Exception('Location does not belong to this service.', 422);
                    }
                }

                // Check for overlapping windows (excluding current window)
                $this->validateNoOverlappingWindows($service, $data, $serviceAvailabilityWindow->id);

                $serviceAvailabilityWindow->update($data);

                Log::info('Service availability window updated', [
                    'window_id' => $serviceAvailabilityWindow->id,
                    'service_id' => $service->id,
                    'updated_by' => $user->id,
                    'updated_fields' => array_keys($data),
                ]);

                return $this->ok('Service availability window updated successfully', [
                    'availability_window' => new ServiceAvailabilityWindowResource($serviceAvailabilityWindow->load(['service', 'serviceLocation']))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete an availability window
     */
    public function destroy(Request $request, Service $service, ServiceAvailabilityWindow $serviceAvailabilityWindow)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('delete_service_availability')) {
                return $this->error('You do not have permission to delete service availability.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('delete_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only delete availability for your own services.', 403);
            }

            // Ensure window belongs to this service
            if ($serviceAvailabilityWindow->service_id !== $service->id) {
                return $this->error('Availability window does not belong to this service.', 404);
            }

            // Check if there are future bookings that depend on this window
            $futureBookingsCount = $this->getFutureBookingsForWindow($serviceAvailabilityWindow);
            if ($futureBookingsCount > 0) {
                return $this->error("Cannot delete availability window with {$futureBookingsCount} future bookings.", 422);
            }

            // Check if this would leave the service without any availability
            $otherActiveWindows = $service->availabilityWindows()
                ->where('id', '!=', $serviceAvailabilityWindow->id)
                ->where('is_active', true)
                ->count();

            if ($otherActiveWindows === 0) {
                return $this->error('Cannot delete the last active availability window for this service.', 422);
            }

            return DB::transaction(function () use ($serviceAvailabilityWindow, $user) {
                $windowId = $serviceAvailabilityWindow->id;
                $serviceId = $serviceAvailabilityWindow->service_id;

                $serviceAvailabilityWindow->delete();

                Log::info('Service availability window deleted', [
                    'window_id' => $windowId,
                    'service_id' => $serviceId,
                    'deleted_by' => $user->id,
                ]);

                return $this->ok('Service availability window deleted successfully');
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Toggle availability window status
     */
    public function toggleStatus(Request $request, Service $service, ServiceAvailabilityWindow $serviceAvailabilityWindow)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_availability')) {
                return $this->error('You do not have permission to edit service availability.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit availability for your own services.', 403);
            }

            // Ensure window belongs to this service
            if ($serviceAvailabilityWindow->service_id !== $service->id) {
                return $this->error('Availability window does not belong to this service.', 404);
            }

            // Check if this would be the last active window
            if ($serviceAvailabilityWindow->is_active) {
                $otherActiveWindows = $service->availabilityWindows()
                    ->where('id', '!=', $serviceAvailabilityWindow->id)
                    ->where('is_active', true)
                    ->count();

                if ($otherActiveWindows === 0) {
                    return $this->error('Cannot deactivate the last active availability window for this service.', 422);
                }
            }

            $newStatus = !$serviceAvailabilityWindow->is_active;
            $serviceAvailabilityWindow->update(['is_active' => $newStatus]);

            Log::info('Service availability window status toggled', [
                'window_id' => $serviceAvailabilityWindow->id,
                'service_id' => $service->id,
                'new_status' => $newStatus ? 'active' : 'inactive',
                'updated_by' => $user->id,
            ]);

            return $this->ok('Availability window status updated successfully', [
                'availability_window' => new ServiceAvailabilityWindowResource($serviceAvailabilityWindow),
                'new_status' => $newStatus ? 'active' : 'inactive',
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get available time slots for a date range
     */
    public function getAvailableSlots(Request $request, Service $service)
    {
        try {
            $request->validate([
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after:start_date',
                'location_id' => 'nullable|exists:service_locations,id',
                'duration_minutes' => 'nullable|integer|min:15|max:480',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_availability')) {
                return $this->error('You do not have permission to view service availability.', 403);
            }

            // Check if user can view this service's availability
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view availability for your own services.', 403);
            }

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $durationMinutes = $request->input('duration_minutes', $service->duration_minutes);

            // Validate date range (max 30 days)
            if ($startDate->diffInDays($endDate) > 30) {
                return $this->error('Date range cannot exceed 30 days.', 422);
            }

            $location = null;
            if ($request->location_id) {
                $location = $service->serviceLocations()->find($request->location_id);
                if (!$location) {
                    return $this->error('Location does not belong to this service.', 404);
                }
            }

            $availableSlots = $this->timeSlotService->getAvailableSlots(
                $service,
                $startDate,
                $endDate,
                $location,
                $durationMinutes
            );

            return $this->ok('Available slots retrieved successfully', [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                ],
                'search_criteria' => [
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'duration_minutes' => $durationMinutes,
                    'location_id' => $location?->id,
                    'location_name' => $location?->name,
                ],
                'available_slots' => $availableSlots->groupBy(function ($slot) {
                    return $slot['start_time']->toDateString();
                })->map(function ($daySlots) {
                    return $daySlots->map(function ($slot) {
                        return [
                            'start_time' => $slot['start_time']->format('H:i'),
                            'end_time' => $slot['end_time']->format('H:i'),
                            'start_datetime' => $slot['start_time']->toISOString(),
                            'end_datetime' => $slot['end_time']->toISOString(),
                            'duration_minutes' => $slot['duration_minutes'],
                            'is_available' => $slot['is_available'],
                            'price_modifier' => $slot['price_modifier'] ?? null,
                        ];
                    })->values();
                }),
                'total_slots' => $availableSlots->count(),
                'days_with_availability' => $availableSlots->groupBy(function ($slot) {
                    return $slot['start_time']->toDateString();
                })->count(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create availability from template
     */
    public function createFromTemplate(Request $request, Service $service)
    {
        try {
            $request->validate([
                'template_type' => 'required|in:business_hours,extended_hours,weekends_only,custom',
                'location_id' => 'nullable|exists:service_locations,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'custom_schedule' => 'required_if:template_type,custom|array',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_service_availability')) {
                return $this->error('You do not have permission to create service availability.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only create availability for your own services.', 403);
            }

            return DB::transaction(function () use ($request, $service, $user) {
                $templateType = $request->template_type;
                $locationId = $request->location_id;
                $createdWindows = [];

                $schedule = $this->getScheduleTemplate($templateType, $request->custom_schedule);

                foreach ($schedule as $windowData) {
                    $windowData['service_id'] = $service->id;
                    $windowData['service_location_id'] = $locationId;

                    // Skip if overlapping window exists
                    try {
                        $this->validateNoOverlappingWindows($service, $windowData);
                        $window = ServiceAvailabilityWindow::create($windowData);
                        $createdWindows[] = $window;
                    } catch (Exception $e) {
                        // Skip overlapping windows
                        continue;
                    }
                }

                Log::info('Service availability created from template', [
                    'service_id' => $service->id,
                    'template_type' => $templateType,
                    'windows_created' => count($createdWindows),
                    'created_by' => $user->id,
                ]);

                return $this->ok('Availability windows created from template', [
                    'template_type' => $templateType,
                    'windows_created' => count($createdWindows),
                    'availability_windows' => ServiceAvailabilityWindowResource::collection(
                        collect($createdWindows)->load(['service', 'serviceLocation'])
                    ),
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk update availability windows
     */
    public function bulkUpdate(Request $request, Service $service)
    {
        try {
            $request->validate([
                'window_ids' => 'required|array|min:1|max:50',
                'window_ids.*' => 'exists:service_availability_windows,id',
                'action' => 'required|in:activate,deactivate,delete,update_pricing',
                'price_modifier' => 'required_if:action,update_pricing|integer',
                'price_modifier_type' => 'required_if:action,update_pricing|in:fixed,percentage',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_availability')) {
                return $this->error('You do not have permission to edit service availability.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit availability for your own services.', 403);
            }

            $windowIds = $request->window_ids;
            $action = $request->action;
            $updated = 0;
            $errors = [];

            // Verify all windows belong to this service
            $windows = ServiceAvailabilityWindow::whereIn('id', $windowIds)
                ->where('service_id', $service->id)
                ->get();

            if ($windows->count() !== count($windowIds)) {
                return $this->error('Some availability windows do not belong to this service.', 422);
            }

            return DB::transaction(function () use ($windows, $action, $request, $user, $service, &$updated, &$errors) {
                foreach ($windows as $window) {
                    try {
                        switch ($action) {
                            case 'activate':
                                $window->update(['is_active' => true]);
                                break;
                            case 'deactivate':
                                // Check if this would be the last active window
                                $otherActiveWindows = $service->availabilityWindows()
                                    ->where('id', '!=', $window->id)
                                    ->where('is_active', true)
                                    ->count();

                                if ($otherActiveWindows === 0) {
                                    $errors[] = "Cannot deactivate window ID {$window->id} - would leave service without availability";
                                    continue 2;
                                }

                                $window->update(['is_active' => false]);
                                break;
                            case 'delete':
                                if (!$user->hasPermission('delete_service_availability')) {
                                    $errors[] = "No permission to delete window ID {$window->id}";
                                    continue 2;
                                }

                                $futureBookings = $this->getFutureBookingsForWindow($window);
                                if ($futureBookings > 0) {
                                    $errors[] = "Window ID {$window->id} has future bookings";
                                    continue 2;
                                }

                                $window->delete();
                                break;
                            case 'update_pricing':
                                $window->update([
                                    'price_modifier' => $request->price_modifier,
                                    'price_modifier_type' => $request->price_modifier_type,
                                ]);
                                break;
                        }

                        $updated++;

                    } catch (Exception $e) {
                        $errors[] = "Error updating window ID {$window->id}: " . $e->getMessage();
                    }
                }

                Log::info('Bulk availability window update completed', [
                    'service_id' => $service->id,
                    'action' => $action,
                    'requested_count' => count($windows),
                    'updated_count' => $updated,
                    'error_count' => count($errors),
                    'updated_by' => $user->id,
                ]);

                return $this->ok('Bulk update completed', [
                    'updated_count' => $updated,
                    'total_requested' => count($windows),
                    'errors' => $errors,
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Helper: Validate no overlapping windows
     */
    private function validateNoOverlappingWindows(Service $service, array $data, ?int $excludeWindowId = null): void
    {
        $query = $service->availabilityWindows()
            ->where('type', $data['type'] ?? 'regular')
            ->where('pattern', $data['pattern'] ?? 'weekly')
            ->where('is_active', true);

        if ($excludeWindowId) {
            $query->where('id', '!=', $excludeWindowId);
        }

        if (isset($data['service_location_id'])) {
            $query->where('service_location_id', $data['service_location_id']);
        } else {
            $query->whereNull('service_location_id');
        }

        if (isset($data['day_of_week'])) {
            $query->where('day_of_week', $data['day_of_week']);
        }

        // Check for time overlap
        $existingWindows = $query->get();

        foreach ($existingWindows as $existing) {
            if ($this->timesOverlap(
                $data['start_time'] ?? '09:00:00',
                $data['end_time'] ?? '17:00:00',
                $existing->start_time,
                $existing->end_time
            )) {
                throw new Exception('Availability window overlaps with existing window.', 422);
            }
        }
    }

    /**
     * Helper: Check if two time ranges overlap
     */
    private function timesOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        $start1 = Carbon::createFromFormat('H:i:s', $start1);
        $end1 = Carbon::createFromFormat('H:i:s', $end1);
        $start2 = Carbon::createFromFormat('H:i:s', $start2);
        $end2 = Carbon::createFromFormat('H:i:s', $end2);

        return $start1->lt($end2) && $end1->gt($start2);
    }

    /**
     * Helper: Get window statistics
     */
    private function getWindowStatistics(ServiceAvailabilityWindow $window): array
    {
        // This would calculate detailed statistics for the window
        // For now, return basic info
        return [
            'total_possible_slots' => 0,
            'booked_slots' => 0,
            'utilization_percentage' => 0,
            'revenue_generated' => 0,
        ];
    }

    /**
     * Helper: Get future bookings count for window
     */
    private function getFutureBookingsForWindow(ServiceAvailabilityWindow $window): int
    {
        // This would check for future bookings that use this window
        // For now, return 0
        return 0;
    }

    /**
     * Helper: Get schedule template
     */
    private function getScheduleTemplate(string $templateType, ?array $customSchedule = null): array
    {
        return match($templateType) {
            'business_hours' => [
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_bookings' => 4, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 2, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_bookings' => 4, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 3, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_bookings' => 4, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 4, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_bookings' => 4, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 5, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_bookings' => 4, 'is_active' => true, 'is_bookable' => true],
            ],
            'extended_hours' => [
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 1, 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'max_bookings' => 6, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 2, 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'max_bookings' => 6, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 3, 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'max_bookings' => 6, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 4, 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'max_bookings' => 6, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 5, 'start_time' => '08:00:00', 'end_time' => '20:00:00', 'max_bookings' => 6, 'is_active' => true, 'is_bookable' => true],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 6, 'start_time' => '10:00:00', 'end_time' => '16:00:00', 'max_bookings' => 3, 'is_active' => true, 'is_bookable' => true, 'price_modifier' => 1500, 'price_modifier_type' => 'fixed'],
            ],
            'weekends_only' => [
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 6, 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'max_bookings' => 4, 'is_active' => true, 'is_bookable' => true, 'price_modifier' => 2000, 'price_modifier_type' => 'fixed'],
                ['type' => 'regular', 'pattern' => 'weekly', 'day_of_week' => 0, 'start_time' => '10:00:00', 'end_time' => '16:00:00', 'max_bookings' => 3, 'is_active' => true, 'is_bookable' => true, 'price_modifier' => 2500, 'price_modifier_type' => 'fixed'],
            ],
            'custom' => $customSchedule ?? [],
        };
    }
}

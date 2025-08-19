<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Resources\V1\ServiceLocationResource;
use App\Requests\V1\StoreServiceLocationRequest;
use App\Requests\V1\UpdateServiceLocationRequest;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ServiceLocationController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
    }

    /**
     * Get all locations for a service
     */
    public function index(Request $request, Service $service)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:active,inactive,all',
                'type' => 'nullable|in:business_premises,client_location,virtual,outdoor',
                'search' => 'nullable|string|max:100',
                'sort' => 'nullable|in:name_asc,name_desc,type_asc,type_desc,created_asc,created_desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_locations')) {
                return $this->error('You do not have permission to view service locations.', 403);
            }

            // Check if user can view this service's locations
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view locations for your own services.', 403);
            }

            $query = $service->serviceLocations()->with(['availabilityWindows']);

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

            // Filter by type
            if ($request->type) {
                $query->where('type', $request->type);
            }

            // Search functionality
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('city', 'LIKE', "%{$search}%")
                        ->orWhere('postcode', 'LIKE', "%{$search}%");
                });
            }

            // Add booking statistics
            $query->withCount([
                'bookings as total_bookings_count',
                'bookings as active_bookings_count' => function ($q) {
                    $q->whereIn('status', ['pending', 'confirmed', 'in_progress']);
                },
            ]);

            // Sorting
            switch ($request->sort) {
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                case 'type_asc':
                    $query->orderBy('type', 'asc');
                    break;
                case 'type_desc':
                    $query->orderBy('type', 'desc');
                    break;
                case 'created_asc':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'created_desc':
                    $query->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('name', 'asc');
            }

            $perPage = $request->input('per_page', 20);
            $locations = $query->paginate($perPage);

            return ServiceLocationResource::collection($locations)->additional([
                'message' => 'Service locations retrieved successfully',
                'status' => 200,
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category' => $service->category,
                ],
                'statistics' => [
                    'total_locations' => $service->serviceLocations()->count(),
                    'active_locations' => $service->serviceLocations()->where('is_active', true)->count(),
                    'location_types' => $service->serviceLocations()
                        ->select('type', DB::raw('count(*) as count'))
                        ->groupBy('type')
                        ->pluck('count', 'type')
                        ->toArray(),
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new location for a service
     */
    public function store(StoreServiceLocationRequest $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_service_locations')) {
                return $this->error('You do not have permission to create service locations.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only create locations for your own services.', 403);
            }

            // Check business rule: max locations per service
            if (!$service->canAddMoreLocations()) {
                return $this->error('Maximum number of locations reached for this service.', 422);
            }

            return DB::transaction(function () use ($request, $service, $user) {
                $data = $request->validated();
                $data['service_id'] = $service->id;

                // Handle coordinates if address is provided
                if (!empty($data['address_line_1']) && empty($data['latitude'])) {
                    $coordinates = $this->geocodeAddress($data);
                    if ($coordinates) {
                        $data['latitude'] = $coordinates['lat'];
                        $data['longitude'] = $coordinates['lng'];
                    }
                }

                $location = ServiceLocation::create($data);

                // Create venue details if this is a detailed location type
                if (in_array($data['type'], ['business_premises', 'outdoor']) && $request->has('venue_details')) {
                    $this->createVenueDetails($location, $request->venue_details);
                }

                Log::info('Service location created', [
                    'location_id' => $location->id,
                    'location_name' => $location->name,
                    'location_type' => $location->type,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'created_by' => $user->id,
                ]);

                return $this->ok('Service location created successfully', [
                    'location' => new ServiceLocationResource($location->load(['service', 'availabilityWindows']))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get location details
     */
    public function show(Request $request, Service $service, ServiceLocation $serviceLocation)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_locations')) {
                return $this->error('You do not have permission to view service locations.', 403);
            }

            // Check if user can view this service's locations
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view locations for your own services.', 403);
            }

            // Ensure location belongs to this service
            if ($serviceLocation->service_id !== $service->id) {
                return $this->error('Location does not belong to this service.', 404);
            }

            $serviceLocation->load([
                'service',
                'availabilityWindows' => function ($query) {
                    $query->orderBy('day_of_week')->orderBy('start_time');
                },
                'bookings' => function ($query) {
                    $query->latest()->limit(10);
                }
            ]);

            // Get capacity and booking statistics
            $locationStats = [
                'total_bookings' => $serviceLocation->bookings()->count(),
                'active_bookings' => $serviceLocation->bookings()
                    ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                    ->count(),
                'completed_bookings' => $serviceLocation->bookings()
                    ->where('status', 'completed')
                    ->count(),
                'total_revenue' => $serviceLocation->bookings()
                    ->where('status', 'completed')
                    ->sum('total_amount'),
                'average_capacity_usage' => $this->calculateAverageCapacityUsage($serviceLocation),
            ];

            return $this->ok('Service location details retrieved', [
                'location' => new ServiceLocationResource($serviceLocation),
                'statistics' => array_merge($locationStats, [
                    'formatted_revenue' => '£' . number_format($locationStats['total_revenue'] / 100, 2),
                    'capacity_usage_percentage' => round($locationStats['average_capacity_usage'], 1),
                ]),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update a location
     */
    public function update(UpdateServiceLocationRequest $request, Service $service, ServiceLocation $serviceLocation)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_locations')) {
                return $this->error('You do not have permission to edit service locations.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit locations for your own services.', 403);
            }

            // Ensure location belongs to this service
            if ($serviceLocation->service_id !== $service->id) {
                return $this->error('Location does not belong to this service.', 404);
            }

            return DB::transaction(function () use ($request, $serviceLocation, $user) {
                $data = $request->validated();

                // Handle coordinates if address changed and no manual coordinates provided
                $addressChanged = false;
                foreach (['address_line_1', 'address_line_2', 'city', 'postcode'] as $field) {
                    if (isset($data[$field]) && $data[$field] !== $serviceLocation->$field) {
                        $addressChanged = true;
                        break;
                    }
                }

                if ($addressChanged && !isset($data['latitude'])) {
                    $coordinates = $this->geocodeAddress($data);
                    if ($coordinates) {
                        $data['latitude'] = $coordinates['lat'];
                        $data['longitude'] = $coordinates['lng'];
                    }
                }

                $serviceLocation->update($data);

                // Update venue details if provided
                if ($request->has('venue_details')) {
                    $this->updateVenueDetails($serviceLocation, $request->venue_details);
                }

                Log::info('Service location updated', [
                    'location_id' => $serviceLocation->id,
                    'location_name' => $serviceLocation->name,
                    'service_id' => $serviceLocation->service_id,
                    'updated_by' => $user->id,
                    'updated_fields' => array_keys($data),
                ]);

                return $this->ok('Service location updated successfully', [
                    'location' => new ServiceLocationResource($serviceLocation->load(['service', 'availabilityWindows']))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete a location
     */
    public function destroy(Request $request, Service $service, ServiceLocation $serviceLocation)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('delete_service_locations')) {
                return $this->error('You do not have permission to delete service locations.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('delete_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only delete locations for your own services.', 403);
            }

            // Ensure location belongs to this service
            if ($serviceLocation->service_id !== $service->id) {
                return $this->error('Location does not belong to this service.', 404);
            }

            // Check if location has active bookings
            $activeBookingsCount = $serviceLocation->bookings()
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->count();

            if ($activeBookingsCount > 0) {
                return $this->error("Cannot delete location with {$activeBookingsCount} active bookings.", 422);
            }

            // Check if this is the last location for the service
            if ($service->serviceLocations()->count() <= 1) {
                return $this->error("Cannot delete the last location for this service.", 422);
            }

            return DB::transaction(function () use ($serviceLocation, $user) {
                $locationName = $serviceLocation->name;
                $locationId = $serviceLocation->id;
                $serviceId = $serviceLocation->service_id;

                $serviceLocation->delete();

                Log::info('Service location deleted', [
                    'location_id' => $locationId,
                    'location_name' => $locationName,
                    'service_id' => $serviceId,
                    'deleted_by' => $user->id,
                ]);

                return $this->ok('Service location deleted successfully');
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Toggle location active status
     */
    public function toggleStatus(Request $request, Service $service, ServiceLocation $serviceLocation)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_locations')) {
                return $this->error('You do not have permission to edit service locations.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit locations for your own services.', 403);
            }

            // Ensure location belongs to this service
            if ($serviceLocation->service_id !== $service->id) {
                return $this->error('Location does not belong to this service.', 404);
            }

            // Check if this would be the last active location
            if ($serviceLocation->is_active) {
                $activeLocationsCount = $service->serviceLocations()->where('is_active', true)->count();
                if ($activeLocationsCount <= 1) {
                    return $this->error('Cannot deactivate the last active location for this service.', 422);
                }
            }

            $newStatus = !$serviceLocation->is_active;
            $serviceLocation->update(['is_active' => $newStatus]);

            Log::info('Service location status toggled', [
                'location_id' => $serviceLocation->id,
                'location_name' => $serviceLocation->name,
                'service_id' => $service->id,
                'new_status' => $newStatus ? 'active' : 'inactive',
                'updated_by' => $user->id,
            ]);

            return $this->ok('Location status updated successfully', [
                'location' => new ServiceLocationResource($serviceLocation),
                'new_status' => $newStatus ? 'active' : 'inactive',
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get locations within distance of coordinates
     */
    public function nearby(Request $request, Service $service)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius_km' => 'nullable|numeric|min:0.1|max:100',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_locations')) {
                return $this->error('You do not have permission to view service locations.', 403);
            }

            // Check if user can view this service's locations
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view locations for your own services.', 403);
            }

            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radiusKm = $request->input('radius_km', 25); // Default 25km
            $limit = $request->input('limit', 10);

            $locations = $service->serviceLocations()
                ->select('*')
                ->selectRaw('
                    ( 6371 * acos( cos( radians(?) )
                    * cos( radians( latitude ) )
                    * cos( radians( longitude ) - radians(?) )
                    + sin( radians(?) )
                    * sin( radians( latitude ) ) ) ) AS distance_km
                ', [$latitude, $longitude, $latitude])
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->where('is_active', true)
                ->havingRaw('distance_km <= ?', [$radiusKm])
                ->orderBy('distance_km')
                ->limit($limit)
                ->get();

            return $this->ok('Nearby locations retrieved successfully', [
                'search_criteria' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'radius_km' => $radiusKm,
                ],
                'locations' => ServiceLocationResource::collection($locations),
                'total_found' => $locations->count(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get location analytics
     */
    public function getAnalytics(Request $request, Service $service)
    {
        try {
            $request->validate([
                'period' => 'nullable|in:week,month,quarter,year',
                'location_id' => 'nullable|exists:service_locations,id',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_locations')) {
                return $this->error('You do not have permission to view location analytics.', 403);
            }

            // Check if user can view this service
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view analytics for your own services.', 403);
            }

            $period = $request->input('period', 'month');
            $locationId = $request->location_id;

            // Date range based on period
            $endDate = now();
            $startDate = match($period) {
                'week' => $endDate->clone()->subWeek(),
                'month' => $endDate->clone()->subMonth(),
                'quarter' => $endDate->clone()->subMonths(3),
                'year' => $endDate->clone()->subYear(),
            };

            $query = $service->serviceLocations();

            if ($locationId) {
                $query->where('id', $locationId);
            }

            $locations = $query->with(['bookings' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }])->get();

            $analyticsData = $locations->map(function ($location) use ($startDate, $endDate) {
                $bookings = $location->bookings;
                $revenue = $bookings->where('status', 'completed')->sum('total_amount');
                $totalBookings = $bookings->count();
                $completedBookings = $bookings->where('status', 'completed')->count();

                return [
                    'location_id' => $location->id,
                    'location_name' => $location->name,
                    'location_type' => $location->type,
                    'revenue' => $revenue,
                    'formatted_revenue' => '£' . number_format($revenue / 100, 2),
                    'total_bookings' => $totalBookings,
                    'completed_bookings' => $completedBookings,
                    'completion_rate' => $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100, 1) : 0,
                    'average_booking_value' => $completedBookings > 0 ? round($revenue / $completedBookings) : 0,
                ];
            });

            $totalRevenue = $analyticsData->sum('revenue');
            $totalBookings = $analyticsData->sum('total_bookings');
            $totalCompleted = $analyticsData->sum('completed_bookings');

            return $this->ok('Location analytics retrieved', [
                'period' => $period,
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                ],
                'summary' => [
                    'total_locations' => $locations->count(),
                    'total_revenue' => $totalRevenue,
                    'formatted_total_revenue' => '£' . number_format($totalRevenue / 100, 2),
                    'total_bookings' => $totalBookings,
                    'completed_bookings' => $totalCompleted,
                    'overall_completion_rate' => $totalBookings > 0 ? round(($totalCompleted / $totalBookings) * 100, 1) : 0,
                ],
                'locations' => $analyticsData->sortByDesc('revenue')->values(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Helper: Geocode address to get coordinates
     */
    private function geocodeAddress(array $addressData): ?array
    {
        // This would integrate with a geocoding service like Google Maps API
        // For now, return null (manual coordinates entry required)
        // TODO: Implement geocoding service integration
        return null;
    }

    /**
     * Helper: Create venue details
     */
    private function createVenueDetails(ServiceLocation $location, array $venueData): void
    {
        // Create venue details record
        // This would create a related VenueDetails record
        // Implementation depends on the venue details structure
    }

    /**
     * Helper: Update venue details
     */
    private function updateVenueDetails(ServiceLocation $location, array $venueData): void
    {
        // Update venue details record
        // This would update the related VenueDetails record
    }

    /**
     * Helper: Calculate average capacity usage
     */
    private function calculateAverageCapacityUsage(ServiceLocation $location): float
    {
        if (!$location->max_capacity) {
            return 0;
        }

        // Calculate based on recent bookings and capacity
        $recentBookings = $location->bookings()
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', 'completed')
            ->count();

        $totalPossibleSlots = 30; // Simplified calculation

        return $totalPossibleSlots > 0 ? ($recentBookings / $totalPossibleSlots) * 100 : 0;
    }
}

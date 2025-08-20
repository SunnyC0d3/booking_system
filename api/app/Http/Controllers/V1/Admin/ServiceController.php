<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Resources\V1\ServiceResource;
use App\Requests\V1\StoreServiceRequest;
use App\Requests\V1\UpdateServiceRequest;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ServiceController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
    }

    /**
     * Get all services (admin view)
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:active,inactive,all',
                'category' => 'nullable|string|max:100',
                'vendor_id' => 'nullable|exists:users,id',
                'search' => 'nullable|string|max:100',
                'sort' => 'nullable|in:name_asc,name_desc,price_asc,price_desc,created_asc,created_desc,bookings_desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_services')) {
                return $this->error('You do not have permission to view services.', 403);
            }

            $query = Service::with([
                'serviceLocations',
                'addOns',
                'vendor',
                'media'
            ]);

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

            // Filter by category
            if ($request->category) {
                $query->where('category', $request->category);
            }

            // Filter by vendor
            if ($request->vendor_id) {
                $query->where('vendor_id', $request->vendor_id);
            }

            // Search functionality
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('category', 'LIKE', "%{$search}%");
                });
            }

            // Add booking statistics
            $query->withCount([
                'bookings as total_bookings_count',
                'bookings as completed_bookings_count' => function ($q) {
                    $q->where('status', 'completed');
                },
                'bookings as pending_bookings_count' => function ($q) {
                    $q->where('status', 'pending');
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
                case 'price_asc':
                    $query->orderBy('base_price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('base_price', 'desc');
                    break;
                case 'created_asc':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'created_desc':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'bookings_desc':
                    $query->orderBy('total_bookings_count', 'desc');
                    break;
                default:
                    $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
            }

            $perPage = $request->input('per_page', 20);
            $services = $query->paginate($perPage);

            return ServiceResource::collection($services)->additional([
                'message' => 'Services retrieved successfully',
                'status' => 200,
                'statistics' => [
                    'total_services' => Service::count(),
                    'active_services' => Service::where('is_active', true)->count(),
                    'inactive_services' => Service::where('is_active', false)->count(),
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new service
     */
    public function store(StoreServiceRequest $request)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_services')) {
                return $this->error('You do not have permission to create services.', 403);
            }

            return DB::transaction(function () use ($request, $user) {
                $data = $request->validated();

                // Set vendor ID if not admin
                if (!$user->hasPermission('manage_all_services')) {
                    $data['vendor_id'] = $user->id;
                }

                // Create the service
                $service = Service::create($data);

                // Handle media uploads if present
                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $image) {
                        $service->addMediaFromRequest('images')
                            ->each(function ($fileAdder) {
                                $fileAdder->toMediaCollection('images');
                            });
                    }
                }

                Log::info('Service created', [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'created_by' => $user->id,
                ]);

                return $this->ok('Service created successfully', [
                    'service' => new ServiceResource($service->load(['serviceLocations', 'addOns', 'vendor', 'media']))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get service analytics
     */
    public function getAnalytics(Request $request)
    {
        try {
            $request->validate([
                'period' => 'nullable|in:week,month,quarter,year',
                'service_id' => 'nullable|exists:services,id',
                'vendor_id' => 'nullable|exists:users,id',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_services')) {
                return $this->error('You do not have permission to view service analytics.', 403);
            }

            $period = $request->input('period', 'month');
            $serviceId = $request->service_id;
            $vendorId = $request->vendor_id;

            // Date range based on period
            $endDate = now();
            $startDate = match ($period) {
                'week' => $endDate->clone()->subWeek(),
                'month' => $endDate->clone()->subMonth(),
                'quarter' => $endDate->clone()->subMonths(3),
                'year' => $endDate->clone()->subYear(),
            };

            $query = Service::query();

            // Apply filters
            if ($serviceId) {
                $query->where('id', $serviceId);
            }

            if ($vendorId) {
                $query->where('vendor_id', $vendorId);
            }

            // If not admin, only show own services
            if (!$user->hasPermission('view_all_services')) {
                $query->where('vendor_id', $user->id);
            }

            $services = $query->withCount([
                'bookings as total_bookings' => function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                },
                'bookings as completed_bookings' => function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'completed')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                },
                'bookings as cancelled_bookings' => function ($q) use ($startDate, $endDate) {
                    $q->where('status', 'cancelled')
                        ->whereBetween('created_at', [$startDate, $endDate]);
                },
            ])->get();

            // Calculate revenue
            $revenueData = $services->map(function ($service) use ($startDate, $endDate) {
                $revenue = $service->bookings()
                    ->where('status', 'completed')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum('total_amount');

                return [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'revenue' => $revenue,
                    'formatted_revenue' => '£' . number_format($revenue / 100, 2),
                    'total_bookings' => $service->total_bookings,
                    'completed_bookings' => $service->completed_bookings,
                    'cancelled_bookings' => $service->cancelled_bookings,
                    'completion_rate' => $service->total_bookings > 0
                        ? round(($service->completed_bookings / $service->total_bookings) * 100, 1)
                        : 0,
                ];
            });

            $totalRevenue = $revenueData->sum('revenue');
            $totalBookings = $revenueData->sum('total_bookings');
            $totalCompleted = $revenueData->sum('completed_bookings');

            return $this->ok('Service analytics retrieved', [
                'period' => $period,
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'summary' => [
                    'total_services' => $services->count(),
                    'total_revenue' => $totalRevenue,
                    'formatted_total_revenue' => '£' . number_format($totalRevenue / 100, 2),
                    'total_bookings' => $totalBookings,
                    'completed_bookings' => $totalCompleted,
                    'overall_completion_rate' => $totalBookings > 0
                        ? round(($totalCompleted / $totalBookings) * 100, 1)
                        : 0,
                ],
                'services' => $revenueData->sortByDesc('revenue')->values(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get service details (admin view)
     */
    public function show(Request $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_services')) {
                return $this->error('You do not have permission to view services.', 403);
            }

            // Check if user can view this specific service
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view your own services.', 403);
            }

            $service->load([
                'serviceLocations',
                'addOns',
                'availabilityWindows',
                'vendor',
                'media',
                'bookings' => function ($query) {
                    $query->latest()->limit(10);
                }
            ]);

            // Add statistics
            $service->loadCount([
                'bookings as total_bookings_count',
                'bookings as completed_bookings_count' => function ($q) {
                    $q->where('status', 'completed');
                },
                'bookings as cancelled_bookings_count' => function ($q) {
                    $q->where('status', 'cancelled');
                },
            ]);

            // Calculate revenue
            $totalRevenue = $service->bookings()
                ->where('status', 'completed')
                ->sum('total_amount');

            return $this->ok('Service details retrieved', [
                'service' => new ServiceResource($service),
                'statistics' => [
                    'total_bookings' => $service->total_bookings_count,
                    'completed_bookings' => $service->completed_bookings_count,
                    'cancelled_bookings' => $service->cancelled_bookings_count,
                    'total_revenue' => $totalRevenue,
                    'formatted_revenue' => '£' . number_format($totalRevenue / 100, 2),
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update a service
     */
    public function update(UpdateServiceRequest $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_services')) {
                return $this->error('You do not have permission to edit services.', 403);
            }

            // Check if user can edit this specific service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit your own services.', 403);
            }

            return DB::transaction(function () use ($request, $service, $user) {
                $data = $request->validated();

                // Don't allow non-admin to change vendor
                if (!$user->hasPermission('manage_all_services')) {
                    unset($data['vendor_id']);
                }

                $service->update($data);

                // Handle media uploads if present
                if ($request->hasFile('images')) {
                    // Clear existing images if replacing
                    if ($request->input('replace_images', false)) {
                        $service->clearMediaCollection('images');
                    }

                    foreach ($request->file('images') as $image) {
                        $service->addMediaFromRequest('images')
                            ->each(function ($fileAdder) {
                                $fileAdder->toMediaCollection('images');
                            });
                    }
                }

                Log::info('Service updated', [
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'updated_by' => $user->id,
                    'updated_fields' => array_keys($data),
                ]);

                return $this->ok('Service updated successfully', [
                    'service' => new ServiceResource($service->load(['serviceLocations', 'addOns', 'vendor', 'media']))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete a service
     */
    public function destroy(Request $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('delete_services')) {
                return $this->error('You do not have permission to delete services.', 403);
            }

            // Check if user can delete this specific service
            if (!$user->hasPermission('delete_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only delete your own services.', 403);
            }

            // Check if service has active bookings
            $activeBookingsCount = $service->bookings()
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->count();

            if ($activeBookingsCount > 0) {
                return $this->error("Cannot delete service with {$activeBookingsCount} active bookings.", 422);
            }

            return DB::transaction(function () use ($service, $user) {
                $serviceName = $service->name;
                $serviceId = $service->id;

                $service->delete();

                Log::info('Service deleted', [
                    'service_id' => $serviceId,
                    'service_name' => $serviceName,
                    'deleted_by' => $user->id,
                ]);

                return $this->ok('Service deleted successfully');
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Toggle service active status
     */
    public function toggleStatus(Request $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_services')) {
                return $this->error('You do not have permission to edit services.', 403);
            }

            // Check if user can edit this specific service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit your own services.', 403);
            }

            $newStatus = !$service->is_active;
            $service->update(['is_active' => $newStatus]);

            Log::info('Service status toggled', [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'new_status' => $newStatus ? 'active' : 'inactive',
                'updated_by' => $user->id,
            ]);

            return $this->ok('Service status updated successfully', [
                'service' => new ServiceResource($service),
                'new_status' => $newStatus ? 'active' : 'inactive',
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Duplicate a service
     */
    public function duplicate(Request $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_services')) {
                return $this->error('You do not have permission to create services.', 403);
            }

            // Check if user can access this specific service
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only duplicate your own services.', 403);
            }

            return DB::transaction(function () use ($service, $user) {
                // Create duplicate service
                $duplicatedService = $service->replicate([
                    'created_at',
                    'updated_at'
                ]);

                $duplicatedService->name = $service->name . ' (Copy)';
                $duplicatedService->is_active = false; // Start as inactive
                $duplicatedService->save();

                // Copy locations
                foreach ($service->serviceLocations as $location) {
                    $duplicatedLocation = $location->replicate([
                        'created_at',
                        'updated_at'
                    ]);
                    $duplicatedLocation->service_id = $duplicatedService->id;
                    $duplicatedLocation->save();
                }

                // Copy add-ons
                foreach ($service->addOns as $addOn) {
                    $duplicatedAddOn = $addOn->replicate([
                        'created_at',
                        'updated_at'
                    ]);
                    $duplicatedAddOn->service_id = $duplicatedService->id;
                    $duplicatedAddOn->save();
                }

                // Copy availability windows
                foreach ($service->availabilityWindows as $window) {
                    $duplicatedWindow = $window->replicate([
                        'created_at',
                        'updated_at'
                    ]);
                    $duplicatedWindow->service_id = $duplicatedService->id;
                    $duplicatedWindow->save();
                }

                Log::info('Service duplicated', [
                    'original_service_id' => $service->id,
                    'duplicated_service_id' => $duplicatedService->id,
                    'duplicated_by' => $user->id,
                ]);

                return $this->ok('Service duplicated successfully', [
                    'service' => new ServiceResource($duplicatedService->load(['serviceLocations', 'addOns', 'vendor']))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}

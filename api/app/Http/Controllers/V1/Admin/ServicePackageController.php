<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use App\Models\Service;
use App\Resources\V1\ServicePackageResource;
use App\Requests\V1\StoreServicePackageRequest;
use App\Requests\V1\UpdateServicePackageRequest;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ServicePackageController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
    }

    /**
     * Get all service packages
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:active,inactive,all',
                'search' => 'nullable|string|max:100',
                'sort' => 'nullable|in:name_asc,name_desc,price_asc,price_desc,created_asc,created_desc',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_packages')) {
                return $this->error('You do not have permission to view service packages.', 403);
            }

            $query = ServicePackage::with([
                'services',
                'bookings' => function ($query) {
                    $query->latest()->limit(5);
                }
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

            // Search functionality
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            // Add booking statistics
            $query->withCount([
                'bookings as total_bookings_count',
                'bookings as completed_bookings_count' => function ($q) {
                    $q->where('status', 'completed');
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
                    $query->orderBy('total_price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('total_price', 'desc');
                    break;
                case 'created_asc':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'created_desc':
                    $query->orderBy('created_at', 'desc');
                    break;
                default:
                    $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
            }

            $perPage = $request->input('per_page', 20);
            $packages = $query->paginate($perPage);

            return ServicePackageResource::collection($packages)->additional([
                'message' => 'Service packages retrieved successfully',
                'status' => 200,
                'statistics' => [
                    'total_packages' => ServicePackage::count(),
                    'active_packages' => ServicePackage::where('is_active', true)->count(),
                    'inactive_packages' => ServicePackage::where('is_active', false)->count(),
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new service package
     */
    public function store(StoreServicePackageRequest $request)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_service_packages')) {
                return $this->error('You do not have permission to create service packages.', 403);
            }

            return DB::transaction(function () use ($request, $user) {
                $data = $request->validated();

                // Calculate totals based on included services
                $serviceIds = collect($data['services'])->pluck('service_id');
                $services = Service::whereIn('id', $serviceIds)->get();

                $individualPriceTotal = 0;
                $totalDurationMinutes = 0;
                $requiresConsultation = false;

                foreach ($data['services'] as $serviceData) {
                    $service = $services->find($serviceData['service_id']);
                    if ($service) {
                        $quantity = $serviceData['quantity'] ?? 1;
                        $individualPriceTotal += $service->base_price * $quantity;
                        $totalDurationMinutes += $service->duration_minutes * $quantity;

                        if ($service->requires_consultation) {
                            $requiresConsultation = true;
                        }
                    }
                }

                // Create the package
                $packageData = array_merge($data, [
                    'individual_price_total' => $individualPriceTotal,
                    'total_duration_minutes' => $totalDurationMinutes,
                    'requires_consultation' => $requiresConsultation,
                ]);

                $package = ServicePackage::create($packageData);

                // Attach services with pivot data
                foreach ($data['services'] as $serviceData) {
                    $package->services()->attach($serviceData['service_id'], [
                        'quantity' => $serviceData['quantity'] ?? 1,
                        'order' => $serviceData['order'] ?? 0,
                        'is_optional' => $serviceData['is_optional'] ?? false,
                        'notes' => $serviceData['notes'] ?? null,
                    ]);
                }

                Log::info('Service package created', [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'services_count' => count($data['services']),
                    'created_by' => $user->id,
                ]);

                return $this->ok('Service package created successfully', [
                    'package' => new ServicePackageResource($package->load('services'))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get service package details
     */
    public function show(Request $request, ServicePackage $package)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_packages')) {
                return $this->error('You do not have permission to view service packages.', 403);
            }

            $package->load([
                'services',
                'bookings' => function ($query) {
                    $query->with(['user', 'serviceLocation'])->latest()->limit(10);
                }
            ]);

            // Add statistics
            $package->loadCount([
                'bookings as total_bookings_count',
                'bookings as completed_bookings_count' => function ($q) {
                    $q->where('status', 'completed');
                },
                'bookings as cancelled_bookings_count' => function ($q) {
                    $q->where('status', 'cancelled');
                },
            ]);

            // Calculate revenue
            $totalRevenue = $package->bookings()
                ->where('status', 'completed')
                ->sum('total_amount');

            return $this->ok('Service package details retrieved', [
                'package' => new ServicePackageResource($package),
                'statistics' => [
                    'total_bookings' => $package->total_bookings_count,
                    'completed_bookings' => $package->completed_bookings_count,
                    'cancelled_bookings' => $package->cancelled_bookings_count,
                    'total_revenue' => $totalRevenue,
                    'formatted_revenue' => '£' . number_format($totalRevenue / 100, 2),
                    'average_booking_value' => $package->completed_bookings_count > 0
                        ? $totalRevenue / $package->completed_bookings_count
                        : 0,
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update a service package
     */
    public function update(UpdateServicePackageRequest $request, ServicePackage $package)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_packages')) {
                return $this->error('You do not have permission to edit service packages.', 403);
            }

            return DB::transaction(function () use ($request, $package, $user) {
                $data = $request->validated();

                // If services are being updated, recalculate totals
                if (isset($data['services'])) {
                    $serviceIds = collect($data['services'])->pluck('service_id');
                    $services = Service::whereIn('id', $serviceIds)->get();

                    $individualPriceTotal = 0;
                    $totalDurationMinutes = 0;
                    $requiresConsultation = false;

                    foreach ($data['services'] as $serviceData) {
                        $service = $services->find($serviceData['service_id']);
                        if ($service) {
                            $quantity = $serviceData['quantity'] ?? 1;
                            $individualPriceTotal += $service->base_price * $quantity;
                            $totalDurationMinutes += $service->duration_minutes * $quantity;

                            if ($service->requires_consultation) {
                                $requiresConsultation = true;
                            }
                        }
                    }

                    $data['individual_price_total'] = $individualPriceTotal;
                    $data['total_duration_minutes'] = $totalDurationMinutes;
                    $data['requires_consultation'] = $requiresConsultation;

                    // Update service relationships
                    $package->services()->detach();
                    foreach ($data['services'] as $serviceData) {
                        $package->services()->attach($serviceData['service_id'], [
                            'quantity' => $serviceData['quantity'] ?? 1,
                            'order' => $serviceData['order'] ?? 0,
                            'is_optional' => $serviceData['is_optional'] ?? false,
                            'notes' => $serviceData['notes'] ?? null,
                        ]);
                    }

                    unset($data['services']); // Remove from main update data
                }

                $package->update($data);

                Log::info('Service package updated', [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'updated_by' => $user->id,
                    'updated_fields' => array_keys($data),
                ]);

                return $this->ok('Service package updated successfully', [
                    'package' => new ServicePackageResource($package->load('services'))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete a service package
     */
    public function destroy(Request $request, ServicePackage $package)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('delete_service_packages')) {
                return $this->error('You do not have permission to delete service packages.', 403);
            }

            // Check if package has active bookings
            $activeBookingsCount = $package->bookings()
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->count();

            if ($activeBookingsCount > 0) {
                return $this->error("Cannot delete package with {$activeBookingsCount} active bookings.", 422);
            }

            return DB::transaction(function () use ($package, $user) {
                $packageName = $package->name;
                $packageId = $package->id;

                $package->delete();

                Log::info('Service package deleted', [
                    'package_id' => $packageId,
                    'package_name' => $packageName,
                    'deleted_by' => $user->id,
                ]);

                return $this->ok('Service package deleted successfully');
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Toggle package active status
     */
    public function toggleStatus(Request $request, ServicePackage $package)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_packages')) {
                return $this->error('You do not have permission to edit service packages.', 403);
            }

            $newStatus = !$package->is_active;
            $package->update(['is_active' => $newStatus]);

            Log::info('Service package status toggled', [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'new_status' => $newStatus ? 'active' : 'inactive',
                'updated_by' => $user->id,
            ]);

            return $this->ok('Package status updated successfully', [
                'package' => new ServicePackageResource($package),
                'new_status' => $newStatus ? 'active' : 'inactive',
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Duplicate a service package
     */
    public function duplicate(Request $request, ServicePackage $package)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_service_packages')) {
                return $this->error('You do not have permission to create service packages.', 403);
            }

            return DB::transaction(function () use ($package, $user) {
                // Create duplicate package
                $duplicatedPackage = $package->replicate([
                    'created_at',
                    'updated_at'
                ]);

                $duplicatedPackage->name = $package->name . ' (Copy)';
                $duplicatedPackage->is_active = false; // Start as inactive
                $duplicatedPackage->save();

                // Copy service relationships
                foreach ($package->services as $service) {
                    $duplicatedPackage->services()->attach($service->id, [
                        'quantity' => $service->pivot->quantity,
                        'order' => $service->pivot->order,
                        'is_optional' => $service->pivot->is_optional,
                        'notes' => $service->pivot->notes,
                    ]);
                }

                Log::info('Service package duplicated', [
                    'original_package_id' => $package->id,
                    'duplicated_package_id' => $duplicatedPackage->id,
                    'duplicated_by' => $user->id,
                ]);

                return $this->ok('Service package duplicated successfully', [
                    'package' => new ServicePackageResource($duplicatedPackage->load('services'))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get package pricing breakdown
     */
    public function getPricingBreakdown(Request $request, ServicePackage $package)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_packages')) {
                return $this->error('You do not have permission to view service packages.', 403);
            }

            $package->load('services');

            $breakdown = [
                'package_info' => [
                    'name' => $package->name,
                    'total_price' => $package->total_price,
                    'formatted_total_price' => '£' . number_format($package->total_price / 100, 2),
                    'individual_price_total' => $package->individual_price_total,
                    'formatted_individual_total' => '£' . number_format($package->individual_price_total / 100, 2),
                    'discount_amount' => $package->discount_amount,
                    'formatted_discount' => '£' . number_format($package->discount_amount / 100, 2),
                    'discount_percentage' => $package->discount_percentage,
                    'savings' => $package->individual_price_total - $package->total_price,
                    'formatted_savings' => '£' . number_format(($package->individual_price_total - $package->total_price) / 100, 2),
                ],
                'services' => $package->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'base_price' => $service->base_price,
                        'formatted_price' => '£' . number_format($service->base_price / 100, 2),
                        'quantity' => $service->pivot->quantity,
                        'is_optional' => $service->pivot->is_optional,
                        'line_total' => $service->base_price * $service->pivot->quantity,
                        'formatted_line_total' => '£' . number_format(($service->base_price * $service->pivot->quantity) / 100, 2),
                        'duration_minutes' => $service->duration_minutes,
                        'total_duration' => $service->duration_minutes * $service->pivot->quantity,
                    ];
                }),
                'duration_summary' => [
                    'total_duration_minutes' => $package->total_duration_minutes,
                    'formatted_duration' => $this->formatDuration($package->total_duration_minutes),
                ],
            ];

            return $this->ok('Package pricing breakdown retrieved', $breakdown);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Helper method to format duration
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? "1 hour" : "{$hours} hours";
        }

        $hoursText = $hours === 1 ? "1 hour" : "{$hours} hours";
        $minutesText = "{$remainingMinutes} minutes";

        return "{$hoursText} {$minutesText}";
    }
}

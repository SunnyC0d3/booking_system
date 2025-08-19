<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceAddOn;
use App\Resources\V1\ServiceAddOnResource;
use App\Requests\V1\StoreServiceAddOnRequest;
use App\Requests\V1\UpdateServiceAddOnRequest;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ServiceAddOnController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
    }

    /**
     * Get all add-ons for a service
     */
    public function index(Request $request, Service $service)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:active,inactive,all',
                'category' => 'nullable|string|max:100',
                'search' => 'nullable|string|max:100',
                'sort' => 'nullable|in:name_asc,name_desc,price_asc,price_desc,order_asc,order_desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_addons')) {
                return $this->error('You do not have permission to view service add-ons.', 403);
            }

            // Check if user can view this service's add-ons
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view add-ons for your own services.', 403);
            }

            $query = $service->addOns();

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

            // Search functionality
            if ($request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('category', 'LIKE', "%{$search}%");
                });
            }

            // Sorting
            switch ($request->sort) {
                case 'name_asc':
                    $query->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $query->orderBy('name', 'desc');
                    break;
                case 'price_asc':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'order_asc':
                    $query->orderBy('sort_order', 'asc');
                    break;
                case 'order_desc':
                    $query->orderBy('sort_order', 'desc');
                    break;
                default:
                    $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
            }

            $perPage = $request->input('per_page', 20);
            $addOns = $query->paginate($perPage);

            return ServiceAddOnResource::collection($addOns)->additional([
                'message' => 'Service add-ons retrieved successfully',
                'status' => 200,
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category' => $service->category,
                ],
                'statistics' => [
                    'total_addons' => $service->addOns()->count(),
                    'active_addons' => $service->addOns()->where('is_active', true)->count(),
                    'required_addons' => $service->addOns()->where('is_required', true)->count(),
                ],
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new add-on for a service
     */
    public function store(StoreServiceAddOnRequest $request, Service $service)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_service_addons')) {
                return $this->error('You do not have permission to create service add-ons.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only create add-ons for your own services.', 403);
            }

            // Check business rule: max add-ons per service
            if (!$service->canAddMoreAddOns()) {
                return $this->error('Maximum number of add-ons reached for this service.', 422);
            }

            return DB::transaction(function () use ($request, $service, $user) {
                $data = $request->validated();
                $data['service_id'] = $service->id;

                // Auto-assign sort order if not provided
                if (!isset($data['sort_order'])) {
                    $data['sort_order'] = $service->addOns()->max('sort_order') + 1;
                }

                $addOn = ServiceAddOn::create($data);

                Log::info('Service add-on created', [
                    'addon_id' => $addOn->id,
                    'addon_name' => $addOn->name,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'created_by' => $user->id,
                ]);

                return $this->ok('Service add-on created successfully', [
                    'add_on' => new ServiceAddOnResource($addOn->load('service'))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get add-on details
     */
    public function show(Request $request, Service $service, ServiceAddOn $serviceAddOn)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_addons')) {
                return $this->error('You do not have permission to view service add-ons.', 403);
            }

            // Check if user can view this service's add-ons
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view add-ons for your own services.', 403);
            }

            // Ensure add-on belongs to this service
            if ($serviceAddOn->service_id !== $service->id) {
                return $this->error('Add-on does not belong to this service.', 404);
            }

            $serviceAddOn->load(['service', 'bookingAddOns']);

            // Get usage statistics
            $usageStats = [
                'total_bookings' => $serviceAddOn->bookingAddOns()->count(),
                'total_quantity_sold' => $serviceAddOn->bookingAddOns()->sum('quantity'),
                'total_revenue' => $serviceAddOn->bookingAddOns()->sum('total_price'),
                'average_quantity_per_booking' => $serviceAddOn->bookingAddOns()->avg('quantity') ?: 0,
            ];

            return $this->ok('Service add-on details retrieved', [
                'add_on' => new ServiceAddOnResource($serviceAddOn),
                'usage_statistics' => array_merge($usageStats, [
                    'formatted_revenue' => '£' . number_format($usageStats['total_revenue'] / 100, 2),
                    'average_quantity_formatted' => number_format($usageStats['average_quantity_per_booking'], 1),
                ]),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an add-on
     */
    public function update(UpdateServiceAddOnRequest $request, Service $service, ServiceAddOn $serviceAddOn)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_addons')) {
                return $this->error('You do not have permission to edit service add-ons.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit add-ons for your own services.', 403);
            }

            // Ensure add-on belongs to this service
            if ($serviceAddOn->service_id !== $service->id) {
                return $this->error('Add-on does not belong to this service.', 404);
            }

            return DB::transaction(function () use ($request, $serviceAddOn, $user) {
                $data = $request->validated();
                $serviceAddOn->update($data);

                Log::info('Service add-on updated', [
                    'addon_id' => $serviceAddOn->id,
                    'addon_name' => $serviceAddOn->name,
                    'service_id' => $serviceAddOn->service_id,
                    'updated_by' => $user->id,
                    'updated_fields' => array_keys($data),
                ]);

                return $this->ok('Service add-on updated successfully', [
                    'add_on' => new ServiceAddOnResource($serviceAddOn->load('service'))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete an add-on
     */
    public function destroy(Request $request, Service $service, ServiceAddOn $serviceAddOn)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('delete_service_addons')) {
                return $this->error('You do not have permission to delete service add-ons.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('delete_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only delete add-ons for your own services.', 403);
            }

            // Ensure add-on belongs to this service
            if ($serviceAddOn->service_id !== $service->id) {
                return $this->error('Add-on does not belong to this service.', 404);
            }

            // Check if add-on is being used in active bookings
            $activeBookingsCount = $serviceAddOn->bookingAddOns()
                ->whereHas('booking', function ($query) {
                    $query->whereIn('status', ['pending', 'confirmed', 'in_progress']);
                })
                ->count();

            if ($activeBookingsCount > 0) {
                return $this->error("Cannot delete add-on used in {$activeBookingsCount} active bookings.", 422);
            }

            return DB::transaction(function () use ($serviceAddOn, $user) {
                $addOnName = $serviceAddOn->name;
                $addOnId = $serviceAddOn->id;
                $serviceId = $serviceAddOn->service_id;

                $serviceAddOn->delete();

                Log::info('Service add-on deleted', [
                    'addon_id' => $addOnId,
                    'addon_name' => $addOnName,
                    'service_id' => $serviceId,
                    'deleted_by' => $user->id,
                ]);

                return $this->ok('Service add-on deleted successfully');
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Toggle add-on active status
     */
    public function toggleStatus(Request $request, Service $service, ServiceAddOn $serviceAddOn)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_addons')) {
                return $this->error('You do not have permission to edit service add-ons.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only edit add-ons for your own services.', 403);
            }

            // Ensure add-on belongs to this service
            if ($serviceAddOn->service_id !== $service->id) {
                return $this->error('Add-on does not belong to this service.', 404);
            }

            $newStatus = !$serviceAddOn->is_active;
            $serviceAddOn->update(['is_active' => $newStatus]);

            Log::info('Service add-on status toggled', [
                'addon_id' => $serviceAddOn->id,
                'addon_name' => $serviceAddOn->name,
                'service_id' => $service->id,
                'new_status' => $newStatus ? 'active' : 'inactive',
                'updated_by' => $user->id,
            ]);

            return $this->ok('Add-on status updated successfully', [
                'add_on' => new ServiceAddOnResource($serviceAddOn),
                'new_status' => $newStatus ? 'active' : 'inactive',
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Reorder add-ons
     */
    public function reorder(Request $request, Service $service)
    {
        try {
            $request->validate([
                'add_on_ids' => 'required|array|min:1',
                'add_on_ids.*' => 'exists:service_add_ons,id',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('edit_service_addons')) {
                return $this->error('You do not have permission to reorder service add-ons.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('edit_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only reorder add-ons for your own services.', 403);
            }

            $addOnIds = $request->add_on_ids;

            // Verify all add-ons belong to this service
            $addOns = ServiceAddOn::whereIn('id', $addOnIds)
                ->where('service_id', $service->id)
                ->get();

            if ($addOns->count() !== count($addOnIds)) {
                return $this->error('Some add-ons do not belong to this service.', 422);
            }

            return DB::transaction(function () use ($addOnIds, $user, $service) {
                foreach ($addOnIds as $index => $addOnId) {
                    ServiceAddOn::where('id', $addOnId)
                        ->update(['sort_order' => $index + 1]);
                }

                Log::info('Service add-ons reordered', [
                    'service_id' => $service->id,
                    'addon_order' => $addOnIds,
                    'reordered_by' => $user->id,
                ]);

                $reorderedAddOns = ServiceAddOn::whereIn('id', $addOnIds)
                    ->orderBy('sort_order')
                    ->get();

                return $this->ok('Add-ons reordered successfully', [
                    'add_ons' => ServiceAddOnResource::collection($reorderedAddOns)
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Duplicate an add-on
     */
    public function duplicate(Request $request, Service $service, ServiceAddOn $serviceAddOn)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('create_service_addons')) {
                return $this->error('You do not have permission to create service add-ons.', 403);
            }

            // Check if user can modify this service
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only duplicate add-ons for your own services.', 403);
            }

            // Ensure add-on belongs to this service
            if ($serviceAddOn->service_id !== $service->id) {
                return $this->error('Add-on does not belong to this service.', 404);
            }

            // Check business rule
            if (!$service->canAddMoreAddOns()) {
                return $this->error('Maximum number of add-ons reached for this service.', 422);
            }

            return DB::transaction(function () use ($serviceAddOn, $user, $service) {
                $duplicatedAddOn = $serviceAddOn->replicate([
                    'created_at',
                    'updated_at'
                ]);

                $duplicatedAddOn->name = $serviceAddOn->name . ' (Copy)';
                $duplicatedAddOn->is_active = false; // Start as inactive
                $duplicatedAddOn->sort_order = $service->addOns()->max('sort_order') + 1;
                $duplicatedAddOn->save();

                Log::info('Service add-on duplicated', [
                    'original_addon_id' => $serviceAddOn->id,
                    'duplicated_addon_id' => $duplicatedAddOn->id,
                    'service_id' => $service->id,
                    'duplicated_by' => $user->id,
                ]);

                return $this->ok('Add-on duplicated successfully', [
                    'add_on' => new ServiceAddOnResource($duplicatedAddOn->load('service'))
                ]);
            });

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get add-on analytics
     */
    public function getAnalytics(Request $request, Service $service)
    {
        try {
            $request->validate([
                'period' => 'nullable|in:week,month,quarter,year',
                'add_on_id' => 'nullable|exists:service_add_ons,id',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_service_addons')) {
                return $this->error('You do not have permission to view add-on analytics.', 403);
            }

            // Check if user can view this service
            if (!$user->hasPermission('view_all_services') && $service->vendor_id !== $user->id) {
                return $this->error('You can only view analytics for your own services.', 403);
            }

            $period = $request->input('period', 'month');
            $addOnId = $request->add_on_id;

            // Date range based on period
            $endDate = now();
            $startDate = match($period) {
                'week' => $endDate->clone()->subWeek(),
                'month' => $endDate->clone()->subMonth(),
                'quarter' => $endDate->clone()->subMonths(3),
                'year' => $endDate->clone()->subYear(),
            };

            $query = $service->addOns();

            if ($addOnId) {
                $query->where('id', $addOnId);
            }

            $addOns = $query->with(['bookingAddOns' => function ($q) use ($startDate, $endDate) {
                $q->whereHas('booking', function ($bookingQuery) use ($startDate, $endDate) {
                    $bookingQuery->whereBetween('created_at', [$startDate, $endDate]);
                });
            }])->get();

            $analyticsData = $addOns->map(function ($addOn) use ($startDate, $endDate) {
                $bookingAddOns = $addOn->bookingAddOns;
                $revenue = $bookingAddOns->sum('total_price');
                $quantity = $bookingAddOns->sum('quantity');
                $bookings = $bookingAddOns->count();

                return [
                    'add_on_id' => $addOn->id,
                    'add_on_name' => $addOn->name,
                    'category' => $addOn->category,
                    'revenue' => $revenue,
                    'formatted_revenue' => '£' . number_format($revenue / 100, 2),
                    'quantity_sold' => $quantity,
                    'bookings_count' => $bookings,
                    'average_quantity_per_booking' => $bookings > 0 ? round($quantity / $bookings, 1) : 0,
                    'average_revenue_per_booking' => $bookings > 0 ? round($revenue / $bookings) : 0,
                ];
            });

            $totalRevenue = $analyticsData->sum('revenue');
            $totalQuantity = $analyticsData->sum('quantity_sold');
            $totalBookings = $analyticsData->sum('bookings_count');

            return $this->ok('Add-on analytics retrieved', [
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
                    'total_addons' => $addOns->count(),
                    'total_revenue' => $totalRevenue,
                    'formatted_total_revenue' => '£' . number_format($totalRevenue / 100, 2),
                    'total_quantity_sold' => $totalQuantity,
                    'total_bookings_with_addons' => $totalBookings,
                ],
                'add_ons' => $analyticsData->sortByDesc('revenue')->values(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

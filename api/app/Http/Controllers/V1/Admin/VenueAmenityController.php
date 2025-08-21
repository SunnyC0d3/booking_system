<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceLocation;
use App\Models\VenueAmenity;
use App\Requests\V1\StoreVenueAmenityRequest;
use App\Requests\V1\UpdateVenueAmenityRequest;
use App\Resources\V1\VenueAmenityResource;
use App\Services\V1\Venues\VenueAmenityService;
use App\Traits\V1\ApiResponses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VenueAmenityController extends Controller
{
    use ApiResponses;

    private VenueAmenityService $amenityService;

    public function __construct(VenueAmenityService $amenityService)
    {
        $this->amenityService = $amenityService;
    }

    /**
     * Display venue amenities
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @queryParam amenity_type string optional Filter by amenity type (equipment, furniture, infrastructure, service, restriction). Example: equipment
     * @queryParam is_active boolean optional Filter by active status. Example: true
     * @queryParam included_in_booking boolean optional Filter by included in booking. Example: true
     * @queryParam requires_advance_notice boolean optional Filter by advance notice requirement. Example: false
     */
    public function index(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_amenities')) {
                return $this->error('You do not have permission to view venue amenities.', 403);
            }

            $request->validate([
                'amenity_type' => 'nullable|in:equipment,furniture,infrastructure,service,restriction',
                'is_active' => 'boolean',
                'included_in_booking' => 'boolean',
                'requires_advance_notice' => 'boolean',
                'per_page' => 'integer|min:1|max:100',
                'sort_by' => 'nullable|in:name,amenity_type,sort_order,created_at',
                'sort_direction' => 'nullable|in:asc,desc',
            ]);

            $query = VenueAmenity::where('service_location_id', $location->id);

            // Apply filters
            if ($request->has('amenity_type')) {
                $query->where('amenity_type', $request->input('amenity_type'));
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('included_in_booking')) {
                $query->where('included_in_booking', $request->boolean('included_in_booking'));
            }

            if ($request->has('requires_advance_notice')) {
                $query->where('requires_advance_notice', $request->boolean('requires_advance_notice'));
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'sort_order');
            $sortDirection = $request->input('sort_direction', 'asc');

            if ($sortBy === 'sort_order') {
                $query->orderBy('sort_order')->orderBy('name');
            } else {
                $query->orderBy($sortBy, $sortDirection);
            }

            $perPage = $request->integer('per_page', 15);
            $amenities = $query->paginate($perPage);

            // Get amenity statistics
            $stats = $this->amenityService->getAmenitiesStats($location);

            Log::info('Admin viewed venue amenities', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'filters' => $request->only(['amenity_type', 'is_active', 'included_in_booking']),
                'total_amenities' => $amenities->total(),
            ]);

            return VenueAmenityResource::collection($amenities)->additional([
                'message' => 'Venue amenities retrieved successfully',
                'status' => 200,
                'location_info' => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'type' => $location->type,
                ],
                'statistics' => $stats,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve venue amenities', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Display specific venue amenity
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam amenity integer required The amenity ID. Example: 1
     */
    public function show(Request $request, ServiceLocation $location, VenueAmenity $amenity)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('view_venue_amenities')) {
                return $this->error('You do not have permission to view venue amenities.', 403);
            }

            // Verify the amenity belongs to the specified location
            if ($amenity->service_location_id !== $location->id) {
                return $this->error('Amenity does not belong to the specified location.', 400);
            }

            // Get usage statistics and booking requirements
            $usageStats = $this->amenityService->getAmenityUsageStats($amenity);
            $requirements = $this->amenityService->getAmenityRequirements($amenity);

            Log::info('Admin viewed venue amenity', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'amenity_id' => $amenity->id,
            ]);

            return $this->ok('Venue amenity retrieved successfully', [
                'amenity' => new VenueAmenityResource($amenity),
                'usage_stats' => $usageStats,
                'requirements' => $requirements,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve venue amenity', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create new venue amenity
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     */
    public function store(StoreVenueAmenityRequest $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('create_venue_amenities')) {
                return $this->error('You do not have permission to create venue amenities.', 403);
            }

            $data = $request->validated();
            $data['service_location_id'] = $location->id;

            return DB::transaction(function () use ($data, $location, $user) {
                // Check for duplicate amenity names within the same location
                $existingAmenity = VenueAmenity::where('service_location_id', $location->id)
                    ->where('name', $data['name'])
                    ->where('amenity_type', $data['amenity_type'])
                    ->first();

                if ($existingAmenity) {
                    return $this->error(
                        'An amenity with this name and type already exists for this location.',
                        409
                    );
                }

                // Auto-assign sort order if not provided
                if (!isset($data['sort_order'])) {
                    $maxSortOrder = VenueAmenity::where('service_location_id', $location->id)
                        ->where('amenity_type', $data['amenity_type'])
                        ->max('sort_order');
                    $data['sort_order'] = ($maxSortOrder ?? 0) + 10;
                }

                // Create amenity
                $amenity = $this->amenityService->createAmenity($data);

                Log::info('Admin created venue amenity', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'amenity_id' => $amenity->id,
                    'amenity_type' => $amenity->amenity_type,
                    'amenity_name' => $amenity->name,
                ]);

                return $this->created(
                    'Venue amenity created successfully',
                    new VenueAmenityResource($amenity)
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to create venue amenity', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Update venue amenity
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam amenity integer required The amenity ID. Example: 1
     */
    public function update(UpdateVenueAmenityRequest $request, ServiceLocation $location, VenueAmenity $amenity)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('edit_venue_amenities')) {
                return $this->error('You do not have permission to edit venue amenities.', 403);
            }

            // Verify the amenity belongs to the specified location
            if ($amenity->service_location_id !== $location->id) {
                return $this->error('Amenity does not belong to the specified location.', 400);
            }

            $data = $request->validated();

            return DB::transaction(function () use ($amenity, $data, $location, $user) {
                // Check for duplicate names if name is being changed
                if (isset($data['name']) && $data['name'] !== $amenity->name) {
                    $existingAmenity = VenueAmenity::where('service_location_id', $location->id)
                        ->where('name', $data['name'])
                        ->where('amenity_type', $data['amenity_type'] ?? $amenity->amenity_type)
                        ->where('id', '!=', $amenity->id)
                        ->first();

                    if ($existingAmenity) {
                        return $this->error(
                            'An amenity with this name and type already exists for this location.',
                            409
                        );
                    }
                }

                // Check impact on existing bookings if availability is being changed
                $bookingImpact = null;
                if (isset($data['is_active']) && !$data['is_active'] && $amenity->is_active) {
                    $bookingImpact = $this->amenityService->assessDeactivationImpact($amenity);

                    if ($bookingImpact['has_conflicts'] && !$request->boolean('force_update', false)) {
                        return $this->error(
                            'Deactivating this amenity affects existing bookings. Use force_update=true to proceed.',
                            409,
                            ['booking_impact' => $bookingImpact]
                        );
                    }
                }

                // Update amenity
                $updatedAmenity = $this->amenityService->updateAmenity($amenity, $data);

                // Handle affected bookings if forced update
                if ($bookingImpact && $bookingImpact['has_conflicts'] && $request->boolean('force_update', false)) {
                    $this->amenityService->handleAffectedBookings(
                        $bookingImpact['affected_bookings'],
                        $user
                    );
                }

                Log::info('Admin updated venue amenity', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'amenity_id' => $amenity->id,
                    'updated_fields' => array_keys($data),
                    'force_update' => $request->boolean('force_update', false),
                ]);

                return $this->ok(
                    'Venue amenity updated successfully',
                    [
                        'amenity' => new VenueAmenityResource($updatedAmenity),
                        'booking_impact' => $bookingImpact,
                    ]
                );
            });

        } catch (Exception $e) {
            Log::error('Failed to update venue amenity', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete venue amenity
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @urlParam amenity integer required The amenity ID. Example: 1
     */
    public function destroy(Request $request, ServiceLocation $location, VenueAmenity $amenity)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('delete_venue_amenities')) {
                return $this->error('You do not have permission to delete venue amenities.', 403);
            }

            // Verify the amenity belongs to the specified location
            if ($amenity->service_location_id !== $location->id) {
                return $this->error('Amenity does not belong to the specified location.', 400);
            }

            return DB::transaction(function () use ($amenity, $location, $user, $request) {
                // Check impact on existing bookings
                $bookingImpact = $this->amenityService->assessDeletionImpact($amenity);

                if ($bookingImpact['has_conflicts']) {
                    Log::warning('Amenity deletion affects existing bookings', [
                        'admin_id' => $user->id,
                        'amenity_id' => $amenity->id,
                        'affected_bookings' => count($bookingImpact['affected_bookings']),
                    ]);

                    if (!$request->boolean('force_delete', false)) {
                        return $this->error(
                            'Deletion affects existing bookings that use this amenity. Use force_delete=true to proceed.',
                            409,
                            ['booking_impact' => $bookingImpact]
                        );
                    }
                }

                // Delete amenity
                $this->amenityService->deleteAmenity($amenity);

                // Handle affected bookings if forced deletion
                if ($bookingImpact['has_conflicts'] && $request->boolean('force_delete', false)) {
                    $this->amenityService->handleAffectedBookings(
                        $bookingImpact['affected_bookings'],
                        $user
                    );
                }

                Log::info('Admin deleted venue amenity', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'amenity_id' => $amenity->id,
                    'amenity_name' => $amenity->name,
                    'amenity_type' => $amenity->amenity_type,
                    'force_delete' => $request->boolean('force_delete', false),
                ]);

                return $this->ok('Venue amenity deleted successfully', [
                    'booking_impact' => $bookingImpact,
                ]);
            });

        } catch (Exception $e) {
            Log::error('Failed to delete venue amenity', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'amenity_id' => $amenity->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Bulk update amenities
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @bodyParam amenity_ids array required Array of amenity IDs. Example: [1,2,3]
     * @bodyParam action string required Action to perform (activate, deactivate, delete, update_sort). Example: activate
     * @bodyParam sort_orders array optional New sort orders (for update_sort action). Example: [10,20,30]
     */
    public function bulkAction(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('edit_venue_amenities')) {
                return $this->error('You do not have permission to perform bulk amenity actions.', 403);
            }

            $request->validate([
                'amenity_ids' => 'required|array|min:1|max:50',
                'amenity_ids.*' => 'exists:venue_amenities,id',
                'action' => 'required|in:activate,deactivate,delete,update_sort',
                'sort_orders' => 'required_if:action,update_sort|array',
                'sort_orders.*' => 'integer|min:0',
                'force_action' => 'boolean',
            ]);

            $amenityIds = $request->input('amenity_ids');
            $action = $request->input('action');
            $forceAction = $request->boolean('force_action', false);

            return DB::transaction(function () use ($amenityIds, $action, $forceAction, $location, $user, $request) {
                $amenities = VenueAmenity::whereIn('id', $amenityIds)
                    ->where('service_location_id', $location->id)
                    ->get();

                if ($amenities->count() !== count($amenityIds)) {
                    return $this->error('Some amenities not found or do not belong to this location.', 400);
                }

                $results = $this->amenityService->performBulkAction(
                    $amenities,
                    $action,
                    $forceAction,
                    $user,
                    $request->input('sort_orders', [])
                );

                Log::info('Admin performed bulk amenity action', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'action' => $action,
                    'amenity_count' => count($amenityIds),
                    'success_count' => $results['success_count'],
                    'error_count' => $results['error_count'],
                ]);

                return $this->ok("Bulk {$action} completed", $results);
            });

        } catch (Exception $e) {
            Log::error('Failed to perform bulk amenity action', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get amenity categories and templates
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @queryParam amenity_type string optional Filter templates by type. Example: equipment
     */
    public function getTemplates(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('create_venue_amenities')) {
                return $this->error('You do not have permission to view amenity templates.', 403);
            }

            $request->validate([
                'amenity_type' => 'nullable|in:equipment,furniture,infrastructure,service,restriction',
            ]);

            $amenityType = $request->input('amenity_type');

            $templates = $this->amenityService->getAmenityTemplates($location, $amenityType);

            Log::info('Admin viewed amenity templates', [
                'admin_id' => $user->id,
                'location_id' => $location->id,
                'amenity_type' => $amenityType,
            ]);

            return $this->ok('Amenity templates retrieved successfully', $templates);

        } catch (Exception $e) {
            Log::error('Failed to retrieve amenity templates', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create amenities from template
     *
     * @group Admin - Venue Management
     * @authenticated
     *
     * @urlParam location integer required The service location ID. Example: 1
     * @bodyParam template_name string required Template name. Example: balloon_arch_venue
     * @bodyParam amenity_selections array optional Specific amenities to create. Example: ["chairs","tables","power_outlets"]
     */
    public function createFromTemplate(Request $request, ServiceLocation $location)
    {
        try {
            $user = $request->user();

            if (!$user->hasPermission('create_venue_amenities')) {
                return $this->error('You do not have permission to create amenities from template.', 403);
            }

            $request->validate([
                'template_name' => 'required|string|in:balloon_arch_venue,wedding_venue,corporate_venue,outdoor_venue',
                'amenity_selections' => 'nullable|array',
                'amenity_selections.*' => 'string',
                'overwrite_existing' => 'boolean',
            ]);

            $templateName = $request->input('template_name');
            $selections = $request->input('amenity_selections', []);
            $overwrite = $request->boolean('overwrite_existing', false);

            return DB::transaction(function () use ($templateName, $selections, $overwrite, $location, $user) {
                $results = $this->amenityService->createFromTemplate(
                    $location,
                    $templateName,
                    $selections,
                    $overwrite
                );

                Log::info('Admin created amenities from template', [
                    'admin_id' => $user->id,
                    'location_id' => $location->id,
                    'template_name' => $templateName,
                    'created_count' => $results['created_count'],
                    'skipped_count' => $results['skipped_count'],
                ]);

                return $this->created('Amenities created from template successfully', $results);
            });

        } catch (Exception $e) {
            Log::error('Failed to create amenities from template', [
                'admin_id' => $request->user()?->id,
                'location_id' => $location->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}

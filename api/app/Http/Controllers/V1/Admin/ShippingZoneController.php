<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Requests\V1\StoreShippingZoneRequest;
use App\Requests\V1\UpdateShippingZoneRequest;
use App\Resources\V1\ShippingZoneResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;

class ShippingZoneController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve paginated list of shipping zones
     *
     * Get a paginated list of shipping zones with optional filtering by name, country, and active status.
     * Shipping zones define geographic regions with specific shipping rules and available methods.
     * Can include associated shipping methods and counts for comprehensive zone management.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @queryParam name string optional Filter by zone name (partial match supported). Example: europe
     * @queryParam country string optional Filter by country code (searches within countries JSON). Example: GB
     * @queryParam is_active boolean optional Filter by active status (true/false). Example: true
     * @queryParam with_methods boolean optional Include associated shipping methods. Example: true
     * @queryParam with_counts boolean optional Include methods and rates counts. Example: true
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of zones per page (max 100). Default: 15. Example: 20
     *
     * @response 200 scenario="Success with shipping zones" {
     *   "message": "Shipping zones retrieved successfully.",
     *   "status": 200,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "United Kingdom",
     *         "description": "Mainland UK delivery zone",
     *         "countries": ["GB"],
     *         "country_names": ["United Kingdom"],
     *         "postcodes": null,
     *         "excluded_postcodes": ["BT", "GY", "JE", "IM"],
     *         "is_active": true,
     *         "sort_order": 1,
     *         "methods_count": 4,
     *         "rates_count": 12,
     *         "available_methods": [
     *           {
     *             "id": 1,
     *             "name": "Standard Delivery",
     *             "carrier": "Royal Mail",
     *             "is_active": true,
     *             "sort_order": 0
     *           },
     *           {
     *             "id": 2,
     *             "name": "Express Delivery",
     *             "carrier": "DPD",
     *             "is_active": true,
     *             "sort_order": 1
     *           }
     *         ],
     *         "created_at": "2025-01-01T00:00:00.000000Z",
     *         "updated_at": "2025-01-10T14:30:00.000000Z"
     *       },
     *       {
     *         "id": 2,
     *         "name": "European Union",
     *         "description": "EU member countries",
     *         "countries": ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE"],
     *         "country_names": ["Austria", "Belgium", "Bulgaria", "Croatia", "Cyprus", "Czech Republic", "Denmark", "Estonia", "Finland", "France", "Germany", "Greece", "Hungary", "Ireland", "Italy", "Latvia", "Lithuania", "Luxembourg", "Malta", "Netherlands", "Poland", "Portugal", "Romania", "Slovakia", "Slovenia", "Spain", "Sweden"],
     *         "postcodes": null,
     *         "excluded_postcodes": null,
     *         "is_active": true,
     *         "sort_order": 3,
     *         "methods_count": 1,
     *         "rates_count": 6,
     *         "created_at": "2025-01-01T00:00:00.000000Z",
     *         "updated_at": "2025-01-01T00:00:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 8,
     *     "last_page": 1
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $query = ShippingZone::query();

        // Apply name filter with partial matching
        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        // Apply country filter - searches within countries JSON array
        if ($request->has('country')) {
            $query->whereJsonContains('countries', strtoupper($request->input('country')));
        }

        // Apply active status filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Include associated shipping methods if requested
        if ($request->boolean('with_methods')) {
            $query->with(['methods' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }]);
        }

        // Include counts if requested
        if ($request->boolean('with_counts')) {
            $query->withCount(['methods', 'rates']);
        }

        $perPage = min($request->input('per_page', 15), 100);
        $zones = $query->ordered()->paginate($perPage);

        return ShippingZoneResource::collection($zones)->additional([
            'message' => 'Shipping zones retrieved successfully.',
            'status' => 200
        ]);
    }

    /**
     * Create a new shipping zone
     *
     * Create a new shipping zone with countries, postcodes, and configuration.
     * Zones define geographic regions that can have specific shipping methods and rates.
     * Countries must be provided as 2-letter ISO codes, and postcodes support wildcards.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @bodyParam name string required Unique name for the shipping zone. Example: Nordic Countries
     * @bodyParam description string optional Detailed description of the zone. Example: Nordic and Scandinavian countries
     * @bodyParam countries array required Array of 2-letter country codes. Example: ["NO", "SE", "DK", "FI", "IS"]
     * @bodyParam countries.* string required Each country code must be exactly 2 uppercase letters. Example: NO
     * @bodyParam postcodes array optional Array of postcode patterns (supports wildcards). Example: ["0*", "1*", "2*"]
     * @bodyParam postcodes.* string optional Each postcode pattern (max 50 characters). Example: 0*
     * @bodyParam excluded_postcodes array optional Array of excluded postcode patterns. Example: ["0001", "0002"]
     * @bodyParam excluded_postcodes.* string optional Each excluded postcode pattern (max 50 characters). Example: 0001
     * @bodyParam is_active boolean optional Whether the zone is active. Default: true. Example: true
     * @bodyParam sort_order integer optional Display order (lower numbers first). Default: 0. Example: 5
     *
     * @response 200 scenario="Shipping zone created successfully" {
     *   "message": "Shipping zone created successfully.",
     *   "data": {
     *     "id": 9,
     *     "name": "Nordic Countries",
     *     "description": "Nordic and Scandinavian countries",
     *     "countries": ["NO", "SE", "DK", "FI", "IS"],
     *     "country_names": ["Norway", "Sweden", "Denmark", "Finland", "Iceland"],
     *     "postcodes": ["0*", "1*", "2*"],
     *     "excluded_postcodes": ["0001", "0002"],
     *     "is_active": true,
     *     "sort_order": 5,
     *     "methods_count": 0,
     *     "rates_count": 0,
     *     "created_at": "2025-01-15T14:30:00.000000Z",
     *     "updated_at": "2025-01-15T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "A shipping zone with this name already exists.",
     *     "The countries field is required.",
     *     "At least one country must be selected.",
     *     "Country codes must be uppercase letters only (e.g., GB, US)."
     *   ]
     * }
     */
    public function store(StoreShippingZoneRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        $zone = ShippingZone::create($data);

        return $this->ok(
            'Shipping zone created successfully.',
            new ShippingZoneResource($zone)
        );
    }

    /**
     * Retrieve a specific shipping zone
     *
     * Get detailed information about a specific shipping zone including its methods and rates count.
     * Optionally includes associated shipping methods with their zone-specific settings.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone to retrieve. Example: 9
     * @queryParam with_methods boolean optional Include associated shipping methods. Example: true
     *
     * @response 200 scenario="Shipping zone found" {
     *   "message": "Shipping zone retrieved successfully.",
     *   "data": {
     *     "id": 9,
     *     "name": "Nordic Countries",
     *     "description": "Nordic and Scandinavian countries",
     *     "countries": ["NO", "SE", "DK", "FI", "IS"],
     *     "country_names": ["Norway", "Sweden", "Denmark", "Finland", "Iceland"],
     *     "postcodes": ["0*", "1*", "2*"],
     *     "excluded_postcodes": ["0001", "0002"],
     *     "is_active": true,
     *     "sort_order": 5,
     *     "methods_count": 2,
     *     "rates_count": 8,
     *     "available_methods": [
     *       {
     *         "id": 3,
     *         "name": "International Standard",
     *         "carrier": "Royal Mail",
     *         "is_active": true,
     *         "sort_order": 0
     *       },
     *       {
     *         "id": 4,
     *         "name": "International Express",
     *         "carrier": "DHL",
     *         "is_active": true,
     *         "sort_order": 1
     *       }
     *     ],
     *     "created_at": "2025-01-15T14:30:00.000000Z",
     *     "updated_at": "2025-01-15T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping zone not found" {
     *   "message": "No query results for model [App\\Models\\ShippingZone] 999"
     * }
     */
    public function show(Request $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        // Load counts for methods and rates
        $shippingZone->loadCount(['methods', 'rates']);

        // Include associated methods if requested
        if ($request->boolean('with_methods')) {
            $shippingZone->load(['methods' => function ($query) {
                $query->orderBy('shipping_zones_methods.sort_order')
                    ->orderBy('name');
            }]);
        }

        return $this->ok(
            'Shipping zone retrieved successfully.',
            new ShippingZoneResource($shippingZone)
        );
    }

    /**
     * Update an existing shipping zone
     *
     * Update shipping zone details including countries, postcodes, and configuration.
     * Changes to active zones may affect existing shipping calculations and should be used carefully.
     * Country codes will be automatically converted to uppercase.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone to update. Example: 9
     *
     * @bodyParam name string optional Unique name for the shipping zone. Example: Nordic & Baltic Countries
     * @bodyParam description string optional Detailed description of the zone. Example: Nordic, Scandinavian, and Baltic countries
     * @bodyParam countries array optional Array of 2-letter country codes. Example: ["NO", "SE", "DK", "FI", "IS", "LT", "LV", "EE"]
     * @bodyParam countries.* string optional Each country code must be exactly 2 uppercase letters. Example: LT
     * @bodyParam postcodes array optional Array of postcode patterns (supports wildcards). Example: ["0*", "1*", "2*", "3*"]
     * @bodyParam postcodes.* string optional Each postcode pattern (max 50 characters). Example: 3*
     * @bodyParam excluded_postcodes array optional Array of excluded postcode patterns. Example: ["0001", "0002", "3999"]
     * @bodyParam excluded_postcodes.* string optional Each excluded postcode pattern (max 50 characters). Example: 3999
     * @bodyParam is_active boolean optional Whether the zone is active. Example: true
     * @bodyParam sort_order integer optional Display order (lower numbers first). Example: 4
     *
     * @response 200 scenario="Shipping zone updated successfully" {
     *   "message": "Shipping zone updated successfully.",
     *   "data": {
     *     "id": 9,
     *     "name": "Nordic & Baltic Countries",
     *     "description": "Nordic, Scandinavian, and Baltic countries",
     *     "countries": ["NO", "SE", "DK", "FI", "IS", "LT", "LV", "EE"],
     *     "country_names": ["Norway", "Sweden", "Denmark", "Finland", "Iceland", "Lithuania", "Latvia", "Estonia"],
     *     "postcodes": ["0*", "1*", "2*", "3*"],
     *     "excluded_postcodes": ["0001", "0002", "3999"],
     *     "is_active": true,
     *     "sort_order": 4,
     *     "methods_count": 2,
     *     "rates_count": 8,
     *     "created_at": "2025-01-15T14:30:00.000000Z",
     *     "updated_at": "2025-01-15T15:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping zone not found" {
     *   "message": "No query results for model [App\\Models\\ShippingZone] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "A shipping zone with this name already exists.",
     *     "Country codes must be uppercase letters only (e.g., GB, US)."
     *   ]
     * }
     */
    public function update(UpdateShippingZoneRequest $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        $shippingZone->update($data);

        return $this->ok(
            'Shipping zone updated successfully.',
            new ShippingZoneResource($shippingZone)
        );
    }

    /**
     * Delete a shipping zone
     *
     * Delete a shipping zone if it has no associated rates. This also removes all
     * method associations. Cannot delete zones that have shipping rates configured
     * as this would affect shipping calculations.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone to delete. Example: 9
     *
     * @response 200 scenario="Shipping zone deleted successfully" {
     *   "message": "Shipping zone deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot delete zone with rates" {
     *   "message": "Cannot delete shipping zone that has associated rates."
     * }
     *
     * @response 404 scenario="Shipping zone not found" {
     *   "message": "No query results for model [App\\Models\\ShippingZone] 999"
     * }
     */
    public function destroy(Request $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        // Check if zone has associated rates
        if ($shippingZone->rates()->exists()) {
            return $this->error('Cannot delete shipping zone that has associated rates.', 400);
        }

        // Remove method associations if they exist
        if ($shippingZone->methods()->exists()) {
            $shippingZone->methods()->detach();
        }

        $shippingZone->delete();

        return $this->ok('Shipping zone deleted successfully.');
    }

    /**
     * Activate a shipping zone
     *
     * Activate a shipping zone to make it available for shipping calculations.
     * Activated zones will be considered when determining available shipping methods
     * and rates for customer addresses.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone to activate. Example: 9
     *
     * @response 200 scenario="Shipping zone activated successfully" {
     *   "message": "Shipping zone activated successfully.",
     *   "data": {
     *     "id": 9,
     *     "name": "Nordic Countries",
     *     "is_active": true,
     *     "updated_at": "2025-01-15T16:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping zone not found" {
     *   "message": "No query results for model [App\\Models\\ShippingZone] 999"
     * }
     */
    public function activate(Request $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingZone->update(['is_active' => true]);

        return $this->ok(
            'Shipping zone activated successfully.',
            new ShippingZoneResource($shippingZone)
        );
    }

    /**
     * Deactivate a shipping zone
     *
     * Deactivate a shipping zone to remove it from shipping calculations.
     * Deactivated zones will not be considered when determining available shipping
     * methods and rates, but existing orders remain unaffected.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone to deactivate. Example: 9
     *
     * @response 200 scenario="Shipping zone deactivated successfully" {
     *   "message": "Shipping zone deactivated successfully.",
     *   "data": {
     *     "id": 9,
     *     "name": "Nordic Countries",
     *     "is_active": false,
     *     "updated_at": "2025-01-15T16:05:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping zone not found" {
     *   "message": "No query results for model [App\\Models\\ShippingZone] 999"
     * }
     */
    public function deactivate(Request $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingZone->update(['is_active' => false]);

        return $this->ok(
            'Shipping zone deactivated successfully.',
            new ShippingZoneResource($shippingZone)
        );
    }

    /**
     * Reorder shipping zones
     *
     * Update the display order of shipping zones. This affects the priority in which
     * zones are evaluated for shipping calculations. Zones with lower sort_order values
     * are processed first and take precedence in case of overlapping coverage.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @bodyParam zones array required Array of shipping zone IDs in desired order. Example: [1, 2, 9, 3]
     * @bodyParam zones.* integer required Each zone ID must be a valid shipping zone ID. Example: 1
     *
     * @response 200 scenario="Shipping zones reordered successfully" {
     *   "message": "Shipping zones reordered successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The zones field is required.",
     *     "The zones must be an array.",
     *     "The zones.0 must be an integer.",
     *     "The zones.1 must exist in shipping_zones table."
     *   ]
     * }
     */
    public function reorder(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'zones' => ['required', 'array'],
            'zones.*' => ['integer', 'exists:shipping_zones,id'],
        ]);

        $zoneIds = $request->input('zones');

        // Update sort_order for each zone based on its position in the array
        foreach ($zoneIds as $index => $zoneId) {
            ShippingZone::where('id', $zoneId)->update(['sort_order' => $index]);
        }

        return $this->ok('Shipping zones reordered successfully.');
    }

    /**
     * Attach shipping method to zone
     *
     * Associate a shipping method with a shipping zone, making that method available
     * for addresses within the zone. Can specify if the method is active within the zone
     * and set the display order for methods within the zone.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone. Example: 9
     *
     * @bodyParam method_id integer required The ID of the shipping method to attach. Example: 3
     * @bodyParam is_active boolean optional Whether the method is active in this zone. Default: true. Example: true
     * @bodyParam sort_order integer optional Display order within the zone. Default: 0. Example: 1
     *
     * @response 200 scenario="Shipping method attached successfully" {
     *   "message": "Shipping method attached to zone successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Method already attached" {
     *   "message": "Shipping method is already attached to this zone."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The method id field is required.",
     *     "The method id must be an integer.",
     *     "The method id must exist in shipping_methods table."
     *   ]
     * }
     */
    public function attachMethod(Request $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $methodId = $request->input('method_id');

        // Check if method is already attached to this zone
        if ($shippingZone->methods()->where('shipping_method_id', $methodId)->exists()) {
            return $this->error('Shipping method is already attached to this zone.', 400);
        }

        // Attach method with pivot data
        $shippingZone->methods()->attach($methodId, [
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return $this->ok('Shipping method attached to zone successfully.');
    }

    /**
     * Detach shipping method from zone
     *
     * Remove the association between a shipping method and a shipping zone.
     * This makes the method unavailable for addresses within the zone.
     * Also removes any rates configured for this method-zone combination.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone. Example: 9
     * @urlParam shippingMethod integer required The ID of the shipping method to detach. Example: 3
     *
     * @response 200 scenario="Shipping method detached successfully" {
     *   "message": "Shipping method detached from zone successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Method not attached to zone" {
     *   "message": "Shipping method is not attached to this zone."
     * }
     */
    public function detachMethod(Request $request, ShippingZone $shippingZone, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        // Check if method is attached to this zone
        if (!$shippingZone->methods()->where('shipping_method_id', $shippingMethod->id)->exists()) {
            return $this->error('Shipping method is not attached to this zone.', 404);
        }

        // Detach method
        $shippingZone->methods()->detach($shippingMethod->id);

        return $this->ok('Shipping method detached from zone successfully.');
    }

    /**
     * Update method settings within zone
     *
     * Update the settings for a shipping method within a specific zone.
     * This allows fine-tuning of method availability and display order
     * on a per-zone basis without affecting the method globally.
     *
     * @group Shipping Zone Management
     * @authenticated
     *
     * @urlParam shippingZone integer required The ID of the shipping zone. Example: 9
     * @urlParam shippingMethod integer required The ID of the shipping method. Example: 3
     *
     * @bodyParam is_active boolean optional Whether the method is active in this zone. Example: false
     * @bodyParam sort_order integer optional Display order within the zone. Example: 2
     *
     * @response 200 scenario="Method settings updated successfully" {
     *   "message": "Shipping method settings updated successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Method not attached to zone" {
     *   "message": "Shipping method is not attached to this zone."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The sort order must be an integer.",
     *     "The sort order must be at least 0."
     *   ]
     * }
     */
    public function updateMethodSettings(Request $request, ShippingZone $shippingZone, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Check if method is attached to this zone
        $pivot = $shippingZone->methods()->where('shipping_method_id', $shippingMethod->id)->first();

        if (!$pivot) {
            return $this->error('Shipping method is not attached to this zone.', 404);
        }

        // Build update data
        $updateData = [];
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }
        if ($request->has('sort_order')) {
            $updateData['sort_order'] = $request->input('sort_order');
        }

        // Update pivot settings
        $shippingZone->methods()->updateExistingPivot($shippingMethod->id, $updateData);

        return $this->ok('Shipping method settings updated successfully.');
    }
}

<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Requests\V1\StoreShippingRateRequest;
use App\Requests\V1\UpdateShippingRateRequest;
use App\Resources\V1\ShippingRateResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;

class ShippingRateController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve paginated list of shipping rates
     *
     * Get a paginated list of all shipping rates in the system with comprehensive filtering options.
     * This endpoint supports filtering by shipping method, zone, active status, rate ranges, and
     * weight/total thresholds. Essential for managing shipping costs and rate structures.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @queryParam shipping_method_id integer optional Filter by shipping method ID. Example: 1
     * @queryParam shipping_zone_id integer optional Filter by shipping zone ID. Example: 2
     * @queryParam is_active boolean optional Filter by active status. Example: true
     * @queryParam min_rate numeric optional Filter rates above this amount (in pounds). Example: 5.00
     * @queryParam max_rate numeric optional Filter rates below this amount (in pounds). Example: 15.00
     * @queryParam weight_range numeric optional Filter rates applicable to this weight (in kg). Example: 2.5
     * @queryParam total_range numeric optional Filter rates applicable to this order total (in pounds). Example: 50.00
     * @queryParam with_relationships boolean optional Include shipping method and zone details. Example: true
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of rates per page (max 100). Default: 15. Example: 25
     *
     * @response 200 scenario="Success with shipping rates" {
     *   "data": [
     *     {
     *       "id": 1,
     *       "shipping_method_id": 1,
     *       "shipping_zone_id": 1,
     *       "min_weight": 0,
     *       "max_weight": 2000,
     *       "min_total": 0,
     *       "max_total": 5000,
     *       "rate": 599,
     *       "rate_formatted": "£5.99",
     *       "free_threshold": 10000,
     *       "free_threshold_formatted": "£100.00",
     *       "is_active": true,
     *       "shipping_method": {
     *         "id": 1,
     *         "name": "Standard Delivery",
     *         "carrier": "Royal Mail",
     *         "service_code": "tracked-48"
     *       },
     *       "shipping_zone": {
     *         "id": 1,
     *         "name": "UK Mainland",
     *         "countries": ["GB"],
     *         "is_active": true
     *       },
     *       "created_at": "2025-01-10T09:00:00.000000Z",
     *       "updated_at": "2025-01-14T14:30:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "shipping_method_id": 1,
     *       "shipping_zone_id": 1,
     *       "min_weight": 2000,
     *       "max_weight": 5000,
     *       "min_total": 0,
     *       "max_total": 5000,
     *       "rate": 799,
     *       "rate_formatted": "£7.99",
     *       "free_threshold": 10000,
     *       "free_threshold_formatted": "£100.00",
     *       "is_active": true,
     *       "shipping_method": {
     *         "id": 1,
     *         "name": "Standard Delivery",
     *         "carrier": "Royal Mail",
     *         "service_code": "tracked-48"
     *       },
     *       "shipping_zone": {
     *         "id": 1,
     *         "name": "UK Mainland",
     *         "countries": ["GB"],
     *         "is_active": true
     *       },
     *       "created_at": "2025-01-10T09:15:00.000000Z",
     *       "updated_at": "2025-01-14T14:30:00.000000Z"
     *     }
     *   ],
     *   "current_page": 1,
     *   "per_page": 15,
     *   "total": 47,
     *   "last_page": 4,
     *   "message": "Shipping rates retrieved successfully.",
     *   "status": 200
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $query = ShippingRate::query();

        if ($request->has('shipping_method_id')) {
            $query->where('shipping_method_id', $request->input('shipping_method_id'));
        }

        if ($request->has('shipping_zone_id')) {
            $query->where('shipping_zone_id', $request->input('shipping_zone_id'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('min_rate') || $request->has('max_rate')) {
            $minRate = $request->has('min_rate') ? (int) round($request->input('min_rate') * 100) : 0;
            $maxRate = $request->has('max_rate') ? (int) round($request->input('max_rate') * 100) : PHP_INT_MAX;
            $query->whereBetween('rate', [$minRate, $maxRate]);
        }

        if ($request->has('weight_range')) {
            $weight = (float) $request->input('weight_range');
            $query->forWeight($weight);
        }

        if ($request->has('total_range')) {
            $total = (int) round($request->input('total_range') * 100);
            $query->forTotal($total);
        }

        if ($request->boolean('with_relationships')) {
            $query->with(['shippingMethod', 'shippingZone']);
        }

        $perPage = min($request->input('per_page', 15), 100);
        $rates = $query->active()->paginate($perPage);

        return ShippingRateResource::collection($rates)->additional([
            'message' => 'Shipping rates retrieved successfully.',
            'status' => 200
        ]);
    }

    /**
     * Create a new shipping rate
     *
     * Create a new shipping rate for a specific shipping method and zone combination.
     * The system validates that the rate doesn't overlap with existing rates and that
     * the weight and total thresholds are logically consistent.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @bodyParam shipping_method_id integer required The ID of the shipping method. Example: 1
     * @bodyParam shipping_zone_id integer required The ID of the shipping zone. Example: 1
     * @bodyParam min_weight numeric required Minimum weight in grams. Example: 0
     * @bodyParam max_weight numeric optional Maximum weight in grams (null for unlimited). Example: 2000
     * @bodyParam min_total numeric required Minimum order total in pounds. Example: 0
     * @bodyParam max_total numeric optional Maximum order total in pounds (null for unlimited). Example: 50.00
     * @bodyParam rate numeric required Shipping rate in pounds. Example: 5.99
     * @bodyParam free_threshold numeric optional Order total for free shipping in pounds. Example: 100.00
     * @bodyParam is_active boolean optional Whether the rate is active. Default: true. Example: true
     * @bodyParam with_relationships boolean optional Include related method and zone in response. Example: true
     *
     * @response 200 scenario="Shipping rate created successfully" {
     *   "message": "Shipping rate created successfully.",
     *   "data": {
     *     "id": 48,
     *     "shipping_method_id": 1,
     *     "shipping_zone_id": 1,
     *     "min_weight": 0,
     *     "max_weight": 2000,
     *     "min_total": 0,
     *     "max_total": 5000,
     *     "rate": 599,
     *     "rate_formatted": "£5.99",
     *     "free_threshold": 10000,
     *     "free_threshold_formatted": "£100.00",
     *     "is_active": true,
     *     "shipping_method": {
     *       "id": 1,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail",
     *       "service_code": "tracked-48"
     *     },
     *     "shipping_zone": {
     *       "id": 1,
     *       "name": "UK Mainland",
     *       "countries": ["GB"],
     *       "is_active": true
     *     },
     *     "created_at": "2025-01-15T10:30:00.000000Z",
     *     "updated_at": "2025-01-15T10:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping method id field is required.",
     *     "The shipping zone id field is required.",
     *     "The rate field is required.",
     *     "The max weight must be greater than min weight."
     *   ]
     * }
     */
    public function store(StoreShippingRateRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        $rate = ShippingRate::create($data);

        if ($request->boolean('with_relationships')) {
            $rate->load(['shippingMethod', 'shippingZone']);
        }

        return $this->ok(
            'Shipping rate created successfully.',
            new ShippingRateResource($rate)
        );
    }

    /**
     * Retrieve a specific shipping rate
     *
     * Get detailed information about a specific shipping rate including its weight and total
     * thresholds, pricing, and optionally the related shipping method and zone details.
     * Useful for viewing complete rate configuration.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @urlParam shippingRate integer required The ID of the shipping rate to retrieve. Example: 1
     * @queryParam with_relationships boolean optional Include shipping method and zone details. Example: true
     *
     * @response 200 scenario="Shipping rate found" {
     *   "message": "Shipping rate retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "shipping_method_id": 1,
     *     "shipping_zone_id": 1,
     *     "min_weight": 0,
     *     "max_weight": 2000,
     *     "min_total": 0,
     *     "max_total": 5000,
     *     "rate": 599,
     *     "rate_formatted": "£5.99",
     *     "free_threshold": 10000,
     *     "free_threshold_formatted": "£100.00",
     *     "is_active": true,
     *     "shipping_method": {
     *       "id": 1,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail",
     *       "service_code": "tracked-48",
     *       "estimated_delivery": "2-3 days"
     *     },
     *     "shipping_zone": {
     *       "id": 1,
     *       "name": "UK Mainland",
     *       "countries": ["GB"],
     *       "postcodes": ["SW1A", "E1", "M1"],
     *       "is_active": true
     *     },
     *     "created_at": "2025-01-10T09:00:00.000000Z",
     *     "updated_at": "2025-01-14T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping rate not found" {
     *   "message": "No query results for model [App\\Models\\ShippingRate] 999"
     * }
     */
    public function show(Request $request, ShippingRate $shippingRate)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        if ($request->boolean('with_relationships')) {
            $shippingRate->load(['shippingMethod', 'shippingZone']);
        }

        return $this->ok(
            'Shipping rate retrieved successfully.',
            new ShippingRateResource($shippingRate)
        );
    }

    /**
     * Update an existing shipping rate
     *
     * Update a shipping rate's thresholds, pricing, and configuration. The system validates
     * that the updated rate doesn't create conflicts with existing rates and that the
     * weight and total thresholds remain logically consistent.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @urlParam shippingRate integer required The ID of the shipping rate to update. Example: 1
     *
     * @bodyParam shipping_method_id integer optional The ID of the shipping method. Example: 1
     * @bodyParam shipping_zone_id integer optional The ID of the shipping zone. Example: 1
     * @bodyParam min_weight numeric optional Minimum weight in grams. Example: 0
     * @bodyParam max_weight numeric optional Maximum weight in grams (null for unlimited). Example: 2500
     * @bodyParam min_total numeric optional Minimum order total in pounds. Example: 0
     * @bodyParam max_total numeric optional Maximum order total in pounds (null for unlimited). Example: 75.00
     * @bodyParam rate numeric optional Shipping rate in pounds. Example: 6.99
     * @bodyParam free_threshold numeric optional Order total for free shipping in pounds. Example: 125.00
     * @bodyParam is_active boolean optional Whether the rate is active. Example: true
     * @bodyParam with_relationships boolean optional Include related method and zone in response. Example: true
     *
     * @response 200 scenario="Shipping rate updated successfully" {
     *   "message": "Shipping rate updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "shipping_method_id": 1,
     *     "shipping_zone_id": 1,
     *     "min_weight": 0,
     *     "max_weight": 2500,
     *     "min_total": 0,
     *     "max_total": 7500,
     *     "rate": 699,
     *     "rate_formatted": "£6.99",
     *     "free_threshold": 12500,
     *     "free_threshold_formatted": "£125.00",
     *     "is_active": true,
     *     "shipping_method": {
     *       "id": 1,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail"
     *     },
     *     "shipping_zone": {
     *       "id": 1,
     *       "name": "UK Mainland",
     *       "countries": ["GB"]
     *     },
     *     "updated_at": "2025-01-15T11:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping rate not found" {
     *   "message": "No query results for model [App\\Models\\ShippingRate] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The max weight must be greater than min weight.",
     *     "The max total must be greater than min total.",
     *     "The rate must be a positive number."
     *   ]
     * }
     */
    public function update(UpdateShippingRateRequest $request, ShippingRate $shippingRate)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        $shippingRate->update($data);

        if ($request->boolean('with_relationships')) {
            $shippingRate->load(['shippingMethod', 'shippingZone']);
        }

        return $this->ok(
            'Shipping rate updated successfully.',
            new ShippingRateResource($shippingRate)
        );
    }

    /**
     * Delete a shipping rate
     *
     * Delete a shipping rate from the system. This removes the rate permanently and may
     * affect shipping calculations if this was the only rate for a specific weight/total
     * combination. Use with caution as this cannot be undone.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @urlParam shippingRate integer required The ID of the shipping rate to delete. Example: 1
     *
     * @response 200 scenario="Shipping rate deleted successfully" {
     *   "message": "Shipping rate deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping rate not found" {
     *   "message": "No query results for model [App\\Models\\ShippingRate] 999"
     * }
     */
    public function destroy(Request $request, ShippingRate $shippingRate)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingRate->delete();

        return $this->ok('Shipping rate deleted successfully.');
    }

    /**
     * Activate a shipping rate
     *
     * Activate a shipping rate, making it available for shipping calculations. This enables
     * the rate to be used when determining shipping costs for orders that match its
     * weight and total criteria.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @urlParam shippingRate integer required The ID of the shipping rate to activate. Example: 1
     *
     * @response 200 scenario="Shipping rate activated successfully" {
     *   "message": "Shipping rate activated successfully.",
     *   "data": {
     *     "id": 1,
     *     "shipping_method_id": 1,
     *     "shipping_zone_id": 1,
     *     "rate": 599,
     *     "rate_formatted": "£5.99",
     *     "is_active": true,
     *     "updated_at": "2025-01-15T12:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping rate not found" {
     *   "message": "No query results for model [App\\Models\\ShippingRate] 999"
     * }
     */
    public function activate(Request $request, ShippingRate $shippingRate)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingRate->update(['is_active' => true]);

        return $this->ok(
            'Shipping rate activated successfully.',
            new ShippingRateResource($shippingRate)
        );
    }

    /**
     * Deactivate a shipping rate
     *
     * Deactivate a shipping rate, removing it from shipping calculations. This prevents
     * the rate from being used for new orders while preserving the rate configuration
     * for potential future reactivation.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @urlParam shippingRate integer required The ID of the shipping rate to deactivate. Example: 1
     *
     * @response 200 scenario="Shipping rate deactivated successfully" {
     *   "message": "Shipping rate deactivated successfully.",
     *   "data": {
     *     "id": 1,
     *     "shipping_method_id": 1,
     *     "shipping_zone_id": 1,
     *     "rate": 599,
     *     "rate_formatted": "£5.99",
     *     "is_active": false,
     *     "updated_at": "2025-01-15T12:15:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping rate not found" {
     *   "message": "No query results for model [App\\Models\\ShippingRate] 999"
     * }
     */
    public function deactivate(Request $request, ShippingRate $shippingRate)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingRate->update(['is_active' => false]);

        return $this->ok(
            'Shipping rate deactivated successfully.',
            new ShippingRateResource($shippingRate)
        );
    }

    /**
     * Bulk create shipping rates
     *
     * Create multiple shipping rates in a single request. This is useful for setting up
     * complete rate structures for new shipping methods or zones. The system validates
     * each rate and prevents creation of overlapping rates.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @bodyParam rates array required Array of shipping rates to create (max 50). Example: [{"shipping_method_id": 1, "shipping_zone_id": 1, "min_weight": 0, "max_weight": 1000, "min_total": 0, "max_total": 25.00, "rate": 4.99}, {"shipping_method_id": 1, "shipping_zone_id": 1, "min_weight": 1000, "max_weight": 2000, "min_total": 0, "max_total": 25.00, "rate": 6.99}]
     * @bodyParam rates.*.shipping_method_id integer required The ID of the shipping method. Example: 1
     * @bodyParam rates.*.shipping_zone_id integer required The ID of the shipping zone. Example: 1
     * @bodyParam rates.*.min_weight numeric required Minimum weight in grams. Example: 0
     * @bodyParam rates.*.max_weight numeric optional Maximum weight in grams. Example: 1000
     * @bodyParam rates.*.min_total numeric required Minimum order total in pounds. Example: 0
     * @bodyParam rates.*.max_total numeric optional Maximum order total in pounds. Example: 25.00
     * @bodyParam rates.*.rate numeric required Shipping rate in pounds. Example: 4.99
     * @bodyParam rates.*.free_threshold numeric optional Order total for free shipping in pounds. Example: 100.00
     * @bodyParam rates.*.is_active boolean optional Whether the rate is active. Default: true. Example: true
     *
     * @response 200 scenario="Shipping rates created successfully" {
     *   "message": "15 shipping rates created successfully.",
     *   "data": {
     *     "created_count": 15
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors with conflicts" {
     *   "message": "Some rates could not be created due to conflicts.",
     *   "errors": [
     *     "Rate 0: Overlapping rate already exists for this method/zone combination.",
     *     "Rate 3: The max weight must be greater than min weight."
     *   ]
     * }
     */
    public function bulkCreate(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'rates' => ['required', 'array', 'min:1', 'max:50'],
            'rates.*.shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'rates.*.shipping_zone_id' => ['required', 'integer', 'exists:shipping_zones,id'],
            'rates.*.min_weight' => ['required', 'numeric', 'min:0'],
            'rates.*.max_weight' => ['nullable', 'numeric', 'min:0'],
            'rates.*.min_total' => ['required', 'numeric', 'min:0'],
            'rates.*.max_total' => ['nullable', 'numeric', 'min:0'],
            'rates.*.rate' => ['required', 'numeric', 'min:0'],
            'rates.*.free_threshold' => ['nullable', 'numeric', 'min:0'],
            'rates.*.is_active' => ['nullable', 'boolean'],
        ]);

        $ratesToCreate = [];
        $errors = [];

        foreach ($request->input('rates') as $index => $rateData) {
            $rateData['min_total'] = (int) round($rateData['min_total'] * 100);
            $rateData['max_total'] = isset($rateData['max_total']) ? (int) round($rateData['max_total'] * 100) : null;
            $rateData['rate'] = (int) round($rateData['rate'] * 100);
            $rateData['free_threshold'] = isset($rateData['free_threshold']) ? (int) round($rateData['free_threshold'] * 100) : null;
            $rateData['is_active'] = $rateData['is_active'] ?? true;
            $rateData['created_at'] = now();
            $rateData['updated_at'] = now();

            $existingRate = ShippingRate::where('shipping_method_id', $rateData['shipping_method_id'])
                ->where('shipping_zone_id', $rateData['shipping_zone_id'])
                ->forWeight($rateData['min_weight'])
                ->forTotal($rateData['min_total'])
                ->exists();

            if ($existingRate) {
                $errors[] = "Rate {$index}: Overlapping rate already exists for this method/zone combination.";
                continue;
            }

            $ratesToCreate[] = $rateData;
        }

        if (!empty($errors)) {
            return $this->error('Some rates could not be created due to conflicts.', 422, $errors);
        }

        ShippingRate::insert($ratesToCreate);

        return $this->ok(
            count($ratesToCreate) . ' shipping rates created successfully.',
            ['created_count' => count($ratesToCreate)]
        );
    }

    /**
     * Bulk update shipping rates
     *
     * Update multiple shipping rates with the same changes in a single request. This is
     * useful for applying price changes, threshold adjustments, or status changes to
     * multiple rates simultaneously.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @bodyParam rate_ids array required Array of shipping rate IDs to update. Example: [1, 2, 3, 4, 5]
     * @bodyParam rate_ids.* integer required Each rate ID must be a valid shipping rate ID. Example: 1
     * @bodyParam updates object required Object containing the fields to update. Example: {"rate": 7.99, "free_threshold": 150.00, "is_active": true}
     * @bodyParam updates.rate numeric optional New shipping rate in pounds. Example: 7.99
     * @bodyParam updates.free_threshold numeric optional New free shipping threshold in pounds. Example: 150.00
     * @bodyParam updates.is_active boolean optional New active status. Example: true
     *
     * @response 200 scenario="Shipping rates updated successfully" {
     *   "message": "5 shipping rates updated successfully.",
     *   "data": {
     *     "updated_count": 5
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The rate ids field is required.",
     *     "The updates field is required.",
     *     "The updates.rate must be a positive number."
     *   ]
     * }
     *
     * @response 422 scenario="No valid updates" {
     *   "message": "No valid updates provided."
     * }
     */
    public function bulkUpdate(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'rate_ids' => ['required', 'array', 'min:1'],
            'rate_ids.*' => ['integer', 'exists:shipping_rates,id'],
            'updates' => ['required', 'array'],
            'updates.rate' => ['nullable', 'numeric', 'min:0'],
            'updates.free_threshold' => ['nullable', 'numeric', 'min:0'],
            'updates.is_active' => ['nullable', 'boolean'],
        ]);

        $updates = [];
        $updatesData = $request->input('updates');

        if (isset($updatesData['rate'])) {
            $updates['rate'] = (int) round($updatesData['rate'] * 100);
        }

        if (isset($updatesData['free_threshold'])) {
            $updates['free_threshold'] = $updatesData['free_threshold'] ? (int) round($updatesData['free_threshold'] * 100) : null;
        }

        if (isset($updatesData['is_active'])) {
            $updates['is_active'] = $updatesData['is_active'];
        }

        if (empty($updates)) {
            return $this->error('No valid updates provided.', 422);
        }

        $updates['updated_at'] = now();

        $updatedCount = ShippingRate::whereIn('id', $request->input('rate_ids'))
            ->update($updates);

        return $this->ok(
            "{$updatedCount} shipping rates updated successfully.",
            ['updated_count' => $updatedCount]
        );
    }

    /**
     * Duplicate shipping rate to other zones
     *
     * Duplicate an existing shipping rate to other zones, maintaining the same weight and
     * total thresholds but applying them to different geographical areas. This is useful
     * for setting up similar rates across multiple zones.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @urlParam shippingRate integer required The ID of the shipping rate to duplicate. Example: 1
     *
     * @bodyParam shipping_zone_ids array required Array of zone IDs to duplicate the rate to. Example: [2, 3, 4]
     * @bodyParam shipping_zone_ids.* integer required Each zone ID must be a valid shipping zone ID. Example: 2
     *
     * @response 200 scenario="Shipping rates duplicated successfully" {
     *   "message": "3 shipping rates duplicated successfully.",
     *   "data": {
     *     "duplicated_count": 3,
     *     "rates": [
     *       {
     *         "id": 49,
     *         "shipping_method_id": 1,
     *         "shipping_zone_id": 2,
     *         "min_weight": 0,
     *         "max_weight": 2000,
     *         "rate": 599,
     *         "rate_formatted": "£5.99",
     *         "is_active": true,
     *         "created_at": "2025-01-15T14:20:00.000000Z"
     *       },
     *       {
     *         "id": 50,
     *         "shipping_method_id": 1,
     *         "shipping_zone_id": 3,
     *         "min_weight": 0,
     *         "max_weight": 2000,
     *         "rate": 599,
     *         "rate_formatted": "£5.99",
     *         "is_active": true,
     *         "created_at": "2025-01-15T14:20:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping rate not found" {
     *   "message": "No query results for model [App\\Models\\ShippingRate] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping zone ids field is required.",
     *     "The shipping zone ids.0 must exist in shipping_zones table."
     *   ]
     * }
     */
    public function duplicate(Request $request, ShippingRate $shippingRate)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'shipping_zone_ids' => ['required', 'array', 'min:1'],
            'shipping_zone_ids.*' => ['integer', 'exists:shipping_zones,id'],
        ]);

        $zoneIds = $request->input('shipping_zone_ids');
        $duplicatedRates = [];

        foreach ($zoneIds as $zoneId) {
            if ($zoneId == $shippingRate->shipping_zone_id) {
                continue;
            }

            $existingRate = ShippingRate::where('shipping_method_id', $shippingRate->shipping_method_id)
                ->where('shipping_zone_id', $zoneId)
                ->forWeight($shippingRate->min_weight)
                ->forTotal($shippingRate->min_total)
                ->exists();

            if (!$existingRate) {
                $newRate = $shippingRate->replicate();
                $newRate->shipping_zone_id = $zoneId;
                $newRate->save();
                $duplicatedRates[] = $newRate;
            }
        }

        return $this->ok(
            count($duplicatedRates) . ' shipping rates duplicated successfully.',
            [
                'duplicated_count' => count($duplicatedRates),
                'rates' => ShippingRateResource::collection($duplicatedRates)
            ]
        );
    }

    /**
     * Calculate shipping cost for specific criteria
     *
     * Calculate the shipping cost for a specific shipping method, zone, weight, and order total.
     * This endpoint is useful for testing rate configurations and understanding how shipping
     * costs are calculated before rates go live.
     *
     * @group Shipping Rate Management
     * @authenticated
     *
     * @bodyParam shipping_method_id integer required The ID of the shipping method. Example: 1
     * @bodyParam shipping_zone_id integer required The ID of the shipping zone. Example: 1
     * @bodyParam weight numeric required Package weight in kilograms. Example: 1.5
     * @bodyParam total numeric required Order total in pounds. Example: 45.99
     *
     * @response 200 scenario="Shipping cost calculated successfully" {
     *   "message": "Shipping cost calculated successfully.",
     *   "data": {
     *     "rate": {
     *       "id": 1,
     *       "shipping_method_id": 1,
     *       "shipping_zone_id": 1,
     *       "min_weight": 0,
     *       "max_weight": 2000,
     *       "min_total": 0,
     *       "max_total": 5000,
     *       "rate": 599,
     *       "rate_formatted": "£5.99",
     *       "free_threshold": 10000,
     *       "free_threshold_formatted": "£100.00",
     *       "is_active": true
     *     },
     *     "calculation": {
     *       "weight": 1.5,
     *       "total": 4599,
     *       "total_formatted": "£45.99",
     *       "shipping_cost": 599,
     *       "shipping_cost_formatted": "£5.99",
     *       "is_free": false,
     *       "free_threshold_met": false
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="No shipping rate found" {
     *   "message": "No shipping rate found for the specified criteria."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping method id field is required.",
     *     "The shipping zone id field is required.",
     *     "The weight field is required.",
     *     "The total field is required."
     *   ]
     * }
     */
    public function calculate(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'shipping_zone_id' => ['required', 'integer', 'exists:shipping_zones,id'],
            'weight' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
        ]);

        $methodId = $request->input('shipping_method_id');
        $zoneId = $request->input('shipping_zone_id');
        $weight = $request->input('weight');
        $total = (int) round($request->input('total') * 100);

        $method = ShippingMethod::findOrFail($methodId);
        $zone = ShippingZone::findOrFail($zoneId);

        $rate = $method->getRateForZone($zone, $weight * 1000, $total);

        if (!$rate) {
            return $this->error('No shipping rate found for the specified criteria.', 404);
        }

        $cost = $rate->calculateShippingCost($total);

        return $this->ok(
            'Shipping cost calculated successfully.',
            [
                'rate' => new ShippingRateResource($rate),
                'calculation' => [
                    'weight' => $weight,
                    'total' => $total,
                    'total_formatted' => '£' . number_format($total / 100, 2),
                    'shipping_cost' => $cost,
                    'shipping_cost_formatted' => '£' . number_format($cost / 100, 2),
                    'is_free' => $cost === 0,
                    'free_threshold_met' => $rate->free_threshold && $total >= $rate->free_threshold,
                ]
            ]
        );
    }
}

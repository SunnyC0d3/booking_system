<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingMethod;
use App\Requests\V1\StoreShippingMethodRequest;
use App\Requests\V1\UpdateShippingMethodRequest;
use App\Resources\V1\ShippingMethodResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;

class ShippingMethodController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve paginated list of shipping methods
     *
     * Get a paginated list of all shipping methods in the system with optional filtering by carrier
     * and active status. Essential for managing shipping configuration and displaying available
     * shipping options to customers during checkout.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @queryParam carrier string optional Filter by carrier name (partial match supported). Example: royal-mail
     * @queryParam is_active boolean optional Filter by active status. Example: true
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of methods per page (max 100). Default: 15. Example: 25
     *
     * @response 200 scenario="Success with shipping methods" {
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail",
     *       "service_code": "tracked-48",
     *       "description": "Tracked delivery within 2-3 working days",
     *       "estimated_delivery": "2-3 days",
     *       "min_delivery_days": 2,
     *       "max_delivery_days": 3,
     *       "is_active": true,
     *       "is_default": false,
     *       "supports_tracking": true,
     *       "requires_signature": false,
     *       "max_weight": 20000,
     *       "max_dimensions": {
     *         "length": 60,
     *         "width": 46,
     *         "height": 46
     *       },
     *       "sort_order": 1,
     *       "zones_count": 3,
     *       "rates_count": 12,
     *       "created_at": "2025-01-10T09:00:00.000000Z",
     *       "updated_at": "2025-01-14T14:30:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "Express Delivery",
     *       "carrier": "DPD",
     *       "service_code": "next-day",
     *       "description": "Next working day delivery by 1pm",
     *       "estimated_delivery": "1 day",
     *       "min_delivery_days": 1,
     *       "max_delivery_days": 1,
     *       "is_active": true,
     *       "is_default": false,
     *       "supports_tracking": true,
     *       "requires_signature": true,
     *       "max_weight": 30000,
     *       "max_dimensions": {
     *         "length": 120,
     *         "width": 80,
     *         "height": 80
     *       },
     *       "sort_order": 2,
     *       "zones_count": 2,
     *       "rates_count": 8,
     *       "created_at": "2025-01-10T09:15:00.000000Z",
     *       "updated_at": "2025-01-14T14:30:00.000000Z"
     *     }
     *   ],
     *   "current_page": 1,
     *   "per_page": 15,
     *   "total": 5,
     *   "last_page": 1,
     *   "message": "Shipping methods retrieved successfully.",
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

        if (!$user->hasPermission('view_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $query = ShippingMethod::query();

        if ($request->has('carrier')) {
            $query->where('carrier', 'like', '%' . $request->input('carrier') . '%');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min($request->input('per_page', 15), 100);
        $methods = $query->ordered()->paginate($perPage);

        return ShippingMethodResource::collection($methods)->additional([
            'message' => 'Shipping methods retrieved successfully.',
            'status' => 200
        ]);
    }

    /**
     * Create a new shipping method
     *
     * Create a new shipping method with carrier details, delivery estimates, and configuration options.
     * The system validates that the service code is unique and the carrier configuration is valid.
     * This method defines how products are shipped and affects checkout options.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @bodyParam name string required The display name for the shipping method. Example: Premium Delivery
     * @bodyParam carrier string required The carrier company name. Example: UPS
     * @bodyParam service_code string required Unique service identifier for the carrier. Example: ups-express
     * @bodyParam description string optional Detailed description of the shipping method. Example: Express delivery with insurance
     * @bodyParam estimated_delivery string required Human-readable delivery estimate. Example: 1-2 days
     * @bodyParam min_delivery_days integer required Minimum delivery days. Example: 1
     * @bodyParam max_delivery_days integer required Maximum delivery days. Example: 2
     * @bodyParam is_active boolean optional Whether method is active. Default: true. Example: true
     * @bodyParam is_default boolean optional Whether this is the default method. Default: false. Example: false
     * @bodyParam supports_tracking boolean optional Whether tracking is supported. Default: true. Example: true
     * @bodyParam requires_signature boolean optional Whether signature is required. Default: false. Example: true
     * @bodyParam max_weight integer optional Maximum weight in grams. Example: 25000
     * @bodyParam max_dimensions object optional Maximum dimensions in cm. Example: {"length": 100, "width": 60, "height": 60}
     * @bodyParam sort_order integer optional Display order. Example: 3
     * @bodyParam carrier_config object optional Carrier-specific configuration. Example: {"api_key": "ups_api_key", "account_number": "12345"}
     *
     * @response 200 scenario="Shipping method created successfully" {
     *   "message": "Shipping method created successfully.",
     *   "data": {
     *     "id": 6,
     *     "name": "Premium Delivery",
     *     "carrier": "UPS",
     *     "service_code": "ups-express",
     *     "description": "Express delivery with insurance",
     *     "estimated_delivery": "1-2 days",
     *     "min_delivery_days": 1,
     *     "max_delivery_days": 2,
     *     "is_active": true,
     *     "is_default": false,
     *     "supports_tracking": true,
     *     "requires_signature": true,
     *     "max_weight": 25000,
     *     "max_dimensions": {
     *       "length": 100,
     *       "width": 60,
     *       "height": 60
     *     },
     *     "sort_order": 3,
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
     *     "The name field is required.",
     *     "The service code must be unique.",
     *     "The max delivery days must be greater than or equal to min delivery days."
     *   ]
     * }
     */
    public function store(StoreShippingMethodRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        $method = ShippingMethod::create($data);

        return $this->ok(
            'Shipping method created successfully.',
            new ShippingMethodResource($method)
        );
    }

    /**
     * Retrieve a specific shipping method
     *
     * Get detailed information about a specific shipping method including zone and rate counts,
     * carrier configuration, and all method settings. Useful for viewing complete method
     * configuration and understanding its relationship to zones and rates.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @urlParam shippingMethod integer required The ID of the shipping method to retrieve. Example: 1
     *
     * @response 200 scenario="Shipping method found" {
     *   "message": "Shipping method retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "Standard Delivery",
     *     "carrier": "Royal Mail",
     *     "service_code": "tracked-48",
     *     "description": "Tracked delivery within 2-3 working days",
     *     "estimated_delivery": "2-3 days",
     *     "min_delivery_days": 2,
     *     "max_delivery_days": 3,
     *     "is_active": true,
     *     "is_default": false,
     *     "supports_tracking": true,
     *     "requires_signature": false,
     *     "max_weight": 20000,
     *     "max_dimensions": {
     *       "length": 60,
     *       "width": 46,
     *       "height": 46
     *     },
     *     "sort_order": 1,
     *     "zones_count": 3,
     *     "rates_count": 12,
     *     "carrier_config": {
     *       "api_endpoint": "https://api.royalmail.com",
     *       "account_id": "RM123456"
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
     * @response 404 scenario="Shipping method not found" {
     *   "message": "No query results for model [App\\Models\\ShippingMethod] 999"
     * }
     */
    public function show(Request $request, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingMethod->loadCount(['zones', 'rates']);

        return $this->ok(
            'Shipping method retrieved successfully.',
            new ShippingMethodResource($shippingMethod)
        );
    }

    /**
     * Update an existing shipping method
     *
     * Update a shipping method's configuration, delivery estimates, and settings. The system
     * validates that any service code changes don't conflict with existing methods. Changes
     * to active status will immediately affect checkout options for customers.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @urlParam shippingMethod integer required The ID of the shipping method to update. Example: 1
     *
     * @bodyParam name string optional The display name for the shipping method. Example: Standard Delivery Updated
     * @bodyParam carrier string optional The carrier company name. Example: Royal Mail
     * @bodyParam service_code string optional Unique service identifier for the carrier. Example: tracked-48-updated
     * @bodyParam description string optional Detailed description of the shipping method. Example: Updated tracked delivery within 2-3 working days
     * @bodyParam estimated_delivery string optional Human-readable delivery estimate. Example: 2-4 days
     * @bodyParam min_delivery_days integer optional Minimum delivery days. Example: 2
     * @bodyParam max_delivery_days integer optional Maximum delivery days. Example: 4
     * @bodyParam is_active boolean optional Whether method is active. Example: true
     * @bodyParam is_default boolean optional Whether this is the default method. Example: false
     * @bodyParam supports_tracking boolean optional Whether tracking is supported. Example: true
     * @bodyParam requires_signature boolean optional Whether signature is required. Example: false
     * @bodyParam max_weight integer optional Maximum weight in grams. Example: 22000
     * @bodyParam max_dimensions object optional Maximum dimensions in cm. Example: {"length": 65, "width": 50, "height": 50}
     * @bodyParam sort_order integer optional Display order. Example: 1
     * @bodyParam carrier_config object optional Carrier-specific configuration. Example: {"api_endpoint": "https://api.royalmail.com/v2", "account_id": "RM123456"}
     *
     * @response 200 scenario="Shipping method updated successfully" {
     *   "message": "Shipping method updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "Standard Delivery Updated",
     *     "carrier": "Royal Mail",
     *     "service_code": "tracked-48",
     *     "description": "Updated tracked delivery within 2-3 working days",
     *     "estimated_delivery": "2-4 days",
     *     "min_delivery_days": 2,
     *     "max_delivery_days": 4,
     *     "is_active": true,
     *     "max_weight": 22000,
     *     "updated_at": "2025-01-15T11:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping method not found" {
     *   "message": "No query results for model [App\\Models\\ShippingMethod] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The service code must be unique.",
     *     "The max delivery days must be greater than or equal to min delivery days."
     *   ]
     * }
     */
    public function update(UpdateShippingMethodRequest $request, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $data = $request->validated();

        $shippingMethod->update($data);

        return $this->ok(
            'Shipping method updated successfully.',
            new ShippingMethodResource($shippingMethod)
        );
    }

    /**
     * Delete a shipping method
     *
     * Delete a shipping method from the system. This action cannot be performed if the method
     * has been used in orders or has associated shipments. The system also removes all related
     * zones and rates when the method is successfully deleted.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @urlParam shippingMethod integer required The ID of the shipping method to delete. Example: 1
     *
     * @response 200 scenario="Shipping method deleted successfully" {
     *   "message": "Shipping method deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping method not found" {
     *   "message": "No query results for model [App\\Models\\ShippingMethod] 999"
     * }
     *
     * @response 400 scenario="Cannot delete method with orders" {
     *   "message": "Cannot delete shipping method that has been used in orders."
     * }
     *
     * @response 400 scenario="Cannot delete method with shipments" {
     *   "message": "Cannot delete shipping method that has associated shipments."
     * }
     */
    public function destroy(Request $request, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        if ($shippingMethod->orders()->exists()) {
            return $this->error('Cannot delete shipping method that has been used in orders.', 400);
        }

        if ($shippingMethod->shipments()->exists()) {
            return $this->error('Cannot delete shipping method that has associated shipments.', 400);
        }

        $shippingMethod->zones()->detach();
        $shippingMethod->rates()->delete();
        $shippingMethod->delete();

        return $this->ok('Shipping method deleted successfully.');
    }

    /**
     * Activate a shipping method
     *
     * Activate a shipping method, making it available for use in orders and visible to customers
     * during checkout. This enables the method to appear in shipping options and be selected
     * for order fulfillment.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @urlParam shippingMethod integer required The ID of the shipping method to activate. Example: 1
     *
     * @response 200 scenario="Shipping method activated successfully" {
     *   "message": "Shipping method activated successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "Standard Delivery",
     *     "carrier": "Royal Mail",
     *     "service_code": "tracked-48",
     *     "is_active": true,
     *     "updated_at": "2025-01-15T12:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping method not found" {
     *   "message": "No query results for model [App\\Models\\ShippingMethod] 999"
     * }
     */
    public function activate(Request $request, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingMethod->update(['is_active' => true]);

        return $this->ok(
            'Shipping method activated successfully.',
            new ShippingMethodResource($shippingMethod)
        );
    }

    /**
     * Deactivate a shipping method
     *
     * Deactivate a shipping method, preventing it from being used in new orders and removing
     * it from customer checkout options. Existing orders and shipments using this method
     * are not affected by this change.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @urlParam shippingMethod integer required The ID of the shipping method to deactivate. Example: 1
     *
     * @response 200 scenario="Shipping method deactivated successfully" {
     *   "message": "Shipping method deactivated successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "Standard Delivery",
     *     "carrier": "Royal Mail",
     *     "service_code": "tracked-48",
     *     "is_active": false,
     *     "updated_at": "2025-01-15T12:15:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Shipping method not found" {
     *   "message": "No query results for model [App\\Models\\ShippingMethod] 999"
     * }
     */
    public function deactivate(Request $request, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingMethod->update(['is_active' => false]);

        return $this->ok(
            'Shipping method deactivated successfully.',
            new ShippingMethodResource($shippingMethod)
        );
    }

    /**
     * Reorder shipping methods
     *
     * Update the display order of shipping methods by providing an array of method IDs in the
     * desired order. This affects how methods are presented to customers during checkout,
     * with lower sort orders appearing first in the list.
     *
     * @group Shipping Method Management
     * @authenticated
     *
     * @bodyParam methods array required Array of shipping method IDs in desired order. Example: [2, 1, 3, 4, 5]
     * @bodyParam methods.* integer required Each method ID must be a valid shipping method ID. Example: 1
     *
     * @response 200 scenario="Shipping methods reordered successfully" {
     *   "message": "Shipping methods reordered successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The methods field is required.",
     *     "The methods.0 field must be an integer.",
     *     "The methods.1 field must exist in shipping_methods table."
     *   ]
     * }
     */
    public function reorder(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_methods')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'methods' => ['required', 'array'],
            'methods.*' => ['integer', 'exists:shipping_methods,id'],
        ]);

        $methodIds = $request->input('methods');

        foreach ($methodIds as $index => $methodId) {
            ShippingMethod::where('id', $methodId)->update(['sort_order' => $index]);
        }

        return $this->ok('Shipping methods reordered successfully.');
    }
}

<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ShippingAddress;
use App\Requests\V1\StoreShippingAddressRequest;
use App\Requests\V1\UpdateShippingAddressRequest;
use App\Resources\V1\ShippingAddressResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;

class ShippingAddressController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve user's shipping addresses
     *
     * Get all shipping addresses for the authenticated user, ordered by default status
     * and creation date. This endpoint returns the user's address book with default
     * addresses appearing first, essential for checkout and order management.
     *
     * @group Customer Shipping Addresses
     * @authenticated
     *
     * @response 200 scenario="Success with shipping addresses" {
     *   "data": [
     *     {
     *       "id": 1,
     *       "user_id": 45,
     *       "type": "shipping",
     *       "name": "John Smith",
     *       "company": "Acme Corp",
     *       "address_line_1": "123 Main Street",
     *       "address_line_2": "Suite 100",
     *       "city": "London",
     *       "state": "Greater London",
     *       "postcode": "SW1A 1AA",
     *       "country": "GB",
     *       "phone": "+44 20 7123 4567",
     *       "is_default": true,
     *       "is_validated": true,
     *       "full_address": "123 Main Street, Suite 100, London, Greater London, SW1A 1AA, United Kingdom",
     *       "country_name": "United Kingdom",
     *       "validation_status": "validated",
     *       "created_at": "2025-01-10T09:00:00.000000Z",
     *       "updated_at": "2025-01-14T14:30:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "user_id": 45,
     *       "type": "shipping",
     *       "name": "John Smith",
     *       "company": null,
     *       "address_line_1": "456 Oak Avenue",
     *       "address_line_2": null,
     *       "city": "Manchester",
     *       "state": "Greater Manchester",
     *       "postcode": "M1 1AA",
     *       "country": "GB",
     *       "phone": "+44 161 234 5678",
     *       "is_default": false,
     *       "is_validated": false,
     *       "full_address": "456 Oak Avenue, Manchester, Greater Manchester, M1 1AA, United Kingdom",
     *       "country_name": "United Kingdom",
     *       "validation_status": "unvalidated",
     *       "created_at": "2025-01-12T15:20:00.000000Z",
     *       "updated_at": "2025-01-12T15:20:00.000000Z"
     *     }
     *   ],
     *   "message": "Shipping addresses retrieved successfully.",
     *   "status": 200
     * }
     *
     * @response 200 scenario="No addresses found" {
     *   "data": [],
     *   "message": "Shipping addresses retrieved successfully.",
     *   "status": 200
     * }
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $addresses = ShippingAddress::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return ShippingAddressResource::collection($addresses)->additional([
            'message' => 'Shipping addresses retrieved successfully.',
            'status' => 200
        ]);
    }

    /**
     * Create a new shipping address
     *
     * Create a new shipping address for the authenticated user. If the address is marked
     * as default, it will automatically unset the default flag from other addresses of
     * the same type. The system supports address validation and formatting.
     *
     * @group Customer Shipping Addresses
     * @authenticated
     *
     * @bodyParam type string required The address type (shipping or billing). Example: shipping
     * @bodyParam name string required Full name for the address. Example: John Smith
     * @bodyParam company string optional Company name. Example: Acme Corp
     * @bodyParam address_line_1 string required First line of the address. Example: 123 Main Street
     * @bodyParam address_line_2 string optional Second line of the address (apartment, suite, etc.). Example: Suite 100
     * @bodyParam city string required City name. Example: London
     * @bodyParam state string optional State or county name. Example: Greater London
     * @bodyParam postcode string required Postal code. Example: SW1A 1AA
     * @bodyParam country string required Two-letter country code (ISO 3166-1 alpha-2). Example: GB
     * @bodyParam phone string optional Phone number. Example: +44 20 7123 4567
     * @bodyParam is_default boolean optional Whether this is the default address for the type. Default: false. Example: true
     * @bodyParam special_instructions string optional Special delivery instructions. Example: Leave at front door
     *
     * @response 200 scenario="Shipping address created successfully" {
     *   "message": "Shipping address created successfully.",
     *   "data": {
     *     "id": 3,
     *     "user_id": 45,
     *     "type": "shipping",
     *     "name": "John Smith",
     *     "company": "Acme Corp",
     *     "address_line_1": "123 Main Street",
     *     "address_line_2": "Suite 100",
     *     "city": "London",
     *     "state": "Greater London",
     *     "postcode": "SW1A 1AA",
     *     "country": "GB",
     *     "phone": "+44 20 7123 4567",
     *     "is_default": true,
     *     "is_validated": false,
     *     "full_address": "123 Main Street, Suite 100, London, Greater London, SW1A 1AA, United Kingdom",
     *     "country_name": "United Kingdom",
     *     "validation_status": "unvalidated",
     *     "special_instructions": "Leave at front door",
     *     "created_at": "2025-01-15T10:30:00.000000Z",
     *     "updated_at": "2025-01-15T10:30:00.000000Z"
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The address line 1 field is required.",
     *     "The city field is required.",
     *     "The postcode field is required.",
     *     "The country field is required.",
     *     "The country must be a valid ISO 3166-1 alpha-2 country code."
     *   ]
     * }
     */
    public function store(StoreShippingAddressRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $data['user_id'] = $user->id;

        if ($data['is_default'] ?? false) {
            ShippingAddress::where('user_id', $user->id)
                ->where('type', $data['type'])
                ->update(['is_default' => false]);
        }

        $address = ShippingAddress::create($data);

        return $this->ok(
            'Shipping address created successfully.',
            new ShippingAddressResource($address)
        );
    }

    /**
     * Retrieve a specific shipping address
     *
     * Get detailed information about a specific shipping address. Users can only access
     * their own addresses. This endpoint returns complete address information including
     * validation status and formatted address details.
     *
     * @group Customer Shipping Addresses
     * @authenticated
     *
     * @urlParam shippingAddress integer required The ID of the shipping address to retrieve. Example: 1
     *
     * @response 200 scenario="Shipping address found" {
     *   "message": "Shipping address retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "user_id": 45,
     *     "type": "shipping",
     *     "name": "John Smith",
     *     "company": "Acme Corp",
     *     "address_line_1": "123 Main Street",
     *     "address_line_2": "Suite 100",
     *     "city": "London",
     *     "state": "Greater London",
     *     "postcode": "SW1A 1AA",
     *     "country": "GB",
     *     "phone": "+44 20 7123 4567",
     *     "is_default": true,
     *     "is_validated": true,
     *     "full_address": "123 Main Street, Suite 100, London, Greater London, SW1A 1AA, United Kingdom",
     *     "country_name": "United Kingdom",
     *     "validation_status": "validated",
     *     "special_instructions": "Leave at front door",
     *     "validation_data": {
     *       "validated_at": "2025-01-10T09:15:00.000000Z",
     *       "validation_service": "postcode_anywhere",
     *       "confidence_score": 0.98
     *     },
     *     "created_at": "2025-01-10T09:00:00.000000Z",
     *     "updated_at": "2025-01-14T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Access denied" {
     *   "message": "You can only view your own shipping addresses."
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "No query results for model [App\\Models\\ShippingAddress] 999"
     * }
     */
    public function show(Request $request, ShippingAddress $shippingAddress)
    {
        $user = $request->user();

        if ($shippingAddress->user_id !== $user->id) {
            return $this->error('You can only view your own shipping addresses.', 403);
        }

        return $this->ok(
            'Shipping address retrieved successfully.',
            new ShippingAddressResource($shippingAddress)
        );
    }

    /**
     * Update an existing shipping address
     *
     * Update a shipping address for the authenticated user. If the address is marked as
     * default, it will automatically unset the default flag from other addresses of the
     * same type. Users can only update their own addresses.
     *
     * @group Customer Shipping Addresses
     * @authenticated
     *
     * @urlParam shippingAddress integer required The ID of the shipping address to update. Example: 1
     *
     * @bodyParam type string optional The address type (shipping or billing). Example: shipping
     * @bodyParam name string optional Full name for the address. Example: John Smith
     * @bodyParam company string optional Company name. Example: Acme Corp
     * @bodyParam address_line_1 string optional First line of the address. Example: 123 Main Street
     * @bodyParam address_line_2 string optional Second line of the address. Example: Suite 100
     * @bodyParam city string optional City name. Example: London
     * @bodyParam state string optional State or county name. Example: Greater London
     * @bodyParam postcode string optional Postal code. Example: SW1A 1AA
     * @bodyParam country string optional Two-letter country code (ISO 3166-1 alpha-2). Example: GB
     * @bodyParam phone string optional Phone number. Example: +44 20 7123 4567
     * @bodyParam is_default boolean optional Whether this is the default address for the type. Example: true
     * @bodyParam special_instructions string optional Special delivery instructions. Example: Ring doorbell twice
     *
     * @response 200 scenario="Shipping address updated successfully" {
     *   "message": "Shipping address updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "user_id": 45,
     *     "type": "shipping",
     *     "name": "John Smith",
     *     "company": "Acme Corp",
     *     "address_line_1": "123 Main Street",
     *     "address_line_2": "Suite 100",
     *     "city": "London",
     *     "state": "Greater London",
     *     "postcode": "SW1A 1AA",
     *     "country": "GB",
     *     "phone": "+44 20 7123 4567",
     *     "is_default": true,
     *     "is_validated": false,
     *     "full_address": "123 Main Street, Suite 100, London, Greater London, SW1A 1AA, United Kingdom",
     *     "country_name": "United Kingdom",
     *     "validation_status": "unvalidated",
     *     "special_instructions": "Ring doorbell twice",
     *     "updated_at": "2025-01-15T11:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Access denied" {
     *   "message": "You can only update your own shipping addresses."
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "No query results for model [App\\Models\\ShippingAddress] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The postcode field is required.",
     *     "The country must be a valid ISO 3166-1 alpha-2 country code."
     *   ]
     * }
     */
    public function update(UpdateShippingAddressRequest $request, ShippingAddress $shippingAddress)
    {
        $user = $request->user();

        if ($shippingAddress->user_id !== $user->id) {
            return $this->error('You can only update your own shipping addresses.', 403);
        }

        $data = $request->validated();

        if ($data['is_default'] ?? false) {
            ShippingAddress::where('user_id', $user->id)
                ->where('type', $data['type'])
                ->where('id', '!=', $shippingAddress->id)
                ->update(['is_default' => false]);
        }

        $shippingAddress->update($data);

        return $this->ok(
            'Shipping address updated successfully.',
            new ShippingAddressResource($shippingAddress)
        );
    }

    /**
     * Delete a shipping address
     *
     * Delete a shipping address from the user's address book. Default addresses cannot
     * be deleted directly - you must first set another address as default. Users can
     * only delete their own addresses.
     *
     * @group Customer Shipping Addresses
     * @authenticated
     *
     * @urlParam shippingAddress integer required The ID of the shipping address to delete. Example: 1
     *
     * @response 200 scenario="Shipping address deleted successfully" {
     *   "message": "Shipping address deleted successfully."
     * }
     *
     * @response 403 scenario="Access denied" {
     *   "message": "You can only delete your own shipping addresses."
     * }
     *
     * @response 400 scenario="Cannot delete default address" {
     *   "message": "Cannot delete default shipping address. Please set another address as default first."
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "No query results for model [App\\Models\\ShippingAddress] 999"
     * }
     */
    public function destroy(Request $request, ShippingAddress $shippingAddress)
    {
        $user = $request->user();

        if ($shippingAddress->user_id !== $user->id) {
            return $this->error('You can only delete your own shipping addresses.', 403);
        }

        if ($shippingAddress->is_default) {
            return $this->error('Cannot delete default shipping address. Please set another address as default first.', 400);
        }

        $shippingAddress->delete();

        return $this->ok('Shipping address deleted successfully.');
    }

    /**
     * Set address as default
     *
     * Set a shipping address as the default address for its type (shipping or billing).
     * This automatically unsets the default flag from other addresses of the same type.
     * Users can only modify their own addresses.
     *
     * @group Customer Shipping Addresses
     * @authenticated
     *
     * @urlParam shippingAddress integer required The ID of the shipping address to set as default. Example: 1
     *
     * @response 200 scenario="Default address updated successfully" {
     *   "message": "Default shipping address updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "user_id": 45,
     *     "type": "shipping",
     *     "name": "John Smith",
     *     "company": "Acme Corp",
     *     "address_line_1": "123 Main Street",
     *     "address_line_2": "Suite 100",
     *     "city": "London",
     *     "state": "Greater London",
     *     "postcode": "SW1A 1AA",
     *     "country": "GB",
     *     "phone": "+44 20 7123 4567",
     *     "is_default": true,
     *     "is_validated": true,
     *     "full_address": "123 Main Street, Suite 100, London, Greater London, SW1A 1AA, United Kingdom",
     *     "country_name": "United Kingdom",
     *     "validation_status": "validated",
     *     "updated_at": "2025-01-15T12:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Access denied" {
     *   "message": "You can only modify your own shipping addresses."
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "No query results for model [App\\Models\\ShippingAddress] 999"
     * }
     */
    public function setDefault(Request $request, ShippingAddress $shippingAddress)
    {
        $user = $request->user();

        if ($shippingAddress->user_id !== $user->id) {
            return $this->error('You can only modify your own shipping addresses.', 403);
        }

        $shippingAddress->setAsDefault();

        return $this->ok(
            'Default shipping address updated successfully.',
            new ShippingAddressResource($shippingAddress->fresh())
        );
    }

    /**
     * Validate a shipping address
     *
     * Validate a shipping address using external address validation services. This checks
     * if the address exists, is deliverable, and provides standardized formatting. The
     * validation result is stored with the address for future reference.
     *
     * @group Customer Shipping Addresses
     * @authenticated
     *
     * @urlParam shippingAddress integer required The ID of the shipping address to validate. Example: 1
     *
     * @response 200 scenario="Address validated successfully" {
     *   "message": "Address validated successfully.",
     *   "data": {
     *     "address": {
     *       "id": 1,
     *       "user_id": 45,
     *       "type": "shipping",
     *       "name": "John Smith",
     *       "company": "Acme Corp",
     *       "address_line_1": "123 Main Street",
     *       "address_line_2": "Suite 100",
     *       "city": "London",
     *       "state": "Greater London",
     *       "postcode": "SW1A 1AA",
     *       "country": "GB",
     *       "phone": "+44 20 7123 4567",
     *       "is_default": true,
     *       "is_validated": true,
     *       "full_address": "123 Main Street, Suite 100, London, Greater London, SW1A 1AA, United Kingdom",
     *       "country_name": "United Kingdom",
     *       "validation_status": "validated",
     *       "validation_data": {
     *         "validated_at": "2025-01-15T12:30:00.000000Z",
     *         "validation_service": "postcode_anywhere",
     *         "confidence_score": 0.98
     *       },
     *       "updated_at": "2025-01-15T12:30:00.000000Z"
     *     },
     *     "validation": {
     *       "valid": true,
     *       "confidence_score": 0.98,
     *       "validation_service": "postcode_anywhere",
     *       "validated_at": "2025-01-15T12:30:00.000000Z",
     *       "standardized_address": {
     *         "address_line_1": "123 Main Street",
     *         "address_line_2": "Suite 100",
     *         "city": "London",
     *         "state": "Greater London",
     *         "postcode": "SW1A 1AA",
     *         "country": "GB"
     *       },
     *       "deliverable": true,
     *       "residential": false,
     *       "suggestions": []
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Access denied" {
     *   "message": "You can only validate your own shipping addresses."
     * }
     *
     * @response 422 scenario="Address validation failed" {
     *   "message": "Address validation failed.",
     *   "errors": {
     *     "valid": false,
     *     "confidence_score": 0.2,
     *     "validation_service": "postcode_anywhere",
     *     "validated_at": "2025-01-15T12:30:00.000000Z",
     *     "errors": [
     *       "Postcode not found",
     *       "Street name does not match postcode area"
     *     ],
     *     "suggestions": [
     *       {
     *         "address_line_1": "123 Main Road",
     *         "city": "London",
     *         "postcode": "SW1A 1AA"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 503 scenario="Validation service unavailable" {
     *   "message": "Address validation service unavailable."
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "No query results for model [App\\Models\\ShippingAddress] 999"
     * }
     */
    public function validate(Request $request, ShippingAddress $shippingAddress)
    {
        $user = $request->user();

        if ($shippingAddress->user_id !== $user->id) {
            return $this->error('You can only validate your own shipping addresses.', 403);
        }

        try {
            $shippingService = app(\App\Services\V1\Shipping\ShippingService::class);
            $validationResult = $shippingService->validateAddress($shippingAddress);

            if ($validationResult['valid']) {
                $shippingAddress->markAsValidated($validationResult);

                return $this->ok(
                    'Address validated successfully.',
                    [
                        'address' => new ShippingAddressResource($shippingAddress->fresh()),
                        'validation' => $validationResult
                    ]
                );
            }

            return $this->error('Address validation failed.', 422, $validationResult);

        } catch (\Exception $e) {
            return $this->error('Address validation service unavailable.', 503);
        }
    }
}

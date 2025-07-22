<?php

namespace App\Services\V1\Shipping;

use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingAddressService
{
    protected ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    public function getUserShippingAddresses(User $user): array
    {
        try {
            $addresses = ShippingAddress::where('user_id', $user->id)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('User shipping addresses retrieved', [
                'user_id' => $user->id,
                'addresses_count' => $addresses->count(),
                'default_addresses' => $addresses->where('is_default', true)->count(),
            ]);

            return [
                'addresses' => $addresses,
                'message' => 'Shipping addresses retrieved successfully.',
            ];

        } catch (Exception $e) {
            Log::error('Failed to retrieve user shipping addresses', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to retrieve shipping addresses: ' . $e->getMessage());
        }
    }

    public function createShippingAddress(array $data, User $user): ShippingAddress
    {
        try {
            $data['user_id'] = $user->id;

            // If this is being set as default, unset other default addresses of the same type
            if ($data['is_default'] ?? false) {
                ShippingAddress::where('user_id', $user->id)
                    ->where('type', $data['type'])
                    ->update(['is_default' => false]);

                Log::info('Previous default addresses unset', [
                    'user_id' => $user->id,
                    'address_type' => $data['type'],
                ]);
            }

            $address = ShippingAddress::create($data);

            Log::info('Shipping address created', [
                'user_id' => $user->id,
                'address_id' => $address->id,
                'address_type' => $address->type,
                'is_default' => $address->is_default,
                'country' => $address->country,
                'city' => $address->city,
            ]);

            return $address;

        } catch (Exception $e) {
            Log::error('Failed to create shipping address', [
                'user_id' => $user->id,
                'data' => array_except($data, ['user_id']), // Don't log user_id twice
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to create shipping address: ' . $e->getMessage());
        }
    }

    public function getShippingAddress(ShippingAddress $shippingAddress, User $user): ShippingAddress
    {
        if ($shippingAddress->user_id !== $user->id) {
            Log::warning('Unauthorized shipping address access attempt', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'address_owner' => $shippingAddress->user_id,
            ]);

            throw new Exception('You can only view your own shipping addresses.', 403);
        }

        Log::info('Shipping address retrieved', [
            'user_id' => $user->id,
            'address_id' => $shippingAddress->id,
            'address_type' => $shippingAddress->type,
            'is_default' => $shippingAddress->is_default,
        ]);

        return $shippingAddress;
    }

    public function updateShippingAddress(ShippingAddress $shippingAddress, array $data, User $user): ShippingAddress
    {
        if ($shippingAddress->user_id !== $user->id) {
            Log::warning('Unauthorized shipping address update attempt', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'address_owner' => $shippingAddress->user_id,
            ]);

            throw new Exception('You can only update your own shipping addresses.', 403);
        }

        try {
            $oldData = $shippingAddress->only(['type', 'is_default', 'country', 'city']);

            // If this is being set as default, unset other default addresses of the same type
            if ($data['is_default'] ?? false) {
                ShippingAddress::where('user_id', $user->id)
                    ->where('type', $data['type'])
                    ->where('id', '!=', $shippingAddress->id)
                    ->update(['is_default' => false]);

                Log::info('Previous default addresses unset during update', [
                    'user_id' => $user->id,
                    'address_id' => $shippingAddress->id,
                    'address_type' => $data['type'],
                ]);
            }

            $shippingAddress->update($data);

            Log::info('Shipping address updated', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'old_data' => $oldData,
                'updated_fields' => array_keys($data),
                'new_default_status' => $shippingAddress->is_default,
            ]);

            return $shippingAddress;

        } catch (Exception $e) {
            Log::error('Failed to update shipping address', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update shipping address: ' . $e->getMessage());
        }
    }

    public function deleteShippingAddress(ShippingAddress $shippingAddress, User $user): void
    {
        if ($shippingAddress->user_id !== $user->id) {
            Log::warning('Unauthorized shipping address deletion attempt', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'address_owner' => $shippingAddress->user_id,
            ]);

            throw new Exception('You can only delete your own shipping addresses.', 403);
        }

        if ($shippingAddress->is_default) {
            Log::warning('Attempt to delete default shipping address', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'address_type' => $shippingAddress->type,
            ]);

            throw new Exception('Cannot delete default shipping address. Please set another address as default first.', 400);
        }

        try {
            $addressId = $shippingAddress->id;
            $addressType = $shippingAddress->type;
            $addressCity = $shippingAddress->city;

            $shippingAddress->delete();

            Log::info('Shipping address deleted', [
                'user_id' => $user->id,
                'address_id' => $addressId,
                'address_type' => $addressType,
                'address_city' => $addressCity,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete shipping address', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to delete shipping address: ' . $e->getMessage());
        }
    }

    public function setAddressAsDefault(ShippingAddress $shippingAddress, User $user): ShippingAddress
    {
        if ($shippingAddress->user_id !== $user->id) {
            Log::warning('Unauthorized default address change attempt', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'address_owner' => $shippingAddress->user_id,
            ]);

            throw new Exception('You can only modify your own shipping addresses.', 403);
        }

        try {
            $oldDefaultAddress = ShippingAddress::where('user_id', $user->id)
                ->where('type', $shippingAddress->type)
                ->where('is_default', true)
                ->first();

            $shippingAddress->setAsDefault();

            Log::info('Default shipping address updated', [
                'user_id' => $user->id,
                'new_default_address_id' => $shippingAddress->id,
                'previous_default_address_id' => $oldDefaultAddress?->id,
                'address_type' => $shippingAddress->type,
                'address_city' => $shippingAddress->city,
            ]);

            return $shippingAddress->fresh();

        } catch (Exception $e) {
            Log::error('Failed to set address as default', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to set address as default: ' . $e->getMessage());
        }
    }

    public function validateShippingAddress(ShippingAddress $shippingAddress, User $user): array
    {
        if ($shippingAddress->user_id !== $user->id) {
            Log::warning('Unauthorized address validation attempt', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'address_owner' => $shippingAddress->user_id,
            ]);

            throw new Exception('You can only validate your own shipping addresses.', 403);
        }

        try {
            Log::info('Address validation requested', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'country' => $shippingAddress->country,
                'postcode' => $shippingAddress->postcode,
            ]);

            $validationResult = $this->shippingService->validateAddress($shippingAddress);

            if ($validationResult['valid']) {
                $shippingAddress->markAsValidated($validationResult);

                Log::info('Address validation successful', [
                    'user_id' => $user->id,
                    'address_id' => $shippingAddress->id,
                    'validation_service' => $validationResult['validation_service'] ?? 'unknown',
                    'confidence_score' => $validationResult['confidence_score'] ?? null,
                ]);

                return [
                    'address' => $shippingAddress->fresh(),
                    'validation' => $validationResult,
                    'message' => 'Address validated successfully.',
                ];
            }

            Log::warning('Address validation failed', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'validation_errors' => $validationResult['errors'] ?? [],
                'validation_service' => $validationResult['validation_service'] ?? 'unknown',
            ]);

            throw new Exception('Address validation failed.', 422);

        } catch (Exception $e) {
            if ($e->getCode() === 422) {
                // Re-throw validation errors with the validation result
                $validationResult['message'] = $e->getMessage();
                throw new \Exception(json_encode($validationResult), 422);
            }

            Log::error('Address validation service error', [
                'user_id' => $user->id,
                'address_id' => $shippingAddress->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Address validation service unavailable.', 503);
        }
    }
}

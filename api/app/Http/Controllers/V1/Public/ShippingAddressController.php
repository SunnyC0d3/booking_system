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

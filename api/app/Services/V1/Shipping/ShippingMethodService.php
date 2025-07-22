<?php

namespace App\Services\V1\Shipping;

use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingMethodService
{
    public function getShippingMethods(Request $request, User $user): array
    {
        if (!$user->hasPermission('view_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
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

        Log::info('Shipping methods retrieved', [
            'user_id' => $user->id,
            'total_methods' => $methods->total(),
            'filters' => $request->only(['carrier', 'is_active']),
        ]);

        return [
            'methods' => $methods,
            'message' => 'Shipping methods retrieved successfully.',
        ];
    }

    public function createShippingMethod(array $data, User $user): ShippingMethod
    {
        if (!$user->hasPermission('create_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $method = ShippingMethod::create($data);

            Log::info('Shipping method created', [
                'user_id' => $user->id,
                'method_id' => $method->id,
                'method_name' => $method->name,
                'carrier' => $method->carrier,
            ]);

            return $method;

        } catch (Exception $e) {
            Log::error('Failed to create shipping method', [
                'user_id' => $user->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to create shipping method: ' . $e->getMessage());
        }
    }

    public function getShippingMethod(ShippingMethod $shippingMethod, User $user): ShippingMethod
    {
        if (!$user->hasPermission('view_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        $shippingMethod->loadCount(['zones', 'rates']);

        Log::info('Shipping method retrieved', [
            'user_id' => $user->id,
            'method_id' => $shippingMethod->id,
            'method_name' => $shippingMethod->name,
        ]);

        return $shippingMethod;
    }

    public function updateShippingMethod(ShippingMethod $shippingMethod, array $data, User $user): ShippingMethod
    {
        if (!$user->hasPermission('edit_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $oldData = $shippingMethod->only(['name', 'carrier', 'is_active']);
            $shippingMethod->update($data);

            Log::info('Shipping method updated', [
                'user_id' => $user->id,
                'method_id' => $shippingMethod->id,
                'method_name' => $shippingMethod->name,
                'old_data' => $oldData,
                'new_data' => array_intersect_key($data, $oldData),
            ]);

            return $shippingMethod;

        } catch (Exception $e) {
            Log::error('Failed to update shipping method', [
                'user_id' => $user->id,
                'method_id' => $shippingMethod->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update shipping method: ' . $e->getMessage());
        }
    }

    public function deleteShippingMethod(ShippingMethod $shippingMethod, User $user): void
    {
        if (!$user->hasPermission('delete_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        if ($shippingMethod->orders()->exists()) {
            throw new Exception('Cannot delete shipping method that has been used in orders.', 400);
        }

        if ($shippingMethod->shipments()->exists()) {
            throw new Exception('Cannot delete shipping method that has associated shipments.', 400);
        }

        try {
            $methodName = $shippingMethod->name;
            $methodId = $shippingMethod->id;

            $shippingMethod->zones()->detach();
            $shippingMethod->rates()->delete();
            $shippingMethod->delete();

            Log::info('Shipping method deleted', [
                'user_id' => $user->id,
                'method_id' => $methodId,
                'method_name' => $methodName,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete shipping method', [
                'user_id' => $user->id,
                'method_id' => $shippingMethod->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to delete shipping method: ' . $e->getMessage());
        }
    }

    public function activateShippingMethod(ShippingMethod $shippingMethod, User $user): ShippingMethod
    {
        if (!$user->hasPermission('edit_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $shippingMethod->update(['is_active' => true]);

            Log::info('Shipping method activated', [
                'user_id' => $user->id,
                'method_id' => $shippingMethod->id,
                'method_name' => $shippingMethod->name,
            ]);

            return $shippingMethod;

        } catch (Exception $e) {
            Log::error('Failed to activate shipping method', [
                'user_id' => $user->id,
                'method_id' => $shippingMethod->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to activate shipping method: ' . $e->getMessage());
        }
    }

    public function deactivateShippingMethod(ShippingMethod $shippingMethod, User $user): ShippingMethod
    {
        if (!$user->hasPermission('edit_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $shippingMethod->update(['is_active' => false]);

            Log::info('Shipping method deactivated', [
                'user_id' => $user->id,
                'method_id' => $shippingMethod->id,
                'method_name' => $shippingMethod->name,
            ]);

            return $shippingMethod;

        } catch (Exception $e) {
            Log::error('Failed to deactivate shipping method', [
                'user_id' => $user->id,
                'method_id' => $shippingMethod->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to deactivate shipping method: ' . $e->getMessage());
        }
    }

    public function reorderShippingMethods(array $methodIds, User $user): void
    {
        if (!$user->hasPermission('edit_shipping_methods')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            foreach ($methodIds as $index => $methodId) {
                ShippingMethod::where('id', $methodId)->update(['sort_order' => $index]);
            }

            Log::info('Shipping methods reordered', [
                'user_id' => $user->id,
                'method_ids' => $methodIds,
                'reorder_count' => count($methodIds),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to reorder shipping methods', [
                'user_id' => $user->id,
                'method_ids' => $methodIds,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to reorder shipping methods: ' . $e->getMessage());
        }
    }
}

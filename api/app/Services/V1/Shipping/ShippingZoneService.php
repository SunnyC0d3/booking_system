<?php

namespace App\Services\V1\Shipping;

use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingZoneService
{
    public function getShippingZones(Request $request, User $user): array
    {
        if (!$user->hasPermission('view_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
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

        Log::info('Shipping zones retrieved', [
            'user_id' => $user->id,
            'total_zones' => $zones->total(),
            'filters' => $request->only(['name', 'country', 'is_active']),
            'with_methods' => $request->boolean('with_methods'),
            'with_counts' => $request->boolean('with_counts'),
        ]);

        return [
            'zones' => $zones,
            'message' => 'Shipping zones retrieved successfully.',
        ];
    }

    public function createShippingZone(array $data, User $user): ShippingZone
    {
        if (!$user->hasPermission('create_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $zone = ShippingZone::create($data);

            Log::info('Shipping zone created', [
                'user_id' => $user->id,
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'countries' => $zone->countries,
                'is_active' => $zone->is_active,
            ]);

            return $zone;

        } catch (Exception $e) {
            Log::error('Failed to create shipping zone', [
                'user_id' => $user->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to create shipping zone: ' . $e->getMessage());
        }
    }

    public function getShippingZone(ShippingZone $shippingZone, Request $request, User $user): ShippingZone
    {
        if (!$user->hasPermission('view_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
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

        Log::info('Shipping zone retrieved', [
            'user_id' => $user->id,
            'zone_id' => $shippingZone->id,
            'zone_name' => $shippingZone->name,
            'with_methods' => $request->boolean('with_methods'),
        ]);

        return $shippingZone;
    }

    public function updateShippingZone(ShippingZone $shippingZone, array $data, User $user): ShippingZone
    {
        if (!$user->hasPermission('edit_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $oldData = $shippingZone->only(['name', 'countries', 'is_active']);
            $shippingZone->update($data);

            Log::info('Shipping zone updated', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'zone_name' => $shippingZone->name,
                'old_data' => $oldData,
                'updated_fields' => array_keys($data),
            ]);

            return $shippingZone;

        } catch (Exception $e) {
            Log::error('Failed to update shipping zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update shipping zone: ' . $e->getMessage());
        }
    }

    public function deleteShippingZone(ShippingZone $shippingZone, User $user): void
    {
        if (!$user->hasPermission('delete_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        // Check if zone has associated rates
        if ($shippingZone->rates()->exists()) {
            throw new Exception('Cannot delete shipping zone that has associated rates.', 400);
        }

        try {
            $zoneId = $shippingZone->id;
            $zoneName = $shippingZone->name;

            // Remove method associations if they exist
            if ($shippingZone->methods()->exists()) {
                $methodCount = $shippingZone->methods()->count();
                $shippingZone->methods()->detach();

                Log::info('Shipping zone methods detached', [
                    'user_id' => $user->id,
                    'zone_id' => $zoneId,
                    'methods_detached' => $methodCount,
                ]);
            }

            $shippingZone->delete();

            Log::info('Shipping zone deleted', [
                'user_id' => $user->id,
                'zone_id' => $zoneId,
                'zone_name' => $zoneName,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete shipping zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to delete shipping zone: ' . $e->getMessage());
        }
    }

    public function activateShippingZone(ShippingZone $shippingZone, User $user): ShippingZone
    {
        if (!$user->hasPermission('edit_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $shippingZone->update(['is_active' => true]);

            Log::info('Shipping zone activated', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'zone_name' => $shippingZone->name,
            ]);

            return $shippingZone;

        } catch (Exception $e) {
            Log::error('Failed to activate shipping zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to activate shipping zone: ' . $e->getMessage());
        }
    }

    public function deactivateShippingZone(ShippingZone $shippingZone, User $user): ShippingZone
    {
        if (!$user->hasPermission('edit_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $shippingZone->update(['is_active' => false]);

            Log::info('Shipping zone deactivated', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'zone_name' => $shippingZone->name,
            ]);

            return $shippingZone;

        } catch (Exception $e) {
            Log::error('Failed to deactivate shipping zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to deactivate shipping zone: ' . $e->getMessage());
        }
    }

    public function reorderShippingZones(array $zoneIds, User $user): void
    {
        if (!$user->hasPermission('edit_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            // Update sort_order for each zone based on its position in the array
            foreach ($zoneIds as $index => $zoneId) {
                ShippingZone::where('id', $zoneId)->update(['sort_order' => $index]);
            }

            Log::info('Shipping zones reordered', [
                'user_id' => $user->id,
                'zone_ids' => $zoneIds,
                'reorder_count' => count($zoneIds),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to reorder shipping zones', [
                'user_id' => $user->id,
                'zone_ids' => $zoneIds,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to reorder shipping zones: ' . $e->getMessage());
        }
    }

    public function attachMethodToZone(ShippingZone $shippingZone, array $data, User $user): void
    {
        if (!$user->hasPermission('edit_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        $methodId = $data['method_id'];

        // Check if method is already attached to this zone
        if ($shippingZone->methods()->where('shipping_method_id', $methodId)->exists()) {
            throw new Exception('Shipping method is already attached to this zone.', 400);
        }

        try {
            // Attach method with pivot data
            $shippingZone->methods()->attach($methodId, [
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            Log::info('Shipping method attached to zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'zone_name' => $shippingZone->name,
                'method_id' => $methodId,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to attach method to zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'method_id' => $methodId,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to attach method to zone: ' . $e->getMessage());
        }
    }

    public function detachMethodFromZone(ShippingZone $shippingZone, ShippingMethod $shippingMethod, User $user): void
    {
        if (!$user->hasPermission('edit_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        // Check if method is attached to this zone
        if (!$shippingZone->methods()->where('shipping_method_id', $shippingMethod->id)->exists()) {
            throw new Exception('Shipping method is not attached to this zone.', 404);
        }

        try {
            // Detach method
            $shippingZone->methods()->detach($shippingMethod->id);

            Log::info('Shipping method detached from zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'zone_name' => $shippingZone->name,
                'method_id' => $shippingMethod->id,
                'method_name' => $shippingMethod->name,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to detach method from zone', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'method_id' => $shippingMethod->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to detach method from zone: ' . $e->getMessage());
        }
    }

    public function updateMethodSettings(ShippingZone $shippingZone, ShippingMethod $shippingMethod, array $data, User $user): void
    {
        if (!$user->hasPermission('edit_shipping_zones')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        // Check if method is attached to this zone
        $pivot = $shippingZone->methods()->where('shipping_method_id', $shippingMethod->id)->first();

        if (!$pivot) {
            throw new Exception('Shipping method is not attached to this zone.', 404);
        }

        try {
            // Build update data
            $updateData = [];
            if (isset($data['is_active'])) {
                $updateData['is_active'] = $data['is_active'];
            }
            if (isset($data['sort_order'])) {
                $updateData['sort_order'] = $data['sort_order'];
            }

            // Update pivot settings
            $shippingZone->methods()->updateExistingPivot($shippingMethod->id, $updateData);

            Log::info('Shipping method settings updated', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'zone_name' => $shippingZone->name,
                'method_id' => $shippingMethod->id,
                'method_name' => $shippingMethod->name,
                'updates' => $updateData,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to update method settings', [
                'user_id' => $user->id,
                'zone_id' => $shippingZone->id,
                'method_id' => $shippingMethod->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update method settings: ' . $e->getMessage());
        }
    }
}

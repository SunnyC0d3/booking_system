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

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $query = ShippingZone::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->has('country')) {
            $query->whereJsonContains('countries', strtoupper($request->input('country')));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('with_methods')) {
            $query->with(['methods' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order');
            }]);
        }

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

    public function show(Request $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingZone->loadCount(['methods', 'rates']);

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

    public function destroy(Request $request, ShippingZone $shippingZone)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        if ($shippingZone->rates()->exists()) {
            return $this->error('Cannot delete shipping zone that has associated rates.', 400);
        }

        if ($shippingZone->methods()->exists()) {
            $shippingZone->methods()->detach();
        }

        $shippingZone->delete();

        return $this->ok('Shipping zone deleted successfully.');
    }

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

        foreach ($zoneIds as $index => $zoneId) {
            ShippingZone::where('id', $zoneId)->update(['sort_order' => $index]);
        }

        return $this->ok('Shipping zones reordered successfully.');
    }

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

        if ($shippingZone->methods()->where('shipping_method_id', $methodId)->exists()) {
            return $this->error('Shipping method is already attached to this zone.', 400);
        }

        $shippingZone->methods()->attach($methodId, [
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return $this->ok('Shipping method attached to zone successfully.');
    }

    public function detachMethod(Request $request, ShippingZone $shippingZone, ShippingMethod $shippingMethod)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_shipping_zones')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        if (!$shippingZone->methods()->where('shipping_method_id', $shippingMethod->id)->exists()) {
            return $this->error('Shipping method is not attached to this zone.', 404);
        }

        $shippingZone->methods()->detach($shippingMethod->id);

        return $this->ok('Shipping method detached from zone successfully.');
    }

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

        $pivot = $shippingZone->methods()->where('shipping_method_id', $shippingMethod->id)->first();

        if (!$pivot) {
            return $this->error('Shipping method is not attached to this zone.', 404);
        }

        $updateData = [];
        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->boolean('is_active');
        }
        if ($request->has('sort_order')) {
            $updateData['sort_order'] = $request->input('sort_order');
        }

        $shippingZone->methods()->updateExistingPivot($shippingMethod->id, $updateData);

        return $this->ok('Shipping method settings updated successfully.');
    }
}

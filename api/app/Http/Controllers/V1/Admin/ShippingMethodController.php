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

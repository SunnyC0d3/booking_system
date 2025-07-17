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

    public function destroy(Request $request, ShippingRate $shippingRate)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_shipping_rates')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $shippingRate->delete();

        return $this->ok('Shipping rate deleted successfully.');
    }

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

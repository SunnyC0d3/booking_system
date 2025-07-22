<?php

namespace App\Services\V1\Shipping;

use App\Models\ShippingRate;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ShippingRateService
{
    public function getShippingRates(Request $request, User $user): array
    {
        if (!$user->hasPermission('view_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
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

        Log::info('Shipping rates retrieved', [
            'user_id' => $user->id,
            'total_rates' => $rates->total(),
            'filters' => $request->only(['shipping_method_id', 'shipping_zone_id', 'is_active', 'min_rate', 'max_rate', 'weight_range', 'total_range']),
        ]);

        return [
            'rates' => $rates,
            'message' => 'Shipping rates retrieved successfully.',
        ];
    }

    public function createShippingRate(array $data, User $user): ShippingRate
    {
        if (!$user->hasPermission('create_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $rate = ShippingRate::create($data);

            if ($data['with_relationships'] ?? false) {
                $rate->load(['shippingMethod', 'shippingZone']);
            }

            Log::info('Shipping rate created', [
                'user_id' => $user->id,
                'rate_id' => $rate->id,
                'method_id' => $rate->shipping_method_id,
                'zone_id' => $rate->shipping_zone_id,
                'rate_amount' => $rate->rate,
            ]);

            return $rate;

        } catch (Exception $e) {
            Log::error('Failed to create shipping rate', [
                'user_id' => $user->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to create shipping rate: ' . $e->getMessage());
        }
    }

    public function getShippingRate(ShippingRate $shippingRate, Request $request, User $user): ShippingRate
    {
        if (!$user->hasPermission('view_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        if ($request->boolean('with_relationships')) {
            $shippingRate->load(['shippingMethod', 'shippingZone']);
        }

        Log::info('Shipping rate retrieved', [
            'user_id' => $user->id,
            'rate_id' => $shippingRate->id,
            'method_id' => $shippingRate->shipping_method_id,
            'zone_id' => $shippingRate->shipping_zone_id,
        ]);

        return $shippingRate;
    }

    public function updateShippingRate(ShippingRate $shippingRate, array $data, User $user): ShippingRate
    {
        if (!$user->hasPermission('edit_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $oldData = $shippingRate->only(['rate', 'is_active', 'min_weight', 'max_weight']);
            $shippingRate->update($data);

            if ($data['with_relationships'] ?? false) {
                $shippingRate->load(['shippingMethod', 'shippingZone']);
            }

            Log::info('Shipping rate updated', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'old_data' => $oldData,
                'updated_fields' => array_keys($data),
            ]);

            return $shippingRate;

        } catch (Exception $e) {
            Log::error('Failed to update shipping rate', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update shipping rate: ' . $e->getMessage());
        }
    }

    public function deleteShippingRate(ShippingRate $shippingRate, User $user): void
    {
        if (!$user->hasPermission('delete_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $rateId = $shippingRate->id;
            $methodId = $shippingRate->shipping_method_id;
            $zoneId = $shippingRate->shipping_zone_id;

            $shippingRate->delete();

            Log::info('Shipping rate deleted', [
                'user_id' => $user->id,
                'rate_id' => $rateId,
                'method_id' => $methodId,
                'zone_id' => $zoneId,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to delete shipping rate', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to delete shipping rate: ' . $e->getMessage());
        }
    }

    public function activateShippingRate(ShippingRate $shippingRate, User $user): ShippingRate
    {
        if (!$user->hasPermission('edit_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $shippingRate->update(['is_active' => true]);

            Log::info('Shipping rate activated', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'method_id' => $shippingRate->shipping_method_id,
                'zone_id' => $shippingRate->shipping_zone_id,
            ]);

            return $shippingRate;

        } catch (Exception $e) {
            Log::error('Failed to activate shipping rate', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to activate shipping rate: ' . $e->getMessage());
        }
    }

    public function deactivateShippingRate(ShippingRate $shippingRate, User $user): ShippingRate
    {
        if (!$user->hasPermission('edit_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $shippingRate->update(['is_active' => false]);

            Log::info('Shipping rate deactivated', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'method_id' => $shippingRate->shipping_method_id,
                'zone_id' => $shippingRate->shipping_zone_id,
            ]);

            return $shippingRate;

        } catch (Exception $e) {
            Log::error('Failed to deactivate shipping rate', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to deactivate shipping rate: ' . $e->getMessage());
        }
    }

    public function bulkCreateShippingRates(array $ratesData, User $user): array
    {
        if (!$user->hasPermission('create_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        $ratesToCreate = [];
        $errors = [];

        try {
            foreach ($ratesData as $index => $rateData) {
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
                Log::warning('Bulk create shipping rates had conflicts', [
                    'user_id' => $user->id,
                    'total_rates' => count($ratesData),
                    'conflicts' => count($errors),
                    'errors' => $errors,
                ]);

                throw new Exception('Some rates could not be created due to conflicts.', 422);
            }

            ShippingRate::insert($ratesToCreate);

            Log::info('Bulk shipping rates created', [
                'user_id' => $user->id,
                'created_count' => count($ratesToCreate),
                'total_submitted' => count($ratesData),
            ]);

            return [
                'created_count' => count($ratesToCreate),
                'message' => count($ratesToCreate) . ' shipping rates created successfully.',
            ];

        } catch (Exception $e) {
            if ($e->getCode() === 422) {
                throw $e; // Re-throw validation errors
            }

            Log::error('Failed to bulk create shipping rates', [
                'user_id' => $user->id,
                'rates_data' => $ratesData,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to create shipping rates: ' . $e->getMessage());
        }
    }

    public function bulkUpdateShippingRates(array $rateIds, array $updates, User $user): array
    {
        if (!$user->hasPermission('edit_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $updateData = [];

            if (isset($updates['rate'])) {
                $updateData['rate'] = (int) round($updates['rate'] * 100);
            }

            if (isset($updates['free_threshold'])) {
                $updateData['free_threshold'] = $updates['free_threshold'] ? (int) round($updates['free_threshold'] * 100) : null;
            }

            if (isset($updates['is_active'])) {
                $updateData['is_active'] = $updates['is_active'];
            }

            if (empty($updateData)) {
                throw new Exception('No valid updates provided.', 422);
            }

            $updateData['updated_at'] = now();

            $updatedCount = ShippingRate::whereIn('id', $rateIds)->update($updateData);

            Log::info('Bulk shipping rates updated', [
                'user_id' => $user->id,
                'rate_ids' => $rateIds,
                'updated_count' => $updatedCount,
                'updates' => $updateData,
            ]);

            return [
                'updated_count' => $updatedCount,
                'message' => "{$updatedCount} shipping rates updated successfully.",
            ];

        } catch (Exception $e) {
            if ($e->getCode() === 422) {
                throw $e; // Re-throw validation errors
            }

            Log::error('Failed to bulk update shipping rates', [
                'user_id' => $user->id,
                'rate_ids' => $rateIds,
                'updates' => $updates,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to update shipping rates: ' . $e->getMessage());
        }
    }

    public function duplicateShippingRate(ShippingRate $shippingRate, array $zoneIds, User $user): array
    {
        if (!$user->hasPermission('create_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
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

            Log::info('Shipping rate duplicated', [
                'user_id' => $user->id,
                'original_rate_id' => $shippingRate->id,
                'target_zone_ids' => $zoneIds,
                'duplicated_count' => count($duplicatedRates),
            ]);

            return [
                'duplicated_count' => count($duplicatedRates),
                'rates' => $duplicatedRates,
                'message' => count($duplicatedRates) . ' shipping rates duplicated successfully.',
            ];

        } catch (Exception $e) {
            Log::error('Failed to duplicate shipping rate', [
                'user_id' => $user->id,
                'rate_id' => $shippingRate->id,
                'zone_ids' => $zoneIds,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to duplicate shipping rate: ' . $e->getMessage());
        }
    }

    public function calculateShippingCost(array $data, User $user): array
    {
        if (!$user->hasPermission('view_shipping_rates')) {
            throw new Exception('You do not have the required permissions.', 403);
        }

        try {
            $methodId = $data['shipping_method_id'];
            $zoneId = $data['shipping_zone_id'];
            $weight = $data['weight'];
            $total = (int) round($data['total'] * 100);

            $method = ShippingMethod::findOrFail($methodId);
            $zone = ShippingZone::findOrFail($zoneId);

            $rate = $method->getRateForZone($zone, $weight * 1000, $total);

            if (!$rate) {
                throw new Exception('No shipping rate found for the specified criteria.', 404);
            }

            $cost = $rate->calculateShippingCost($total);

            Log::info('Shipping cost calculated', [
                'user_id' => $user->id,
                'method_id' => $methodId,
                'zone_id' => $zoneId,
                'weight' => $weight,
                'total' => $total,
                'calculated_cost' => $cost,
                'rate_id' => $rate->id,
            ]);

            return [
                'rate' => $rate,
                'calculation' => [
                    'weight' => $weight,
                    'total' => $total,
                    'total_formatted' => '£' . number_format($total / 100, 2),
                    'shipping_cost' => $cost,
                    'shipping_cost_formatted' => '£' . number_format($cost / 100, 2),
                    'is_free' => $cost === 0,
                    'free_threshold_met' => $rate->free_threshold && $total >= $rate->free_threshold,
                ],
                'message' => 'Shipping cost calculated successfully.',
            ];

        } catch (Exception $e) {
            Log::error('Failed to calculate shipping cost', [
                'user_id' => $user->id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            if ($e->getCode() === 404) {
                throw $e; // Re-throw not found errors
            }

            throw new Exception('Failed to calculate shipping cost: ' . $e->getMessage());
        }
    }
}

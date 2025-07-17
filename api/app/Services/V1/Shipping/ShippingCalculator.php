<?php

namespace App\Services\V1\Shipping;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Constants\FulfillmentStatuses;
use App\Constants\ShippingClasses;
use Illuminate\Support\Collection;

class ShippingCalculator
{
    public function calculateForCart(Cart $cart, ShippingAddress $address): Collection
    {
        if (!$this->cartRequiresShipping($cart)) {
            return collect();
        }

        $zone = $address->getShippingZone();

        if (!$zone) {
            return collect();
        }

        $cartWeight = $this->calculateCartWeight($cart);
        $cartTotal = $cart->getTotalAmountInPennies();

        return $this->getAvailableMethodsForZone($zone, $cartWeight, $cartTotal);
    }

    public function calculateForOrder(Order $order, ShippingAddress $address): Collection
    {
        if (!$order->requiresShipping()) {
            return collect();
        }

        $zone = $address->getShippingZone();

        if (!$zone) {
            return collect();
        }

        $orderWeight = $order->getShippingWeight();
        $orderTotal = $order->getTotalAmountInPennies();

        return $this->getAvailableMethodsForZone($zone, $orderWeight, $orderTotal);
    }

    public function calculateForProducts(Collection $products, ShippingAddress $address, array $quantities = []): Collection
    {
        $totalWeight = 0;
        $totalValue = 0;
        $requiresShipping = false;
        $hasSpecialHandling = false;
        $shippingClasses = [];

        foreach ($products as $index => $product) {
            if ($product->requiresShipping()) {
                $requiresShipping = true;
                $quantity = $quantities[$index] ?? $quantities[$product->id] ?? 1;
                $totalWeight += $product->getWeightInKg() * $quantity;
                $totalValue += $product->price * $quantity;

                $shippingClass = $product->getShippingClass();
                $shippingClasses[] = $shippingClass;

                // Check for special handling requirements
                if (in_array($shippingClass, [
                    ShippingClasses::FRAGILE,
                    ShippingClasses::DANGEROUS,
                    ShippingClasses::REFRIGERATED,
                    ShippingClasses::OVERSIZED,
                    ShippingClasses::HEAVY
                ])) {
                    $hasSpecialHandling = true;
                }
            }
        }

        if (!$requiresShipping) {
            return collect();
        }

        $zone = $address->getShippingZone();

        if (!$zone) {
            return collect();
        }

        $methods = $this->getAvailableMethodsForZone($zone, $totalWeight, $totalValue);

        // Filter methods based on special handling requirements
        if ($hasSpecialHandling) {
            $methods = $this->filterMethodsForSpecialHandling($methods, $shippingClasses);
        }

        return $methods;
    }

    public function getQuickEstimate(string $countryCode, string $postcode, float $weight, int $valueInPennies): Collection
    {
        $tempAddress = new ShippingAddress([
            'country' => $countryCode,
            'postcode' => $postcode,
        ]);

        $zone = $tempAddress->getShippingZone();

        if (!$zone) {
            return collect();
        }

        return $this->getAvailableMethodsForZone($zone, $weight, $valueInPennies);
    }

    public function getCheapestMethod(ShippingAddress $address, float $weight, int $valueInPennies): ?array
    {
        $methods = $this->getQuickEstimate($address->country, $address->postcode, $weight, $valueInPennies);

        return $methods->sortBy('cost')->first();
    }

    public function getFastestMethod(ShippingAddress $address, float $weight, int $valueInPennies): ?array
    {
        $methods = $this->getQuickEstimate($address->country, $address->postcode, $weight, $valueInPennies);

        return $methods->sortBy('estimated_days_min')->first();
    }

    protected function getAvailableMethodsForZone(ShippingZone $zone, float $weightInKg, int $totalInPennies): Collection
    {
        return $zone->getAvailableMethods()
            ->get()
            ->map(function ($method) use ($zone, $weightInKg, $totalInPennies) {
                $rate = $method->getRateForZone($zone, $weightInKg * 1000, $totalInPennies);

                if (!$rate) {
                    return null;
                }

                $cost = $rate->calculateShippingCost($totalInPennies);

                return [
                    'id' => $method->id,
                    'name' => $method->name,
                    'description' => $method->description,
                    'carrier' => $method->carrier,
                    'service_code' => $method->service_code,
                    'cost' => $cost,
                    'cost_formatted' => '£' . number_format($cost / 100, 2),
                    'is_free' => $cost === 0,
                    'estimated_days_min' => $method->estimated_days_min,
                    'estimated_days_max' => $method->estimated_days_max,
                    'estimated_delivery' => $method->getEstimatedDeliveryAttribute(),
                    'estimated_date_min' => now()->addDays($method->estimated_days_min)->format('Y-m-d'),
                    'estimated_date_max' => now()->addDays($method->estimated_days_max)->format('Y-m-d'),
                    'rate_id' => $rate->id,
                    'zone_id' => $zone->id,
                    'metadata' => $method->metadata,
                ];
            })
            ->filter()
            ->values();
    }

    protected function filterMethodsForSpecialHandling(Collection $methods, array $shippingClasses): Collection
    {
        $specialClasses = array_intersect($shippingClasses, [
            ShippingClasses::FRAGILE,
            ShippingClasses::DANGEROUS,
            ShippingClasses::REFRIGERATED,
            ShippingClasses::OVERSIZED,
            ShippingClasses::HEAVY
        ]);

        if (empty($specialClasses)) {
            return $methods;
        }

        return $methods->filter(function ($method) use ($specialClasses) {
            // Filter out overnight/express for dangerous goods
            if (in_array(ShippingClasses::DANGEROUS, $specialClasses)) {
                if (stripos($method['name'], 'overnight') !== false ||
                    stripos($method['name'], 'express') !== false) {
                    return false;
                }
            }

            // Filter out standard shipping for refrigerated items
            if (in_array(ShippingClasses::REFRIGERATED, $specialClasses)) {
                if (stripos($method['name'], 'standard') !== false) {
                    return false;
                }
            }

            return true;
        });
    }

    protected function cartRequiresShipping(Cart $cart): bool
    {
        return $cart->cartItems()
            ->whereHas('product', function ($query) {
                $query->where('requires_shipping', true)
                    ->where('is_virtual', false);
            })
            ->exists();
    }

    protected function calculateCartWeight(Cart $cart): float
    {
        return $cart->cartItems()
            ->with('product')
            ->get()
            ->sum(function ($item) {
                if (!$item->product->requiresShipping()) {
                    return 0;
                }
                return $item->product->getWeightInKg() * $item->quantity;
            });
    }

    protected function calculateCartShippingClasses(Cart $cart): array
    {
        return $cart->cartItems()
            ->with('product')
            ->get()
            ->map(function ($item) {
                if (!$item->product->requiresShipping()) {
                    return null;
                }
                return $item->product->getShippingClass();
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    public function validateShippingMethod(int $methodId, ShippingAddress $address, float $weight = 0, int $total = 0): bool
    {
        $zone = $address->getShippingZone();

        if (!$zone) {
            return false;
        }

        $method = ShippingMethod::find($methodId);

        if (!$method || !$method->is_active) {
            return false;
        }

        if (!$method->isAvailableForZone($zone)) {
            return false;
        }

        $rate = $method->getRateForZone($zone, $weight * 1000, $total);

        return $rate !== null;
    }

    public function getShippingCostForMethod(int $methodId, ShippingAddress $address, float $weight = 0, int $total = 0): int
    {
        $zone = $address->getShippingZone();

        if (!$zone) {
            return 0;
        }

        $method = ShippingMethod::find($methodId);

        if (!$method) {
            return 0;
        }

        $rate = $method->getRateForZone($zone, $weight * 1000, $total);

        if (!$rate) {
            return 0;
        }

        return $rate->calculateShippingCost($total);
    }

    public function getShippingOptionsForCheckout(Cart $cart, ShippingAddress $address): array
    {
        $shippingMethods = $this->calculateForCart($cart, $address);

        return [
            'available_methods' => $shippingMethods,
            'cheapest_method' => $shippingMethods->sortBy('cost')->first(),
            'fastest_method' => $shippingMethods->sortBy('estimated_days_min')->first(),
            'requires_shipping' => $this->cartRequiresShipping($cart),
            'total_weight' => $this->calculateCartWeight($cart),
            'shipping_classes' => $this->calculateCartShippingClasses($cart),
            'shipping_zone' => $address->getShippingZone()?->name,
        ];
    }

    public function canMethodHandleShippingClass(ShippingMethod $method, string $shippingClass): bool
    {
        $metadata = $method->metadata ?? [];
        $supportedClasses = $metadata['supported_shipping_classes'] ?? ShippingClasses::all();

        return in_array($shippingClass, $supportedClasses);
    }

    public function getShippingRestrictions(Cart $cart): array
    {
        $restrictions = [];
        $shippingClasses = $this->calculateCartShippingClasses($cart);

        foreach ($shippingClasses as $class) {
            switch ($class) {
                case ShippingClasses::DANGEROUS:
                    $restrictions[] = 'Contains dangerous goods - special handling required';
                    break;
                case ShippingClasses::REFRIGERATED:
                    $restrictions[] = 'Requires refrigeration during transport';
                    break;
                case ShippingClasses::FRAGILE:
                    $restrictions[] = 'Fragile items - careful handling required';
                    break;
                case ShippingClasses::OVERSIZED:
                    $restrictions[] = 'Oversized items - may require special delivery';
                    break;
                case ShippingClasses::HEAVY:
                    $restrictions[] = 'Heavy items - may require additional handling fees';
                    break;
            }
        }

        return array_unique($restrictions);
    }
}

<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\ShippingAddress;
use App\Models\Product;
use App\Requests\V1\GetShippingQuoteRequest;
use App\Resources\V1\ShippingQuoteResource;
use App\Services\V1\Shipping\ShippingCalculator;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;

class ShippingCalculationController extends Controller
{
    use ApiResponses;

    protected ShippingCalculator $calculator;

    public function __construct(ShippingCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function getCartShippingQuote(GetShippingQuoteRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        $cart = $user->cart()->with('cartItems.product')->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty.', 400);
        }

        $address = ShippingAddress::where('user_id', $user->id)
            ->where('id', $data['shipping_address_id'])
            ->first();

        if (!$address) {
            return $this->error('Shipping address not found.', 404);
        }

        $shippingOptions = $this->calculator->calculateForCart($cart, $address);

        if ($shippingOptions->isEmpty()) {
            return $this->ok('No shipping options available for this address.', [
                'shipping_methods' => [],
                'cart_requires_shipping' => true,
                'shipping_restrictions' => $this->calculator->getShippingRestrictions($cart),
            ]);
        }

        return $this->ok('Shipping quotes retrieved successfully.', [
            'shipping_methods' => ShippingQuoteResource::collection($shippingOptions),
            'cart_summary' => [
                'total_weight' => $this->getCartWeight($cart),
                'total_amount' => $cart->getTotalAmountInPennies(),
                'total_amount_formatted' => $cart->getTotalAmountFormatted(),
                'items_count' => $cart->getTotalItemsCount(),
                'requires_shipping' => true,
            ],
            'shipping_address' => [
                'id' => $address->id,
                'name' => $address->name,
                'full_address' => $address->getFullAddressAttribute(),
                'country' => $address->country,
                'postcode' => $address->postcode,
            ],
            'shipping_restrictions' => $this->calculator->getShippingRestrictions($cart),
        ]);
    }

    public function getProductShippingQuote(Request $request)
    {
        $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:1'],
            'shipping_address_id' => ['required', 'integer', 'exists:shipping_addresses,id'],
        ]);

        $user = $request->user();
        $productIds = $request->input('product_ids');
        $quantities = $request->input('quantities');

        $products = Product::whereIn('id', $productIds)->get();

        if ($products->count() !== count($productIds)) {
            return $this->error('One or more products not found.', 404);
        }

        $address = ShippingAddress::where('user_id', $user->id)
            ->where('id', $request->input('shipping_address_id'))
            ->first();

        if (!$address) {
            return $this->error('Shipping address not found.', 404);
        }

        $shippingOptions = $this->calculator->calculateForProducts($products, $address, $quantities);

        if ($shippingOptions->isEmpty()) {
            return $this->ok('No shipping options available for these products.', [
                'shipping_methods' => [],
                'products_require_shipping' => $this->productsRequireShipping($products),
            ]);
        }

        return $this->ok('Product shipping quotes retrieved successfully.', [
            'shipping_methods' => ShippingQuoteResource::collection($shippingOptions),
            'products_summary' => $this->getProductsSummary($products, $quantities),
            'shipping_address' => [
                'id' => $address->id,
                'name' => $address->name,
                'full_address' => $address->getFullAddressAttribute(),
                'country' => $address->country,
                'postcode' => $address->postcode,
            ],
        ]);
    }

    public function getQuickEstimate(Request $request)
    {
        $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'postcode' => ['required', 'string', 'max:20'],
            'weight' => ['required', 'numeric', 'min:0'],
            'value' => ['required', 'numeric', 'min:0'],
        ]);

        $countryCode = strtoupper($request->input('country'));
        $postcode = strtoupper($request->input('postcode'));
        $weight = (float) $request->input('weight');
        $valueInPennies = (int) round($request->input('value') * 100);

        $shippingOptions = $this->calculator->getQuickEstimate(
            $countryCode,
            $postcode,
            $weight,
            $valueInPennies
        );

        if ($shippingOptions->isEmpty()) {
            return $this->ok('No shipping options available for this location.', [
                'shipping_methods' => [],
                'location' => [
                    'country' => $countryCode,
                    'postcode' => $postcode,
                ],
            ]);
        }

        return $this->ok('Quick shipping estimate retrieved successfully.', [
            'shipping_methods' => ShippingQuoteResource::collection($shippingOptions),
            'shipment_details' => [
                'weight' => $weight,
                'weight_unit' => 'kg',
                'value' => $valueInPennies,
                'value_formatted' => '£' . number_format($valueInPennies / 100, 2),
            ],
            'location' => [
                'country' => $countryCode,
                'postcode' => $postcode,
            ],
        ]);
    }

    public function getCheapestOption(Request $request)
    {
        $request->validate([
            'shipping_address_id' => ['required', 'integer', 'exists:shipping_addresses,id'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $address = ShippingAddress::where('user_id', $user->id)
            ->where('id', $request->input('shipping_address_id'))
            ->first();

        if (!$address) {
            return $this->error('Shipping address not found.', 404);
        }

        $weight = (float) ($request->input('weight') ?? 1.0);
        $valueInPennies = (int) round(($request->input('value') ?? 50) * 100);

        $cheapestMethod = $this->calculator->getCheapestMethod($address, $weight, $valueInPennies);

        if (!$cheapestMethod) {
            return $this->ok('No shipping options available.', [
                'cheapest_method' => null,
            ]);
        }

        return $this->ok('Cheapest shipping option retrieved successfully.', [
            'cheapest_method' => new ShippingQuoteResource($cheapestMethod),
            'address' => [
                'id' => $address->id,
                'country' => $address->country,
                'postcode' => $address->postcode,
            ],
        ]);
    }

    public function getFastestOption(Request $request)
    {
        $request->validate([
            'shipping_address_id' => ['required', 'integer', 'exists:shipping_addresses,id'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'value' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $address = ShippingAddress::where('user_id', $user->id)
            ->where('id', $request->input('shipping_address_id'))
            ->first();

        if (!$address) {
            return $this->error('Shipping address not found.', 404);
        }

        $weight = (float) ($request->input('weight') ?? 1.0);
        $valueInPennies = (int) round(($request->input('value') ?? 50) * 100);

        $fastestMethod = $this->calculator->getFastestMethod($address, $weight, $valueInPennies);

        if (!$fastestMethod) {
            return $this->ok('No shipping options available.', [
                'fastest_method' => null,
            ]);
        }

        return $this->ok('Fastest shipping option retrieved successfully.', [
            'fastest_method' => new ShippingQuoteResource($fastestMethod),
            'address' => [
                'id' => $address->id,
                'country' => $address->country,
                'postcode' => $address->postcode,
            ],
        ]);
    }

    public function validateShippingMethod(Request $request)
    {
        $request->validate([
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'shipping_address_id' => ['required', 'integer', 'exists:shipping_addresses,id'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $address = ShippingAddress::where('user_id', $user->id)
            ->where('id', $request->input('shipping_address_id'))
            ->first();

        if (!$address) {
            return $this->error('Shipping address not found.', 404);
        }

        $methodId = $request->input('shipping_method_id');
        $weight = (float) ($request->input('weight') ?? 0);
        $totalInPennies = (int) round(($request->input('total') ?? 0) * 100);

        $isValid = $this->calculator->validateShippingMethod($methodId, $address, $weight, $totalInPennies);
        $cost = $isValid ? $this->calculator->getShippingCostForMethod($methodId, $address, $weight, $totalInPennies) : 0;

        return $this->ok('Shipping method validation completed.', [
            'is_valid' => $isValid,
            'shipping_cost' => $cost,
            'shipping_cost_formatted' => '£' . number_format($cost / 100, 2),
            'method_id' => $methodId,
            'address_id' => $address->id,
        ]);
    }

    protected function getCartWeight(Cart $cart): float
    {
        return $cart->cartItems->sum(function ($item) {
            return $item->product->getWeightInKg() * $item->quantity;
        });
    }

    protected function productsRequireShipping($products): bool
    {
        return $products->some(function ($product) {
            return $product->requiresShipping();
        });
    }

    protected function getProductsSummary($products, $quantities): array
    {
        $totalWeight = 0;
        $totalValue = 0;

        foreach ($products as $index => $product) {
            $quantity = $quantities[$index] ?? $quantities[$product->id] ?? 1;
            $totalWeight += $product->getWeightInKg() * $quantity;
            $totalValue += $product->price * $quantity;
        }

        return [
            'total_weight' => $totalWeight,
            'total_weight_formatted' => $product->getWeightFormatted(),
            'total_value' => $totalValue,
            'total_value_formatted' => '£' . number_format($totalValue / 100, 2),
            'products_count' => $products->count(),
            'requires_shipping' => $this->productsRequireShipping($products),
        ];
    }
}

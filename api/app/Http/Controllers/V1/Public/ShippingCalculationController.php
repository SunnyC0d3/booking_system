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

    /**
     * Get shipping quotes for cart contents
     *
     * Calculate shipping costs for the current user's cart contents to a specific address.
     * This endpoint analyzes cart weight, dimensions, and value to provide accurate shipping
     * quotes from all applicable shipping methods. Essential for checkout process.
     *
     * @group Shipping Calculations
     * @authenticated
     *
     * @bodyParam shipping_address_id integer required The ID of the shipping address to calculate rates for. Example: 1
     *
     * @response 200 scenario="Shipping quotes retrieved successfully" {
     *   "message": "Shipping quotes retrieved successfully.",
     *   "data": {
     *     "shipping_methods": [
     *       {
     *         "id": 1,
     *         "name": "Standard Delivery",
     *         "carrier": "Royal Mail",
     *         "service_code": "tracked-48",
     *         "description": "Tracked delivery within 2-3 working days",
     *         "estimated_delivery": "2-3 days",
     *         "cost": 599,
     *         "cost_formatted": "£5.99",
     *         "is_free": false,
     *         "free_threshold": 10000,
     *         "free_threshold_formatted": "£100.00",
     *         "supports_tracking": true,
     *         "requires_signature": false,
     *         "estimated_delivery_date": "2025-01-17T17:00:00.000000Z",
     *         "transit_time": "2-3 days"
     *       },
     *       {
     *         "id": 2,
     *         "name": "Express Delivery",
     *         "carrier": "DPD",
     *         "service_code": "next-day",
     *         "description": "Next working day delivery by 1pm",
     *         "estimated_delivery": "1 day",
     *         "cost": 999,
     *         "cost_formatted": "£9.99",
     *         "is_free": false,
     *         "free_threshold": 15000,
     *         "free_threshold_formatted": "£150.00",
     *         "supports_tracking": true,
     *         "requires_signature": true,
     *         "estimated_delivery_date": "2025-01-16T13:00:00.000000Z",
     *         "transit_time": "1 day"
     *       }
     *     ],
     *     "cart_summary": {
     *       "total_weight": 2.5,
     *       "total_amount": 4599,
     *       "total_amount_formatted": "£45.99",
     *       "items_count": 3,
     *       "requires_shipping": true
     *     },
     *     "shipping_address": {
     *       "id": 1,
     *       "name": "John Smith",
     *       "full_address": "123 Main Street, Suite 100, London, SW1A 1AA, United Kingdom",
     *       "country": "GB",
     *       "postcode": "SW1A 1AA"
     *     },
     *     "shipping_restrictions": {
     *       "restricted_countries": [],
     *       "restricted_postcodes": [],
     *       "hazardous_items": false,
     *       "oversized_items": false,
     *       "restricted_items": []
     *     }
     *   }
     * }
     *
     * @response 400 scenario="Cart is empty" {
     *   "message": "Cart is empty."
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "Shipping address not found."
     * }
     *
     * @response 200 scenario="No shipping options available" {
     *   "message": "No shipping options available for this address.",
     *   "data": {
     *     "shipping_methods": [],
     *     "cart_requires_shipping": true,
     *     "shipping_restrictions": {
     *       "restricted_countries": ["US", "CA"],
     *       "restricted_postcodes": ["BT1", "BT2"],
     *       "hazardous_items": true,
     *       "oversized_items": false,
     *       "restricted_items": ["lithium_batteries"]
     *     }
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping address id field is required.",
     *     "The shipping address id must be an integer.",
     *     "The shipping address id must exist in shipping_addresses table."
     *   ]
     * }
     */
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

    /**
     * Get shipping quotes for specific products
     *
     * Calculate shipping costs for specific products and quantities to a shipping address.
     * This is useful for getting shipping estimates before adding items to cart or for
     * product pages showing shipping costs.
     *
     * @group Shipping Calculations
     * @authenticated
     *
     * @bodyParam product_ids array required Array of product IDs to calculate shipping for. Example: [1, 2, 3]
     * @bodyParam product_ids.* integer required Each product ID must be a valid product ID. Example: 1
     * @bodyParam quantities array required Array of quantities for each product (must match product_ids). Example: [2, 1, 3]
     * @bodyParam quantities.* integer required Each quantity must be a positive integer. Example: 2
     * @bodyParam shipping_address_id integer required The ID of the shipping address to calculate rates for. Example: 1
     *
     * @response 200 scenario="Product shipping quotes retrieved successfully" {
     *   "message": "Product shipping quotes retrieved successfully.",
     *   "data": {
     *     "shipping_methods": [
     *       {
     *         "id": 1,
     *         "name": "Standard Delivery",
     *         "carrier": "Royal Mail",
     *         "service_code": "tracked-48",
     *         "description": "Tracked delivery within 2-3 working days",
     *         "estimated_delivery": "2-3 days",
     *         "cost": 799,
     *         "cost_formatted": "£7.99",
     *         "is_free": false,
     *         "free_threshold": 10000,
     *         "free_threshold_formatted": "£100.00",
     *         "supports_tracking": true,
     *         "requires_signature": false,
     *         "estimated_delivery_date": "2025-01-17T17:00:00.000000Z",
     *         "transit_time": "2-3 days"
     *       }
     *     ],
     *     "products_summary": {
     *       "total_weight": 3.8,
     *       "total_weight_formatted": "3.8kg",
     *       "total_value": 7999,
     *       "total_value_formatted": "£79.99",
     *       "products_count": 3,
     *       "requires_shipping": true
     *     },
     *     "shipping_address": {
     *       "id": 1,
     *       "name": "John Smith",
     *       "full_address": "123 Main Street, Suite 100, London, SW1A 1AA, United Kingdom",
     *       "country": "GB",
     *       "postcode": "SW1A 1AA"
     *     }
     *   }
     * }
     *
     * @response 404 scenario="Products not found" {
     *   "message": "One or more products not found."
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "Shipping address not found."
     * }
     *
     * @response 200 scenario="No shipping options available" {
     *   "message": "No shipping options available for these products.",
     *   "data": {
     *     "shipping_methods": [],
     *     "products_require_shipping": true
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The product ids field is required.",
     *     "The product ids must be an array.",
     *     "The quantities field is required.",
     *     "The quantities must be an array.",
     *     "The shipping address id field is required."
     *   ]
     * }
     */
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

    /**
     * Get quick shipping estimate
     *
     * Get a quick shipping estimate for a specific location, weight, and value without
     * requiring authentication or specific products. This is useful for product pages
     * or marketing materials to show approximate shipping costs.
     *
     * @group Shipping Calculations
     *
     * @bodyParam country string required Two-letter country code (ISO 3166-1 alpha-2). Example: GB
     * @bodyParam postcode string required Postal code for the destination. Example: SW1A 1AA
     * @bodyParam weight numeric required Package weight in kilograms. Example: 2.5
     * @bodyParam value numeric required Package value in pounds. Example: 49.99
     *
     * @response 200 scenario="Quick shipping estimate retrieved successfully" {
     *   "message": "Quick shipping estimate retrieved successfully.",
     *   "data": {
     *     "shipping_methods": [
     *       {
     *         "id": 1,
     *         "name": "Standard Delivery",
     *         "carrier": "Royal Mail",
     *         "service_code": "tracked-48",
     *         "description": "Tracked delivery within 2-3 working days",
     *         "estimated_delivery": "2-3 days",
     *         "cost": 599,
     *         "cost_formatted": "£5.99",
     *         "is_free": false,
     *         "free_threshold": 10000,
     *         "free_threshold_formatted": "£100.00",
     *         "supports_tracking": true,
     *         "requires_signature": false,
     *         "estimated_delivery_date": "2025-01-17T17:00:00.000000Z",
     *         "transit_time": "2-3 days"
     *       },
     *       {
     *         "id": 2,
     *         "name": "Express Delivery",
     *         "carrier": "DPD",
     *         "service_code": "next-day",
     *         "description": "Next working day delivery by 1pm",
     *         "estimated_delivery": "1 day",
     *         "cost": 999,
     *         "cost_formatted": "£9.99",
     *         "is_free": false,
     *         "free_threshold": 15000,
     *         "free_threshold_formatted": "£150.00",
     *         "supports_tracking": true,
     *         "requires_signature": true,
     *         "estimated_delivery_date": "2025-01-16T13:00:00.000000Z",
     *         "transit_time": "1 day"
     *       }
     *     ],
     *     "shipment_details": {
     *       "weight": 2.5,
     *       "weight_unit": "kg",
     *       "value": 4999,
     *       "value_formatted": "£49.99"
     *     },
     *     "location": {
     *       "country": "GB",
     *       "postcode": "SW1A 1AA"
     *     }
     *   }
     * }
     *
     * @response 200 scenario="No shipping options available" {
     *   "message": "No shipping options available for this location.",
     *   "data": {
     *     "shipping_methods": [],
     *     "location": {
     *       "country": "GB",
     *       "postcode": "SW1A 1AA"
     *     }
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The country field is required.",
     *     "The country must be 2 characters.",
     *     "The postcode field is required.",
     *     "The weight field is required.",
     *     "The weight must be a number.",
     *     "The value field is required."
     *   ]
     * }
     */
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

    /**
     * Get cheapest shipping option
     *
     * Find the cheapest shipping option for a specific address and shipment details.
     * This is useful for budget-conscious customers or for displaying the minimum
     * shipping cost on product pages.
     *
     * @group Shipping Calculations
     * @authenticated
     *
     * @bodyParam shipping_address_id integer required The ID of the shipping address to calculate rates for. Example: 1
     * @bodyParam weight numeric optional Package weight in kilograms. Default: 1.0. Example: 1.5
     * @bodyParam value numeric optional Package value in pounds. Default: 50.0. Example: 75.99
     *
     * @response 200 scenario="Cheapest shipping option retrieved successfully" {
     *   "message": "Cheapest shipping option retrieved successfully.",
     *   "data": {
     *     "cheapest_method": {
     *       "id": 1,
     *       "name": "Standard Delivery",
     *       "carrier": "Royal Mail",
     *       "service_code": "tracked-48",
     *       "description": "Tracked delivery within 2-3 working days",
     *       "estimated_delivery": "2-3 days",
     *       "cost": 599,
     *       "cost_formatted": "£5.99",
     *       "is_free": false,
     *       "free_threshold": 10000,
     *       "free_threshold_formatted": "£100.00",
     *       "supports_tracking": true,
     *       "requires_signature": false,
     *       "estimated_delivery_date": "2025-01-17T17:00:00.000000Z",
     *       "transit_time": "2-3 days"
     *     },
     *     "address": {
     *       "id": 1,
     *       "country": "GB",
     *       "postcode": "SW1A 1AA"
     *     }
     *   }
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "Shipping address not found."
     * }
     *
     * @response 200 scenario="No shipping options available" {
     *   "message": "No shipping options available.",
     *   "data": {
     *     "cheapest_method": null
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping address id field is required.",
     *     "The shipping address id must be an integer.",
     *     "The weight must be a number.",
     *     "The value must be a number."
     *   ]
     * }
     */
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

    /**
     * Get fastest shipping option
     *
     * Find the fastest shipping option for a specific address and shipment details.
     * This is useful for urgent deliveries or when customers prioritize speed over cost.
     *
     * @group Shipping Calculations
     * @authenticated
     *
     * @bodyParam shipping_address_id integer required The ID of the shipping address to calculate rates for. Example: 1
     * @bodyParam weight numeric optional Package weight in kilograms. Default: 1.0. Example: 1.5
     * @bodyParam value numeric optional Package value in pounds. Default: 50.0. Example: 75.99
     *
     * @response 200 scenario="Fastest shipping option retrieved successfully" {
     *   "message": "Fastest shipping option retrieved successfully.",
     *   "data": {
     *     "fastest_method": {
     *       "id": 2,
     *       "name": "Express Delivery",
     *       "carrier": "DPD",
     *       "service_code": "next-day",
     *       "description": "Next working day delivery by 1pm",
     *       "estimated_delivery": "1 day",
     *       "cost": 999,
     *       "cost_formatted": "£9.99",
     *       "is_free": false,
     *       "free_threshold": 15000,
     *       "free_threshold_formatted": "£150.00",
     *       "supports_tracking": true,
     *       "requires_signature": true,
     *       "estimated_delivery_date": "2025-01-16T13:00:00.000000Z",
     *       "transit_time": "1 day"
     *     },
     *     "address": {
     *       "id": 1,
     *       "country": "GB",
     *       "postcode": "SW1A 1AA"
     *     }
     *   }
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "Shipping address not found."
     * }
     *
     * @response 200 scenario="No shipping options available" {
     *   "message": "No shipping options available.",
     *   "data": {
     *     "fastest_method": null
     *   }
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping address id field is required.",
     *     "The shipping address id must be an integer.",
     *     "The weight must be a number.",
     *     "The value must be a number."
     *   ]
     * }
     */
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

    /**
     * Validate shipping method for order
     *
     * Validate that a specific shipping method is available and calculate the exact cost
     * for a given address, weight, and order total. This is used during checkout to
     * ensure the selected shipping method is still valid and get the final cost.
     *
     * @group Shipping Calculations
     * @authenticated
     *
     * @bodyParam shipping_method_id integer required The ID of the shipping method to validate. Example: 1
     * @bodyParam shipping_address_id integer required The ID of the shipping address. Example: 1
     * @bodyParam weight numeric optional Package weight in kilograms. Default: 0. Example: 2.5
     * @bodyParam total numeric optional Order total in pounds. Default: 0. Example: 89.99
     *
     * @response 200 scenario="Shipping method validation completed" {
     *   "message": "Shipping method validation completed.",
     *   "data": {
     *     "is_valid": true,
     *     "shipping_cost": 599,
     *     "shipping_cost_formatted": "£5.99",
     *     "method_id": 1,
     *     "address_id": 1
     *   }
     * }
     *
     * @response 200 scenario="Shipping method not valid" {
     *   "message": "Shipping method validation completed.",
     *   "data": {
     *     "is_valid": false,
     *     "shipping_cost": 0,
     *     "shipping_cost_formatted": "£0.00",
     *     "method_id": 1,
     *     "address_id": 1
     *   }
     * }
     *
     * @response 404 scenario="Shipping address not found" {
     *   "message": "Shipping address not found."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping method id field is required.",
     *     "The shipping method id must exist in shipping_methods table.",
     *     "The shipping address id field is required.",
     *     "The shipping address id must exist in shipping_addresses table."
     *   ]
     * }
     */
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

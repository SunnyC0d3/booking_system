<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Services\V1\Orders\CheckoutService;
use App\Models\Cart;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    use ApiResponses;

    protected CheckoutService $checkoutService;

    public function __construct(CheckoutService $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * Get checkout summary with shipping costs
     *
     * Calculate the total checkout cost including shipping for the current cart.
     * This endpoint provides a preview of costs before final order creation.
     *
     * @group Checkout
     * @authenticated
     *
     * @bodyParam shipping_method_id integer required The ID of the shipping method. Example: 1
     * @bodyParam shipping_address_id integer required The ID of the shipping address. Example: 1
     *
     * @response 200 scenario="Checkout summary calculated successfully" {
     *   "message": "Checkout summary calculated successfully.",
     *   "data": {
     *     "items_total": 4599,
     *     "items_total_formatted": "£45.99",
     *     "shipping_cost": 599,
     *     "shipping_cost_formatted": "£5.99",
     *     "total_amount": 5198,
     *     "total_amount_formatted": "£51.98",
     *     "requires_shipping": true
     *   }
     * }
     *
     * @response 400 scenario="Cart is empty" {
     *   "message": "Cart is empty."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The shipping method id field is required.",
     *     "The shipping address id field is required."
     *   ]
     * }
     */
    public function getCheckoutSummary(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'shipping_address_id' => ['required', 'integer', 'exists:shipping_addresses,id'],
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->with('cartItems.product')->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty.', 400);
        }

        try {
            $summary = $this->checkoutService->getCheckoutSummary(
                $cart,
                $request->input('shipping_method_id'),
                $request->input('shipping_address_id')
            );

            return $this->ok('Checkout summary calculated successfully.', $summary);

        } catch (\Exception $e) {
            return $this->error('Failed to calculate checkout summary: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Validate checkout data
     *
     * Validate that the selected shipping method and address are compatible
     * with the current cart contents before proceeding to payment.
     *
     * @group Checkout
     * @authenticated
     *
     * @bodyParam shipping_method_id integer required The ID of the shipping method. Example: 1
     * @bodyParam shipping_address_id integer required The ID of the shipping address. Example: 1
     *
     * @response 200 scenario="Checkout validation successful" {
     *   "message": "Checkout validation successful.",
     *   "data": {
     *     "valid": true,
     *     "shipping_available": true,
     *     "estimated_delivery": "2025-01-20T17:00:00.000000Z"
     *   }
     * }
     *
     * @response 400 scenario="Validation failed" {
     *   "message": "Selected shipping method is not available for this address."
     * }
     */
    public function validateCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'shipping_address_id' => ['required', 'integer', 'exists:shipping_addresses,id'],
        ]);

        $user = $request->user();
        $cart = Cart::where('user_id', $user->id)->with('cartItems.product')->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty.', 400);
        }

        try {
            $this->checkoutService->getCheckoutSummary(
                $cart,
                $request->input('shipping_method_id'),
                $request->input('shipping_address_id')
            );

            return $this->ok('Checkout validation successful.', [
                'valid' => true,
                'shipping_available' => true,
            ]);

        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}

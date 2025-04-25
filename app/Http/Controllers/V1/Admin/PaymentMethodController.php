<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\PaymentMethod as DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;
use App\Services\V1\Payments\PaymentMethod;

class PaymentMethodController extends Controller
{
    use ApiResponses;

    private $paymentMethod;

    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Retrieve all payment methods.
     *
     * @group Payment Methods
     * @authenticated
     *
     * @response 200 {
     *   "message": "Payment Methods retrieved successfully.",
     *   "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request) {
        try {
            return $this->paymentMethod->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new payment method.
     *
     * @group Payment Methods
     * @authenticated
     *
     * @bodyParam name string required The name of the payment method. Example: "Google Pay"
     *
     * @response 200 {
     *   "message": "Payment Method created successfully.",
     *   "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function store(Request $request) {
        try {
            return $this->paymentMethod->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update a payment method.
     *
     * @group Payment Methods
     * @authenticated
     *
     * @bodyParam name string required The name of the payment method. Example: "Google Pay"
     *
     * @response 200 {
     *   "message": "Payment Method updated successfully.",
     *   "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function update(Request $request, DB $paymentMethod) {
        try {
            return $this->paymentMethod->update($request, $paymentMethod);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a permission.
     *
     * @group Payment Methods
     * @authenticated
     *
     * @response 200 {
     *   "message": "Payment Method deleted successfully.",
     *   "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function destroy(Request $request, DB $paymentMethod) {
        try {
            return $this->paymentMethod->delete($request, $paymentMethod);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

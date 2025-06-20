<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\PaymentMethod as DB;
use App\Requests\V1\StorePaymentMethodRequest;
use App\Requests\V1\UpdatePaymentMethodRequest;
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
     * Retrieve all payment methods
     *
     * Get a complete list of all available payment methods in the system. This endpoint provides
     * administrators with information about supported payment gateways and methods that customers
     * can use to complete their purchases. Essential for payment system configuration and
     * troubleshooting payment-related issues.
     *
     * @group Payment Method Management
     * @authenticated
     *
     * @response 200 scenario="Payment methods retrieved successfully" {
     *   "message": "Payment Methods retrieved successfully.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "stripe",
     *       "created_at": "2024-12-01T10:00:00.000000Z",
     *       "updated_at": "2024-12-01T10:00:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "paypal",
     *       "created_at": "2024-12-01T10:05:00.000000Z",
     *       "updated_at": "2024-12-01T10:05:00.000000Z"
     *     },
     *     {
     *       "id": 3,
     *       "name": "apple pay",
     *       "created_at": "2024-12-01T10:10:00.000000Z",
     *       "updated_at": "2024-12-01T10:10:00.000000Z"
     *     },
     *     {
     *       "id": 4,
     *       "name": "google pay",
     *       "created_at": "2024-12-01T10:15:00.000000Z",
     *       "updated_at": "2024-12-01T10:15:00.000000Z"
     *     },
     *     {
     *       "id": 5,
     *       "name": "bank transfer",
     *       "created_at": "2024-12-01T10:20:00.000000Z",
     *       "updated_at": "2024-12-01T10:20:00.000000Z"
     *     },
     *     {
     *       "id": 6,
     *       "name": "card",
     *       "created_at": "2024-12-01T10:25:00.000000Z",
     *       "updated_at": "2024-12-01T10:25:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 200 scenario="No payment methods configured" {
     *   "message": "Payment Methods retrieved successfully.",
     *   "data": []
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
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
     * Create a new payment method
     *
     * Add a new payment method to the system. Payment methods must be from a predefined list
     * of supported gateways and cannot be duplicated. This endpoint is essential for configuring
     * which payment options are available to customers during checkout.
     *
     * @group Payment Method Management
     * @authenticated
     *
     * @bodyParam name string required The name of the payment method. Must be one of the supported payment methods: card, paypal, bank transfer, apple pay, google pay, stripe. Example: "google pay"
     *
     * @response 200 scenario="Payment method created successfully" {
     *   "message": "Payment Method created successfully.",
     *   "data": {
     *     "id": 7,
     *     "name": "google pay",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The selected name is invalid. Supported methods: card, paypal, bank transfer, apple pay, google pay, stripe.",
     *     "The name has already been taken."
     *   ]
     * }
     *
     * @response 422 scenario="Duplicate payment method" {
     *   "errors": [
     *     "The name has already been taken."
     *   ]
     * }
     */
    public function store(StorePaymentMethodRequest $request) {
        try {
            return $this->paymentMethod->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing payment method
     *
     * Modify an existing payment method in the system. The new name must be from the supported
     * list and cannot conflict with other existing payment methods. Use this endpoint to correct
     * payment method names or replace deprecated methods with updated versions.
     *
     * @group Payment Method Management
     * @authenticated
     *
     * @urlParam paymentMethod integer required The ID of the payment method to update. Example: 3
     *
     * @bodyParam name string required The updated name of the payment method. Must be one of the supported payment methods: card, paypal, bank transfer, apple pay, google pay, stripe. Example: "apple pay"
     *
     * @response 200 scenario="Payment method updated successfully" {
     *   "message": "Payment Method updated successfully.",
     *   "data": {
     *     "id": 3,
     *     "name": "apple pay",
     *     "created_at": "2024-12-01T10:10:00.000000Z",
     *     "updated_at": "2025-01-16T14:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Payment method not found" {
     *   "message": "No query results for model [App\\Models\\PaymentMethod] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The selected name is invalid. Supported methods: card, paypal, bank transfer, apple pay, google pay, stripe.",
     *     "The name has already been taken."
     *   ]
     * }
     *
     * @response 422 scenario="Name already exists" {
     *   "errors": [
     *     "The name has already been taken."
     *   ]
     * }
     */
    public function update(UpdatePaymentMethodRequest $request, DB $paymentMethod) {
        try {
            return $this->paymentMethod->update($request, $paymentMethod);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a payment method
     *
     * Permanently remove a payment method from the system. This action is irreversible and will
     * prevent customers from using this payment method for future transactions. Exercise caution
     * as this may affect existing payment configurations and customer checkout options.
     *
     * **Warning**: Deleting a payment method that is currently being used by active payment
     * integrations may cause payment processing issues.
     *
     * @group Payment Method Management
     * @authenticated
     *
     * @urlParam paymentMethod integer required The ID of the payment method to delete. Example: 5
     *
     * @response 200 scenario="Payment method deleted successfully" {
     *   "message": "Payment Method deleted successfully.",
     *   "data": []
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Payment method not found" {
     *   "message": "No query results for model [App\\Models\\PaymentMethod] 999"
     * }
     *
     * @response 409 scenario="Payment method in use" {
     *   "message": "Cannot delete payment method that has associated payments or transactions."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *   "message": "An error occurred while deleting the payment method."
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

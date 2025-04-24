<?php

namespace App\Services\V1\Payments;

use Illuminate\Http\Request;
use App\Models\PaymentMethod as DB;
use App\Traits\V1\ApiResponses;

class PaymentMethod
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_payment_methods')) {
            $methods = PaymentMethod::all();

            return response()->json([
                'message' => 'Payment methods retrieved successfully.',
                'data' => $methods,
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $paymentMethod)
    {
        $user = $request->user();

        if ($user->hasPermission('view_payment_methods')) {
            return response()->json([
                'message' => 'Payment method retrieved successfully.',
                'data' => $paymentMethod,
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_payment_methods')) {
            $method = PaymentMethod::create($request->validated());

            return response()->json([
                'message' => 'Payment method created successfully.',
                'data' => $method,
            ], 201);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $paymentMethod)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_payment_methods')) {
            $paymentMethod->update($request->validated());

            return response()->json([
                'message' => 'Payment method updated successfully.',
                'data' => $paymentMethod,
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $paymentMethod)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_payment_methods')) {
            $paymentMethod->delete();

            return response()->json([
                'message' => 'Payment method deleted successfully.',
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

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
            $paymentMethods = DB::all();

            return $this->ok('Payment Methods retrieved successfully.', $paymentMethods);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $paymentMethod)
    {
        $user = $request->user();

        if ($user->hasPermission('view_payment_methods')) {
            return $this->ok('Payment Method retrieved successfully.', $paymentMethod);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_payment_methods')) {
            $data = $request->validated();

            $paymentMethod = DB::create($data);

            return $this->ok('Payment Method created successfully.', $paymentMethod);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $paymentMethod)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_payment_methods')) {
            $data = $request->validated();

            $paymentMethod->update($data);

            return $this->ok('Payment Method updated successfully.', $paymentMethod);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $paymentMethod)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_payment_methods')) {
            $paymentMethod->forceDelete();

            return $this->ok('Payment Method deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

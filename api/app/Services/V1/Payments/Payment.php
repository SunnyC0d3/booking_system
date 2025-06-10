<?php

namespace App\Services\V1\Payments;


use App\Models\Payment as PaymentDB;
use App\Resources\V1\PaymentResource;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class Payment
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_payments')) {
            $payments = PaymentDB::with([
                'user',
                'order',
            ])
            ->latest()
            ->paginate(15);

            return $this->ok('Payments retrieved.', PaymentResource::collection($payments));
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

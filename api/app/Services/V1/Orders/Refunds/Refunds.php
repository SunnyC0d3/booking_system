<?php

namespace App\Services\V1\Orders\Refunds;


use App\Models\OrderRefund as OrderRefundDB;
use App\Resources\V1\OrderRefundResource;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class Refunds
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('manage_refunds')) {
            $returns = OrderRefundDB::with([
                'orderReturn.orderItem.product',
                'orderReturn.orderItem.order.user',
                'orderReturn.orderItem.order.payments',
                'status'
            ])->latest()->paginate(20);

            return $this->ok('Order refunds retrieved.', OrderRefundResource::collection($returns));
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

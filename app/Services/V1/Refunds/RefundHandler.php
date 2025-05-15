<?php

namespace App\Services\V1\Refunds;

use Illuminate\Http\Request;
interface RefundHandler
{
    public function refund(Request $request, int $orderReturnId);
}

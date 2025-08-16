<?php

namespace App\Services\V1\Refunds;

use Illuminate\Http\Request;

interface RefundHandlerInterface
{
    public function refund(Request $request, int $id);
}

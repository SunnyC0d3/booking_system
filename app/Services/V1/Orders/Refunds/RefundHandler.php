<?php

namespace App\Services\V1\Orders\Refunds;

use Illuminate\Http\Request;

interface RefundHandler
{
    public function refund(Request $request, int $orderReturnId, bool $webhookEnabled = false);
}

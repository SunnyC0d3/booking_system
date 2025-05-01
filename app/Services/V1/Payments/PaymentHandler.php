<?php

namespace App\Services\V1\Payments;

use Illuminate\Http\Request;
interface PaymentHandler
{
    public function createPayment(Request $request);
}

<?php

namespace App\Services\V1\Payments;

use Illuminate\Http\Request;
interface PaymentHandlerInterface
{
    public function createPayment(Request $request);

    public function verifyPayment(Request $request);
}

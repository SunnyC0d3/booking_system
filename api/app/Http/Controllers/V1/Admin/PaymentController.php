<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Payments\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;

class PaymentController extends Controller
{
    use ApiResponses;

    private $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function index(Request $request)
    {
        try {
            return $this->payment->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

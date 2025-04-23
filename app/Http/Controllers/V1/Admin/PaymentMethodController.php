<?php

namespace App\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;
use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    use ApiResponses;

    public function index() {}

    public function create() {}

    public function store(Request $request) {}

    public function edit(PaymentMethod $paymentMethod) {}

    public function update(Request $request, PaymentMethod $paymentMethod) {}

    public function destroy(PaymentMethod $paymentMethod) {}
}

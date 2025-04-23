<?php

namespace App\Http\Controllers\V1\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;
use App\Models\Payment;

class PaymentController extends Controller
{
    use ApiResponses;

    public function index() {}

    public function create() {}

    public function store(Request $request) {}

    public function edit(Payment $payment) {}

    public function update(Request $request, Payment $payment) {}

    public function destroy(Payment $payment) {}
}

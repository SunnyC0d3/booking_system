<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderRefund;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class RefundController extends Controller
{
    public function index() {}

    public function create() {}

    public function store(Request $request) {}

    public function show(OrderRefund $orderRefund) {}

    public function edit(OrderRefund $orderRefund) {}

    public function update(Request $request, OrderRefund $orderRefund) {}

    public function destroy(OrderRefund $orderRefund) {}
}

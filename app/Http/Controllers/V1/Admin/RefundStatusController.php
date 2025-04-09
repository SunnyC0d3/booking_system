<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderRefundStatus;
use App\Traits\V1\ApiResponses;

class RefundStatusController extends Controller
{
    public function index() {}

    public function create() {}

    public function store(Request $request) {}

    public function edit(OrderRefundStatus $orderRefundStatus) {}

    public function update(Request $request, OrderRefundStatus $orderRefundStatus) {}

    public function destroy(OrderRefundStatus $orderRefundStatus) {}
}

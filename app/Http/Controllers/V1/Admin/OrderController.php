<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;

class OrderController extends Controller
{
    public function index() {}

    public function create() {}

    public function store(Request $request) {}

    public function show(Order $order) {}

    public function edit(Order $order) {}

    public function update(Request $request, Order $order) {}

    public function destroy(Order $order) {}

    public function updateStatus(Order $order) {}
}

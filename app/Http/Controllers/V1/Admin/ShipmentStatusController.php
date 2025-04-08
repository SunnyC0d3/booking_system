<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\OrderShipmentStatus;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;

class ShipmentStatusController extends Controller
{
    public function index() {}

    public function create() {}

    public function store(Request $request) {}

    public function edit(OrderShipmentStatus $orderShipmentStatus) {}

    public function update(Request $request, OrderShipmentStatus $orderShipmentStatus) {}

    public function destroy(OrderShipmentStatus $orderShipmentStatus) {}
}

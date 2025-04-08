<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrderReturn;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;

class ReturnController extends Controller
{
    public function index() {}

    public function create() {}

    public function store(Request $request) {}

    public function show(OrderReturn $orderReturn) {}

    public function edit(OrderReturn $orderReturn) {}

    public function update(Request $request, OrderReturn $orderReturn) {}

    public function destroy(OrderReturn $orderReturn) {}
}

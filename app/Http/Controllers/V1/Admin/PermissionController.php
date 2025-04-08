<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;

class PermissionController extends Controller
{
    public function index() {}

    public function store(Request $request) {}

    public function update(Request $request, Permission $permission) {}

    public function destroy(Permission $permission) {}
}
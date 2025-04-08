<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;

class RoleController extends Controller
{
    public function index() {}

    public function store(Request $request) {}

    public function update(Request $request, Role $role) {}

    public function destroy(Role $role) {}
}
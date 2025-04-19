<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    public function index(Role $role)
    {
        return response()->json([
            'role' => $role->name,
            'permissions' => $role->permissions()->pluck('name')
        ]);
    }

    public function assign(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $permissions = Permission::whereIn('name', $validated['permissions'])->get();
        $role->permissions()->syncWithoutDetaching($permissions);

        return response()->json(['message' => 'Permissions assigned successfully']);
    }

    public function assignAllPermissions(Role $role)
    {
        $role->permissions()->sync(Permission::pluck('id'));
        return response()->json(['message' => 'All permissions assigned']);
    }

    public function revoke(Role $role, Permission $permission)
    {
        $role->permissions()->detach($permission->id);

        return response()->json(['message' => 'Permission revoked successfully']);
    }
}

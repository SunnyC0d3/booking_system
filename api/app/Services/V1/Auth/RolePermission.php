<?php

namespace App\Services\V1\Auth;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class RolePermission
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request, Role $role)
    {
        $user = $request->user();

        if ($user->hasPermission('view_roles') && $user->hasPermission('view_permissions')) {
            return $this->ok('Role and Permission retrieved successfully!', [
                'role' => $role->name,
                'permissions' => $role->permissions()->pluck('name')
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function assign(Request $request, Role $role)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_roles') && $user->hasPermission('edit_permissions')) {
            $data = $request->validated();

            $permissions = Permission::whereIn('name', $data['permissions'])->get();
            $role->permissions()->syncWithoutDetaching($permissions);

            return $this->ok('Permissions assigned successfully');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function assignAll(Request $request, Role $role)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_roles') && $user->hasPermission('edit_permissions')) {
            $role->permissions()->sync(Permission::pluck('id'));

            return $this->ok('Permissions assigned successfully');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function revoke(Request $request, Role $role, Permission $permission)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_roles') && $user->hasPermission('delete_permissions')) {
            $role->permissions()->detach($permission->id);

            return $this->ok('Permission revoked successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

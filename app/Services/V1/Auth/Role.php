<?php

namespace App\Services\V1\Auth;

use App\Models\Role as DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class Role
{
    use ApiResponses;

    public function __construct() {}

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_roles')) {
            $roles = DB::all();
            return $this->ok('Roles retrieved successfully.', $roles);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $role)
    {
        $user = $request->user();

        if ($user->hasPermission('view_roles')) {
            return $this->ok('Role retrieved successfully.', $role);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_roles')) {
            $data = $request->validated();

            $role = DB::create($data);
            return $this->ok('Role created successfully.', $role);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $role)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_roles')) {
            $data = $request->validated();

            $role->update($data);
            return $this->ok('Role updated successfully.', $role);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $role)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_roles')) {
            $role->forceDelete();
            return $this->ok('Role deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

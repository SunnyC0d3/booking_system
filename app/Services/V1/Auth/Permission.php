<?php

namespace App\Services\V1\Auth;

use App\Models\Permission as DB;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;

class Permission
{
    use ApiResponses;

    public function __construct() {}

    public function all(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('view_permissions')) {
            $permissions = DB::all();
            return $this->ok('Permissions retrieved successfully.', $permissions);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $permission)
    {
        $user = $request->user();

        if ($user->hasPermission('view_permissions')) {
            return $this->ok('Permission retrieved successfully.', $permission);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_permissions')) {
            $data = $request->validated();

            $permission = DB::create($data);
            return $this->ok('Permission created successfully.', $permission);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $permission)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_permissions')) {
            $data = $request->validated();

            $permission->update($data);
            return $this->ok('Permission updated successfully.', $permission);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $permission)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_permissions')) {
            $permission->forceDelete();
            return $this->ok('Permission deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

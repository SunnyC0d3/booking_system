<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Requests\V1\AssignPermissionsRequest;
use App\Services\V1\Auth\RolePermission;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use \Exception;

class RolePermissionController extends Controller
{
    use ApiResponses;

    private $role_permission;

    public function __construct(RolePermission $role_permission)
    {
        $this->role_permission = $role_permission;
    }

    public function index(Request $request, Role $role)
    {
        try {
            return $this->role_permission->all($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function assign(AssignPermissionsRequest $request, Role $role)
    {
        try {
            return $this->role_permission->assign($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function assignAllPermissions(Request $request, Role $role)
    {
        try {
            return $this->role_permission->assignAll($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function revoke(Request $request, Role $role, Permission $permission)
    {
        try {
            return $this->role_permission->revoke($request, $role, $permission);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

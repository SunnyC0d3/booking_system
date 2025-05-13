<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Requests\V1\AssignPermissionRequest;
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

    /**
     * Retrieve all permissions for a role.
     *
     * @group Role Permissions
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @urlParam role integer required The ID of the role. Example: 1
     *
     * @response 200 {
     *   "message": "Permissions retrieved successfully.",
     *   "data": []
     * }
     *
     * @response 403 {
     *   "message": "You do not have the required permissions."
     * }
     */

    public function index(Request $request, Role $role)
    {
        try {
            return $this->role_permission->all($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Assign specific permissions to a role.
     *
     * @group Role Permissions
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @urlParam role integer required The ID of the role. Example: 1
     *
     * @bodyParam permissions array required List of permission names to assign. Example: ["create_users", "edit_users"]
     * @bodyParam permissions.* string required The name of each permission. Example: "create_users"
     *
     * @response 200 {
     *   "message": "Permissions assigned successfully.",
     *   "data": {}
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "permissions": ["The permissions field is required."]
     *   }
     * }
     */
    public function assign(AssignPermissionRequest $request, Role $role)
    {
        try {
            return $this->role_permission->assign($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Assign all available permissions to a role.
     *
     * @group Role Permissions
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @urlParam role integer required The ID of the role. Example: 1
     *
     * @response 200 {
     *   "message": "All permissions assigned successfully.",
     *   "data": {}
     * }
     *
     * @response 403 {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function assignAllPermissions(Request $request, Role $role)
    {
        try {
            return $this->role_permission->assignAll($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Revoke a specific permission from a role.
     *
     * @group Role Permissions
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @urlParam role integer required The ID of the role. Example: 1
     * @urlParam permission integer required The ID of the permission to revoke. Example: 5
     *
     * @response 200 {
     *   "message": "Permission revoked successfully.",
     *   "data": {}
     * }
     *
     * @response 403 {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function revoke(Request $request, Role $role, Permission $permission)
    {
        try {
            return $this->role_permission->revoke($request, $role, $permission);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

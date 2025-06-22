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
     * Retrieve all permissions for a specific role
     *
     * Get a complete list of permissions assigned to a specific role. This endpoint is essential for
     * understanding what actions users with this role can perform within the system. It provides
     * administrators with visibility into the current permission structure and helps with access
     * control auditing and role management decisions.
     *
     * @group Role Permission Management
     * @authenticated
     *
     * @urlParam role integer required The ID of the role to retrieve permissions for. Example: 3
     *
     * @response 200 scenario="Permissions retrieved successfully" {
     *   "message": "Permissions retrieved successfully.",
     *   "data": {
     *     "role": "manager",
     *     "permissions": [
     *       "view_users",
     *       "create_users",
     *       "edit_users",
     *       "view_orders",
     *       "create_orders",
     *       "edit_orders",
     *       "view_products",
     *       "create_products",
     *       "edit_products",
     *       "view_vendors",
     *       "manage_returns",
     *       "view_payments"
     *     ]
     *   }
     * }
     *
     * @response 200 scenario="Role with no permissions" {
     *   "message": "Permissions retrieved successfully.",
     *   "data": {
     *     "role": "new_role",
     *     "permissions": []
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Role not found" {
     *   "message": "No query results for model [App\\Models\\Role] 999"
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
     * Assign specific permissions to a role
     *
     * Grant specific permissions to a role, enabling users with that role to perform the associated
     * actions. This endpoint allows fine-grained control over role capabilities by selectively
     * assigning permissions. Permissions are added to existing ones (not replaced), so you can
     * incrementally build up role permissions without losing previously assigned ones.
     *
     * @group Role Permission Management
     * @authenticated
     *
     * @urlParam role integer required The ID of the role to assign permissions to. Example: 3
     *
     * @bodyParam permissions array required List of permission names to assign to the role. All permission names must exist in the system.
     * @bodyParam permissions.* string required The name of each permission to assign. Must be exact permission names as stored in the database. Example: "view_users"
     *
     * @response 200 scenario="Permissions assigned successfully" {
     *   "message": "Permissions assigned successfully.",
     *   "data": {
     *     "role": "manager",
     *     "assigned_permissions": [
     *       "delete_users",
     *       "manage_refunds",
     *       "view_reports"
     *     ],
     *     "total_permissions": 15
     *   }
     * }
     *
     * @response 200 scenario="Permissions already assigned" {
     *   "message": "Permissions assigned successfully.",
     *   "data": {
     *     "role": "manager",
     *     "assigned_permissions": [],
     *     "note": "All specified permissions were already assigned to this role",
     *     "total_permissions": 12
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Role not found" {
     *   "message": "No query results for model [App\\Models\\Role] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The permissions field is required.",
     *     "The permissions must be an array.",
     *     "The permissions.0 is invalid. Permission 'invalid_permission' does not exist.",
     *     "The permissions.1 is invalid. Permission 'another_invalid' does not exist."
     *   ]
     * }
     *
     * @response 422 scenario="Invalid permission names" {
     *   "errors": [
     *     "The following permissions do not exist: invalid_permission, fake_permission"
     *   ]
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
     * Assign all available permissions to a role
     *
     * Grant all permissions in the system to a specific role, effectively creating a "super" role with
     * complete access to all system functionality. Use this endpoint with extreme caution as it provides
     * unrestricted access to all features. This is typically used for super administrator roles or during
     * initial system setup. The operation will assign every permission that exists in the system.
     *
     * **Warning**: This gives complete system access to users with this role. Ensure this is intentional
     * and that the role is assigned only to trusted administrators.
     *
     * @group Role Permission Management
     * @authenticated
     *
     * @urlParam role integer required The ID of the role to assign all permissions to. Example: 1
     *
     * @response 200 scenario="All permissions assigned successfully" {
     *   "message": "All permissions assigned successfully.",
     *   "data": {
     *     "role": "super_admin",
     *     "total_permissions_assigned": 47,
     *     "newly_assigned": 12,
     *     "already_assigned": 35,
     *     "permissions": [
     *       "view_users",
     *       "create_users",
     *       "edit_users",
     *       "delete_users",
     *       "view_orders",
     *       "create_orders",
     *       "edit_orders",
     *       "delete_orders",
     *       "view_products",
     *       "create_products",
     *       "edit_products",
     *       "delete_products",
     *       "view_vendors",
     *       "create_vendors",
     *       "edit_vendors",
     *       "delete_vendors",
     *       "view_payments",
     *       "manage_refunds",
     *       "manage_returns",
     *       "view_roles",
     *       "create_roles",
     *       "edit_roles",
     *       "delete_roles",
     *       "view_permissions",
     *       "create_permissions",
     *       "edit_permissions",
     *       "delete_permissions",
     *       "view_reports",
     *       "export_data",
     *       "import_data",
     *       "system_settings",
     *       "user_management",
     *       "security_settings"
     *     ]
     *   }
     * }
     *
     * @response 200 scenario="Role already has all permissions" {
     *   "message": "All permissions assigned successfully.",
     *   "data": {
     *     "role": "super_admin",
     *     "total_permissions_assigned": 47,
     *     "newly_assigned": 0,
     *     "already_assigned": 47,
     *     "note": "Role already had all available permissions"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Role not found" {
     *   "message": "No query results for model [App\\Models\\Role] 999"
     * }
     *
     * @response 500 scenario="Assignment failed" {
     *   "message": "An error occurred while assigning all permissions to the role."
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
     * Revoke a specific permission from a role
     *
     * Remove a specific permission from a role, preventing users with that role from performing the
     * associated action. This endpoint provides fine-grained control over role capabilities by allowing
     * selective removal of permissions. Use this when you need to restrict access to specific features
     * for users with a particular role while maintaining their other permissions.
     *
     * @group Role Permission Management
     * @authenticated
     *
     * @urlParam role integer required The ID of the role to revoke the permission from. Example: 3
     * @urlParam permission integer required The ID of the permission to revoke from the role. Example: 12
     *
     * @response 200 scenario="Permission revoked successfully" {
     *   "message": "Permission revoked successfully.",
     *   "data": {
     *     "role": "manager",
     *     "revoked_permission": "delete_users",
     *     "remaining_permissions_count": 11,
     *     "remaining_permissions": [
     *       "view_users",
     *       "create_users",
     *       "edit_users",
     *       "view_orders",
     *       "create_orders",
     *       "edit_orders",
     *       "view_products",
     *       "create_products",
     *       "edit_products",
     *       "view_vendors",
     *       "manage_returns"
     *     ]
     *   }
     * }
     *
     * @response 200 scenario="Permission was not assigned" {
     *   "message": "Permission revoked successfully.",
     *   "data": {
     *     "role": "manager",
     *     "permission": "super_admin_only",
     *     "note": "Permission was not assigned to this role",
     *     "remaining_permissions_count": 12
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Role not found" {
     *   "message": "No query results for model [App\\Models\\Role] 999"
     * }
     *
     * @response 404 scenario="Permission not found" {
     *   "message": "No query results for model [App\\Models\\Permission] 999"
     * }
     *
     * @response 422 scenario="Cannot revoke core permission" {
     *   "message": "Cannot revoke core system permissions that are required for role functionality."
     * }
     *
     * @response 500 scenario="Revocation failed" {
     *   "message": "An error occurred while revoking the permission from the role."
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

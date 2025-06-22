<?php

namespace App\Http\Controllers\V1\Admin;

use App\Requests\V1\StoreRoleRequest;
use App\Requests\V1\UpdateRoleRequest;
use App\Services\V1\Auth\Role;
use App\Models\Role as DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;

class RoleController extends Controller
{
    use ApiResponses;

    private $role;

    public function __construct(Role $role)
    {
        $this->role = $role;
    }

    /**
     * Retrieve all user roles
     *
     * Get a complete list of all user roles in the system. Roles define user access levels and permissions
     * within the application. This endpoint is essential for user management, access control configuration,
     * and understanding the system's permission structure. Only administrators with proper permissions
     * can view roles to maintain system security.
     *
     * @group Role Management
     * @authenticated
     *
     * @response 200 scenario="Roles retrieved successfully" {
     *   "message": "Roles retrieved successfully.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "super_admin",
     *       "created_at": "2024-12-01T08:00:00.000000Z",
     *       "updated_at": "2024-12-01T08:00:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "admin",
     *       "created_at": "2024-12-01T08:05:00.000000Z",
     *       "updated_at": "2024-12-01T08:05:00.000000Z"
     *     },
     *     {
     *       "id": 3,
     *       "name": "manager",
     *       "created_at": "2024-12-01T08:10:00.000000Z",
     *       "updated_at": "2024-12-01T08:10:00.000000Z"
     *     },
     *     {
     *       "id": 4,
     *       "name": "customer_service",
     *       "created_at": "2024-12-01T08:15:00.000000Z",
     *       "updated_at": "2024-12-01T08:15:00.000000Z"
     *     },
     *     {
     *       "id": 5,
     *       "name": "vendor",
     *       "created_at": "2024-12-01T08:20:00.000000Z",
     *       "updated_at": "2024-12-01T08:20:00.000000Z"
     *     },
     *     {
     *       "id": 6,
     *       "name": "user",
     *       "created_at": "2024-12-01T08:25:00.000000Z",
     *       "updated_at": "2024-12-01T08:25:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 200 scenario="No roles configured" {
     *   "message": "Roles retrieved successfully.",
     *   "data": []
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function index(Request $request)
    {
        try {
            return $this->role->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new user role
     *
     * Add a new role to the system for user access control. Roles define what permissions users have
     * and what actions they can perform within the application. Role names should follow a consistent
     * naming convention (lowercase, underscore-separated) and must be unique. Once created, roles can
     * be assigned to users and configured with specific permissions.
     *
     * @group Role Management
     * @authenticated
     *
     * @bodyParam name string required The name of the role. Should follow naming convention (lowercase, underscore-separated). Will be converted to lowercase automatically. Example: customer_service
     *
     * @response 200 scenario="Role created successfully" {
     *   "message": "Role created successfully.",
     *   "data": {
     *     "id": 7,
     *     "name": "customer_service",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The name has already been taken.",
     *     "The name may not be greater than 255 characters."
     *   ]
     * }
     *
     * @response 422 scenario="Duplicate role name" {
     *   "errors": [
     *     "The name has already been taken."
     *   ]
     * }
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            return $this->role->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing role
     *
     * Modify an existing role name in the system. Exercise caution when updating roles as this may
     * affect user access throughout the application. Ensure that any name changes are reflected in
     * user assignments and access control logic. Role names are automatically converted to lowercase
     * and must remain unique across all roles.
     *
     * @group Role Management
     * @authenticated
     *
     * @urlParam role integer required The ID of the role to update. Example: 7
     *
     * @bodyParam name string required The updated name of the role. Should follow naming convention (lowercase, underscore-separated). Will be converted to lowercase automatically. Example: senior_customer_service
     *
     * @response 200 scenario="Role updated successfully" {
     *   "message": "Role updated successfully.",
     *   "data": {
     *     "id": 7,
     *     "name": "senior_customer_service",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T15:15:00.000000Z"
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
     *     "The name field is required.",
     *     "The name has already been taken.",
     *     "The name may not be greater than 255 characters."
     *   ]
     * }
     *
     * @response 422 scenario="Name already exists" {
     *   "errors": [
     *     "The name has already been taken."
     *   ]
     * }
     */
    public function update(UpdateRoleRequest $request, DB $role)
    {
        try {
            return $this->role->update($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a role
     *
     * Permanently remove a role from the system. This action is irreversible and will affect all users
     * currently assigned to this role. Before deletion, ensure that users with this role are reassigned
     * to appropriate alternative roles to maintain proper access control. Exercise extreme caution when
     * deleting roles as this may impact user access throughout the application.
     *
     * **Warning**: Deleting a role that is currently assigned to users may leave those users without
     * proper access permissions, potentially locking them out of system features.
     *
     * @group Role Management
     * @authenticated
     *
     * @urlParam role integer required The ID of the role to delete. Example: 7
     *
     * @response 200 scenario="Role deleted successfully" {
     *   "message": "Role deleted successfully."
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
     * @response 409 scenario="Role in use by users" {
     *   "message": "Cannot delete role that is currently assigned to users."
     * }
     *
     * @response 422 scenario="Core role protection" {
     *   "message": "Cannot delete core system roles required for application functionality."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *   "message": "An error occurred while deleting the role."
     * }
     */
    public function destroy(Request $request, DB $role)
    {
        try {
            return $this->role->delete($request, $role);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

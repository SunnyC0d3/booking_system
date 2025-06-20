<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\Permission as DB;
use App\Requests\V1\StorePermissionRequest;
use App\Requests\V1\UpdatePermissionRequest;
use App\Services\V1\Auth\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use \Exception;

class PermissionController extends Controller
{
    use ApiResponses;

    private $permission;

    public function __construct(Permission $permission)
    {
        $this->permission = $permission;
    }

    /**
     * Retrieve all permissions
     *
     * Get a complete list of all permissions available in the system. Permissions define what
     * actions users can perform and are assigned to roles for access control. This endpoint is
     * essential for role management, security auditing, and understanding system capabilities.
     * Only super admins can manage permissions to maintain system security.
     *
     * @group Permission Management
     * @authenticated
     *
     * @response 200 scenario="Permissions retrieved successfully" {
     *   "message": "Permissions retrieved successfully.",
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "view_users",
     *       "created_at": "2024-12-01T08:00:00.000000Z",
     *       "updated_at": "2024-12-01T08:00:00.000000Z"
     *     },
     *     {
     *       "id": 2,
     *       "name": "create_users",
     *       "created_at": "2024-12-01T08:00:00.000000Z",
     *       "updated_at": "2024-12-01T08:00:00.000000Z"
     *     },
     *     {
     *       "id": 3,
     *       "name": "edit_users",
     *       "created_at": "2024-12-01T08:00:00.000000Z",
     *       "updated_at": "2024-12-01T08:00:00.000000Z"
     *     },
     *     {
     *       "id": 4,
     *       "name": "delete_users",
     *       "created_at": "2024-12-01T08:00:00.000000Z",
     *       "updated_at": "2024-12-01T08:00:00.000000Z"
     *     },
     *     {
     *       "id": 5,
     *       "name": "view_orders",
     *       "created_at": "2024-12-01T08:05:00.000000Z",
     *       "updated_at": "2024-12-01T08:05:00.000000Z"
     *     },
     *     {
     *       "id": 6,
     *       "name": "create_orders",
     *       "created_at": "2024-12-01T08:05:00.000000Z",
     *       "updated_at": "2024-12-01T08:05:00.000000Z"
     *     },
     *     {
     *       "id": 7,
     *       "name": "edit_orders",
     *       "created_at": "2024-12-01T08:05:00.000000Z",
     *       "updated_at": "2024-12-01T08:05:00.000000Z"
     *     },
     *     {
     *       "id": 8,
     *       "name": "delete_orders",
     *       "created_at": "2024-12-01T08:05:00.000000Z",
     *       "updated_at": "2024-12-01T08:05:00.000000Z"
     *     },
     *     {
     *       "id": 9,
     *       "name": "manage_refunds",
     *       "created_at": "2024-12-01T08:10:00.000000Z",
     *       "updated_at": "2024-12-01T08:10:00.000000Z"
     *     },
     *     {
     *       "id": 10,
     *       "name": "manage_returns",
     *       "created_at": "2024-12-01T08:10:00.000000Z",
     *       "updated_at": "2024-12-01T08:10:00.000000Z"
     *     },
     *     {
     *       "id": 11,
     *       "name": "view_products",
     *       "created_at": "2024-12-01T08:15:00.000000Z",
     *       "updated_at": "2024-12-01T08:15:00.000000Z"
     *     },
     *     {
     *       "id": 12,
     *       "name": "create_products",
     *       "created_at": "2024-12-01T08:15:00.000000Z",
     *       "updated_at": "2024-12-01T08:15:00.000000Z"
     *     }
     *   ]
     * }
     *
     * @response 200 scenario="No permissions configured" {
     *   "message": "Permissions retrieved successfully.",
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
            return $this->permission->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new permission
     *
     * Add a new permission to the system. Permissions should follow a consistent naming convention
     * (e.g., action_resource format like "view_users", "create_orders"). Once created, permissions
     * can be assigned to roles to control user access. Permission names are automatically converted
     * to lowercase and must be unique across the system.
     *
     * @group Permission Management
     * @authenticated
     *
     * @bodyParam name string required The name of the permission. Should follow naming convention (action_resource). Will be converted to lowercase. Example: "create-vendor"
     *
     * @response 200 scenario="Permission created successfully" {
     *   "message": "Permission created successfully.",
     *   "data": {
     *     "id": 13,
     *     "name": "create-vendor",
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
     * @response 422 scenario="Duplicate permission name" {
     *   "errors": [
     *     "The name has already been taken."
     *   ]
     * }
     */
    public function store(StorePermissionRequest $request)
    {
        try {
            return $this->permission->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing permission
     *
     * Modify an existing permission name. Exercise caution when updating permissions as this
     * may affect role-based access controls throughout the system. Ensure that any name changes
     * are reflected in role assignments and access control logic. Permission names are automatically
     * converted to lowercase and must remain unique.
     *
     * @group Permission Management
     * @authenticated
     *
     * @urlParam permission integer required The ID of the permission to update. Example: 13
     *
     * @bodyParam name string required The updated name of the permission. Should follow naming convention (action_resource). Will be converted to lowercase. Example: "create-vendor"
     *
     * @response 200 scenario="Permission updated successfully" {
     *   "message": "Permission updated successfully.",
     *   "data": {
     *     "id": 13,
     *     "name": "create-vendor",
     *     "created_at": "2025-01-16T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T15:15:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Permission not found" {
     *   "message": "No query results for model [App\\Models\\Permission] 999"
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
    public function update(UpdatePermissionRequest $request, DB $permission)
    {
        try {
            return $this->permission->update($request, $permission);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Delete a permission
     *
     * Permanently remove a permission from the system. This action is irreversible and will
     * automatically remove the permission from all roles that currently have it assigned.
     * Exercise extreme caution when deleting permissions as this may break access control
     * throughout the application and potentially lock users out of system features.
     *
     * **Warning**: Deleting core system permissions may cause application functionality to
     * become inaccessible to all users, including administrators.
     *
     * @group Permission Management
     * @authenticated
     *
     * @urlParam permission integer required The ID of the permission to delete. Example: 13
     *
     * @response 200 scenario="Permission deleted successfully" {
     *   "message": "Permission deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Permission not found" {
     *   "message": "No query results for model [App\\Models\\Permission] 999"
     * }
     *
     * @response 409 scenario="Permission in use" {
     *   "message": "Cannot delete permission that is currently assigned to roles."
     * }
     *
     * @response 422 scenario="Core permission protection" {
     *   "message": "Cannot delete core system permissions required for application functionality."
     * }
     *
     * @response 500 scenario="Deletion failed" {
     *   "message": "An error occurred while deleting the permission."
     * }
     */
    public function destroy(Request $request, DB $permission)
    {
        try {
            return $this->permission->delete($request, $permission);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

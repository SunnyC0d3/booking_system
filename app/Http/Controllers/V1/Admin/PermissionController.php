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
     * Retrieve all permissions.
     *
     * @group Permissions
     * @authenticated
     *
     * @response 200 {
     *   "message": "Permissions retrieved successfully.",
     *   "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Create a new permission.
     *
     * @group Permissions
     * @authenticated
     *
     * @bodyParam name string required The name of the permission. Example: "create-vendor"
     *
     * @response 200 {
     *   "message": "Permission created successfully.",
     *   "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Update a permission.
     *
     * @group Permissions
     * @authenticated
     *
     * @bodyParam name string required The updated name of the permission. Example: "create-vendor"
     *
     * @response 200 {
     *   "message": "Permission updated successfully.",
     *   "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Delete a permission.
     *
     * @group Permissions
     * @authenticated
     *
     * @response 200 {
     *   "message": "Permission deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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

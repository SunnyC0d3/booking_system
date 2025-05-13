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
     * Retrieve all roles.
     *
     * @group Roles
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @response 200 {
     *   "message": "Roles retrieved successfully.",
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
            return $this->role->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new role.
     *
     * @group Roles
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @bodyParam name string required The name of the role. Example: "admin"
     *
     * @response 200 {
     *   "message": "Role created successfully.",
     *   "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Update a role.
     *
     * @group Roles
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @bodyParam name string required The updated name of the role. Example: "admin"
     *
     * @response 200 {
     *   "message": "Role updated successfully.",
     *   "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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
     * Delete a role.
     *
     * @group Roles
     * @authenticated
     *
     * @header X-Hmac HMAC signature of the request payload done via sha256.
     * @header X-Timestamp Timestamp + Request body.
     *
     * @response 200 {
     *   "message": "Role deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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

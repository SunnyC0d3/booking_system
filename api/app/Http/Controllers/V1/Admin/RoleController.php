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

    public function index(Request $request)
    {
        try {
            return $this->role->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreRoleRequest $request)
    {
        try {
            return $this->role->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateRoleRequest $request, DB $role)
    {
        try {
            return $this->role->update($request, $role);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(Request $request, DB $role)
    {
        try {
            return $this->role->delete($request, $role);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

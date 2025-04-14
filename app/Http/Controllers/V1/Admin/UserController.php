<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User as UserDB;
use App\Services\V1\Users\User;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreUserRequest;
use App\Requests\V1\UpdateUserRequest;
use App\Requests\V1\FilterUserRequest;
use Illuminate\Http\Request;
use App\Filters\V1\UserFilter;
use \Exception;

class UserController extends Controller
{
    use ApiResponses;

    private $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Retrieve a paginated list of users.
     *
     * @group Users
     * @authenticated
     *
     * @response 200 {
     *     "message": "Users retrieved successfully.",
     *     "data": []
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function index(FilterUserRequest $request, UserFilter $filter)
    {
        $request->validated($request->only([
            'filter' => [
                'name',
                'email',
                'role',
                'created_at',
                'updated_at',
                'search',
                'include'
            ],
            'page',
            'per_page',
            'sort',
        ]));

        try {
            return $this->user->all($request, $filter);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new user.
     *
     * @group Users
     * @authenticated
     *
     * @response 201 {
     *     "message": "Users created successfully!",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function store(StoreUserRequest $request)
    {
        $request->validated($request->only([
            'name',
            'email',
            'password',
            'role_id',
            'address.address_line1',
            'address.city',
            'address.country',
            'address.postal_code',
            'address.address_line2',
            'address.state',
        ]));

        try {
            return $this->user->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific user.
     *
     * @group Users
     * @authenticated
     *
     * @response 200 {
     *     "message": "Users details retrieved.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function show(Request $request, UserDB $user)
    {
        try {
            return $this->user->find($request, $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing user.
     *
     * @group Users
     * @authenticated
     *
     * @response 200 {
     *     "message": "Users updated successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function update(UpdateUserRequest $request, UserDB $user)
    {
        $request->validated($request->only([
            'name',
            'email',
            'password',
            'role_id',
            'address.address_line1',
            'address.city',
            'address.country',
            'address.postal_code',
            'address.address_line2',
            'address.state',
        ]));

        try {
            return $this->user->update($request, $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Permanently delete a user.
     *
     * @group Users
     * @authenticated
     *
     * @response 200 {
     *     "message": "Users deleted successfully."
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
     * }
     */
    public function destroy(Request $request, UserDB $user)
    {
        try {
            return $this->user->delete($request, $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

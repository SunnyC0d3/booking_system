<?php

namespace App\Services\V1\Users;

use App\Models\User as DB;
use App\Resources\V1\UserResource;
use Illuminate\Http\Request;
use App\Traits\V1\ApiResponses;
use App\Filters\V1\UserFilter;
use Illuminate\Support\Facades\Hash;

class User
{
    use ApiResponses;

    public function __construct()
    {
    }

    public function all(Request $request, UserFilter $filter)
    {
        $user = $request->user();

        if ($user->hasPermission('view_users')) {
            $request->validated();

            $query = DB::with(['userAddress', 'role', 'vendors'])->filter($filter);
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage);

            return UserResource::collection($users)->additional([
                'message' => 'Users retrieved successfully.',
                'status' => 200
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $_user)
    {
        $user = $request->user();

        if ($user->hasPermission('view_users')) {
            $_user->load(['role', 'vendors', 'userAddress']);
            return $this->ok('User details retrieved.', new UserResource($_user));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_users')) {
            $data = $request->validated();

            $_user = DB::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'password' => Hash::make($data['password']),
            ]);

            $_user->userAddress()->create($data['address']);

            return $this->ok('User created successfully!', new UserResource($_user));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $_user)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_users')) {
            $data = $request->validated();

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $_user->update($data);

            if (isset($data['address'])) {
                $_user->userAddress()->updateOrCreate([], $data['address']);
            }

            return $this->ok('User updated successfully.', new UserResource($_user));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $_user)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_users')) {
            $_user->userAddress()->delete();
            $_user->delete();
            return $this->ok('User deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

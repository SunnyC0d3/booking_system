<?php

namespace App\Services\V1\Users;

use App\Models\User as UserDB;
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
            $query = UserDB::filter($filter);
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage)->appends($request->query());

            return $this->ok('Users retrieved successfully.', $users);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(UserDB $user)
    {
        if ($user->hasPermission('view_users')) {
            $user->load(['role', 'vendors', 'userAddress']);
            return $this->ok('Users details retrieved.', $user);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_users')) {
            $data = $request->validated();

            $user = UserDB::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'password' => Hash::make($data['password']),
            ]);

            $user = $user->userAddress()->create($data['address']);

            return $this->ok('Users created successfully!', $user);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('edit_users')) {
            $data = $request->validated();

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if (isset($data['address'])) {
                $user->userAddress()->updateOrCreate([], $data['address']);
            }

            return $this->ok('Users updated successfully.', $user->fresh()->load(['role', 'userAddress']));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(UserDB $user)
    {
        if ($user->hasPermission('delete_users')) {
            $user->delete();
            return $this->ok('Users deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

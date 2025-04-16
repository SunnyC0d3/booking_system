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

            $query = UserDB::filter($filter);
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage)->appends($request->query());

            return $this->ok('Users retrieved successfully.', $users);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, UserDB $_user)
    {
        $user = $request->user();

        if ($user->hasPermission('view_users')) {
            $_user->load(['role', 'vendors', 'userAddress']);
            return $this->ok('Users details retrieved.', $_user);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_users')) {
            $data = $request->validated();

            $_user = UserDB::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'password' => Hash::make($data['password']),
            ]);

            $_user->userAddress()->create($data['address']);

            return $this->ok('Users created successfully!', $_user->load(['role', 'userAddress']));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, UserDB $_user)
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

            return $this->ok('Users updated successfully.', $_user->load(['role', 'userAddress']));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, UserDB $_user)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_users')) {
            $_user->userAddress()->delete();
            $_user->delete();
            return $this->ok('Users deleted successfully.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }
}

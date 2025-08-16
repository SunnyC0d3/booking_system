<?php

namespace App\Services\V1\Users;

use App\Models\Role;
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

        if ($user->hasPermission('view_all_users')) {
            $request->validated();

            $query = DB::with(['userAddress', 'role'])->filter($filter);
            $perPage = $request->input('per_page', 15);
            $users = $query->paginate($perPage);

            return UserResource::collection($users)->additional([
                'message' => 'Users retrieved successfully.',
                'status' => 200
            ]);
        }

        if ($user->hasPermission('view_own_profile')) {
            $userData = DB::with(['userAddress', 'role'])
                ->where('id', $user->id)
                ->paginate(1);

            return UserResource::collection($userData)->additional([
                'message' => 'Your profile retrieved successfully.',
                'status' => 200
            ]);
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function find(Request $request, DB $_user)
    {
        $user = $request->user();

        if ($user->hasPermission('view_all_users')) {
            $_user->load(['role', 'userAddress']);
            return $this->ok('User details retrieved.', new UserResource($_user));
        }

        if ($user->hasPermission('view_own_profile')) {
            if ($_user->id !== $user->id) {
                return $this->error('You can only view your own profile.', 403);
            }

            $_user->load(['role', 'userAddress']);
            return $this->ok('Your profile retrieved.', new UserResource($_user));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        if ($user->hasPermission('create_all_users')) {
            $data = $request->validated();

            $_user = DB::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'password' => Hash::make($data['password']),
            ]);

            if (isset($data['address'])) {
                $_user->userAddress()->create($data['address']);
            }

            return $this->ok('User created successfully!', new UserResource($_user));
        }

        if ($user->hasPermission('create_user_account')) {
            $data = $request->validated();

            $defaultUserRoleId = Role::where('name', 'User')->first()->id;

            if (isset($data['role_id']) && $data['role_id'] != $defaultUserRoleId) {
                return $this->error('You cannot assign roles during registration.', 403);
            }

            $_user = DB::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $defaultUserRoleId,
                'password' => Hash::make($data['password']),
            ]);

            if (isset($data['address'])) {
                $_user->userAddress()->create($data['address']);
            }

            return $this->ok('Account created successfully!', new UserResource($_user));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function update(Request $request, DB $_user)
    {
        $user = $request->user();
        $data = $request->validated();

        if ($user->hasPermission('edit_all_users')) {
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $_user->update($data);

            if (isset($data['address'])) {
                $_user->userAddress()->updateOrCreate([], $data['address']);
            }

            return $this->ok('User updated successfully.', new UserResource($_user));
        }

        if ($user->hasPermission('edit_own_profile')) {
            if ($_user->id !== $user->id) {
                return $this->error('You can only edit your own profile.', 403);
            }

            if (isset($data['role_id']) && $data['role_id'] != $_user->role_id) {
                return $this->error('You cannot change your own role.', 403);
            }

            unset($data['role_id'], $data['email_verified_at']);

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $_user->update($data);

            if (isset($data['address'])) {
                $_user->userAddress()->updateOrCreate([], $data['address']);
            }

            return $this->ok('Your profile updated successfully.', new UserResource($_user));
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function delete(Request $request, DB $_user)
    {
        $user = $request->user();

        if ($user->hasPermission('delete_all_users')) {
            if ($_user->hasRole('Super Admin')) {
                return $this->error('Super Admin accounts cannot be deleted.', 403);
            }

            if ($_user->id === $user->id) {
                return $this->error('You cannot delete your own account.', 403);
            }

            $_user->userAddress()->delete();
            $_user->delete();
            return $this->ok('User deleted successfully.');
        }

        if ($user->hasPermission('delete_own_account')) {
            if ($_user->id !== $user->id) {
                return $this->error('You can only delete your own account.', 403);
            }

            $_user->userAddress()->delete();
            $_user->delete();
            return $this->ok('Your account has been deleted.');
        }

        return $this->error('You do not have the required permissions.', 403);
    }

    public function restore(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('restore_users')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $_user = DB::withTrashed()->findOrFail($id);

        if (!$_user->trashed()) {
            return $this->error('User is not deleted.', 400);
        }

        $_user->restore();
        $_user->load(['role', 'userAddress']);

        return $this->ok('User restored successfully.', new UserResource($_user));
    }

    public function forceDelete(Request $request, int $id)
    {
        $user = $request->user();

        if (!$user->hasPermission('force_delete_users')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $_user = DB::withTrashed()->findOrFail($id);

        if (!$_user->trashed()) {
            return $this->error('User must be soft deleted before force deleting.', 400);
        }

        if ($_user->hasRole('Super Admin')) {
            return $this->error('Super Admin accounts cannot be permanently deleted.', 403);
        }

        $_user->forceDelete();

        return $this->ok('User permanently deleted.');
    }
}

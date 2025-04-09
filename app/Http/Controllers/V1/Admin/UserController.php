<?php

namespace App\Http\Controllers\V1\Admin;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Requests\V1\StoreUserRequest;
use App\Requests\V1\UpdateUserRequest;
use Illuminate\Support\Facades\Hash;
use \Exception;

class UserController extends Controller
{
    use ApiResponses;

    public function index()
    {
        try {
            $users = User::with(['role', 'vendors', 'userAddress'])->latest()->paginate(15);
            return $this->ok('Users retrieved successfully.', $users);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated($request->only([
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
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'role_id' => $data['role_id'],
                'password' => Hash::make($data['password']),
            ]);

            $user = $user->userAddress()->create($data['address']);

            return $this->ok('User created successfully!', $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function show(User $user)
    {
        try {
            $user->load(['role', 'vendors', 'userAddress']);
            return $this->ok('User details retrieved.', $user);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated($request->only([
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
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            if (isset($data['address'])) {
                $user->userAddress()->updateOrCreate([], $data['address']);
            }

            return $this->ok('User updated successfully.', $user->fresh()->load(['role', 'userAddress']));
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            $user->delete();
            return $this->ok('User deleted successfully.');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

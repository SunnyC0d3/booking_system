<?php

namespace App\Policies\V1;

use App\Models\User;
use App\Permissions\V1\Abilities;
use App\Traits\V1\TokenHelper;

class UserPolicy
{
    use TokenHelper;

    private function getUserToken(User $user, string $token)
    {
        return $user->tokens->where('token', $this->getTokenFromString($token))->first();
    }

    public function create(User $user, string $token)
    {
        $authenticatedToken = $this->getUserToken($user, $token);

        if (!$authenticatedToken) {
            return false;
        }

        if ($user->role === 'user') {
            return $authenticatedToken->can(Abilities::CreateUser);
        }

        if ($user->role === 'admin') {
            return $authenticatedToken->can(Abilities::CreateAdmin);
        }

        if ($user->role === 'client') {
            return $authenticatedToken->can(Abilities::CreateClient);
        }

        return false;
    }

    public function update(User $user, string $token)
    {
        $authenticatedToken = $this->getUserToken($user, $token);

        if (!$authenticatedToken) {
            return false;
        }

        if ($user->role === 'user') {
            return $authenticatedToken->can(Abilities::UpdateUser);
        }

        if ($user->role === 'admin') {
            return $authenticatedToken->can(Abilities::UpdateAdmin);
        }

        if ($user->role === 'client') {
            return $authenticatedToken->can(Abilities::UpdateClient);
        }

        return false;
    }

    public function delete(User $user, string $token)
    {
        $authenticatedToken = $this->getUserToken($user, $token);

        if (!$authenticatedToken) {
            return false;
        }

        if ($user->role === 'user') {
            return $authenticatedToken->can(Abilities::DeleteUser);
        }

        if ($user->role === 'admin') {
            return $authenticatedToken->can(Abilities::DeleteAdmin);
        }

        if ($user->role === 'client') {
            return $authenticatedToken->can(Abilities::DeleteClient);
        }

        return false;
    }

    public function replace(User $user, string $token)
    {
        $authenticatedToken = $this->getUserToken($user, $token);

        if (!$authenticatedToken) {
            return false;
        }

        if ($user->role === 'user') {
            return $authenticatedToken->can(Abilities::ReplaceUser);
        }

        if ($user->role === 'admin') {
            return $authenticatedToken->can(Abilities::ReplaceAdmin);
        }

        if ($user->role === 'client') {
            return $authenticatedToken->can(Abilities::ReplaceClient);
        }

        return false;
    }

    public function only(User $user, string $token)
    {
        $authenticatedToken = $this->getUserToken($user, $token);

        if (!$authenticatedToken) {
            return false;
        }

        if ($user->role === 'user') {
            return $authenticatedToken->can(Abilities::OnlyUser);
        }

        if ($user->role === 'admin') {
            return $authenticatedToken->can(Abilities::OnlyAdmin);
        }

        if ($user->role === 'client') {
            return $authenticatedToken->can(Abilities::OnlyClient);
        }

        return false;
    }
}

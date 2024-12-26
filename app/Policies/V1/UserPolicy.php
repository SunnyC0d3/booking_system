<?php

namespace App\Policies\V1;

use App\Models\User;
use App\Permissions\V1\Abilities;

class UserPolicy
{
    public function can(User $user, $scope)
    {
        if (Abilities::getAbilities($user)) {
            foreach (Abilities::getAbilities($user) as $ability) {
                if ($scope === $ability && $user->tokenCan($ability)) {
                    return true;
                }
            }
        }

        return false;
    }
}

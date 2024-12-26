<?php

namespace App\Policies\V1;

use App\Models\User;
use App\Permissions\V1\Abilities;

class UserPolicy
{
    public function can(User $user)
    {
        return $user->tokenCan(Abilities::getAbilities($user)) ? true : false;
    }
}

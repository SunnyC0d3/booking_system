<?php

namespace App\Permissions\V1;

use App\Models\User;

final class Abilities
{
    public const Scopes = [
        'register'      => 'Ability to register',
        'logout'        => 'Ability to logout',
        'read-products'     => 'Ability to read products',
        'write-products'    => 'Ability to create, update or delete products'
    ];

    public static function getAbilities(User $user): array | null
    {
        $keys = array_keys(self::Scopes);

        if ($user->role === 'admin') {
            return $keys;
        }

        if ($user->role === 'user') {
            return [
                $keys[1],
                $keys[2],
            ];
        }

        return null;
    }
}

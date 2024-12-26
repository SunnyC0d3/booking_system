<?php

namespace App\Permissions\V1;

use App\Models\User;

final class Abilities
{
    public const ReadProducts  = 'read-products';
    public const WriteProducts = 'write-products';

    public static function getAbilities(User $user): string | null
    {
        if ($user->role === 'admin') {
            return implode(' ', [
                self::ReadProducts,
                self::WriteProducts
            ]);
        }

        if ($user->role === 'user') {
            return implode(' ', [
                self::ReadProducts
            ]);
        }

        return null;
    }
}

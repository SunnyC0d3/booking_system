<?php

namespace App\Permissions;

use App\Models\User;

final class Abilities
{
    public const CreateUser    = 'user:create';
    public const ReplaceUser   = 'user:replace';
    public const UpdateUser    = 'user:update';
    public const DeleteUser    = 'user:delete';
    public const OnlyUser      = 'user:only';

    public const CreateAdmin   = 'admin:create';
    public const ReplaceAdmin  = 'admin:replace';
    public const UpdateAdmin   = 'admin:update';
    public const DeleteAdmin   = 'admin:delete';
    public const OnlyAdmin     = 'admin:only';

    public const CreateClient  = 'client:create';
    public const ReplaceClient = 'client:replace';
    public const UpdateClient  = 'client:update';
    public const DeleteClient  = 'client:delete';
    public const OnlyClient    = 'client:only';

    public static function getAbilities(User $user): array
    {
        if ($user->role === 'client') {
            return [
                self::CreateClient,
                self::ReplaceClient,
                self::UpdateClient,
                self::DeleteClient,
                self::OnlyClient
            ];
        }

        if ($user->role === 'admin') {
            return [
                self::CreateAdmin,
                self::ReplaceAdmin,
                self::UpdateAdmin,
                self::DeleteAdmin,
                self::OnlyAdmin
            ];
        }

        if ($user->role === 'user') {
            return [
                self::CreateUser,
                self::ReplaceUser,
                self::UpdateUser,
                self::DeleteUser,
                self::OnlyUser
            ];
        }
    }
}

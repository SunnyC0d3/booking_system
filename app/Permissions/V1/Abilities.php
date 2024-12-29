<?php

namespace App\Permissions\V1;

final class Abilities
{
    public const Scopes = [
        'register'              => 'Ability to register',
        'login'                 => 'Ability to login',
        'logout'                => 'Ability to logout',
        'read-products'         => 'Ability to read products',
        'write-products'        => 'Ability to create, update or delete products'
    ];
}

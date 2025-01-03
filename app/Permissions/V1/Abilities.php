<?php

namespace App\Permissions\V1;

final class Abilities
{
    public const Scopes = [
        'read-products'         => 'Ability to read products',
        'write-products'        => 'Ability to create, update or delete products'
    ];
}

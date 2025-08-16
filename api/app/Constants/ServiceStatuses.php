<?php

namespace App\Constants;

class ServiceStatuses
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const DRAFT = 'draft';

    public const ALL = [
        self::ACTIVE,
        self::INACTIVE,
        self::DRAFT,
    ];
}

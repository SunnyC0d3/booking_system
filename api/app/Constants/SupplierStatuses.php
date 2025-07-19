<?php

namespace App\Constants;

class SupplierStatuses
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const PENDING_APPROVAL = 'pending_approval';
    public const SUSPENDED = 'suspended';
    public const TERMINATED = 'terminated';

    public static function all(): array
    {
        return [
            self::ACTIVE,
            self::INACTIVE,
            self::PENDING_APPROVAL,
            self::SUSPENDED,
            self::TERMINATED,
        ];
    }

    public static function labels(): array
    {
        return [
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::SUSPENDED => 'Suspended',
            self::TERMINATED => 'Terminated',
        ];
    }

    public static function getActiveStatuses(): array
    {
        return [
            self::ACTIVE,
        ];
    }
}

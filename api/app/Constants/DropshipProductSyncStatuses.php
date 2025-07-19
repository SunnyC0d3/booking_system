<?php

namespace App\Constants;

class DropshipProductSyncStatuses
{
    public const SYNCED = 'synced';
    public const PENDING_SYNC = 'pending_sync';
    public const SYNC_FAILED = 'sync_failed';
    public const OUT_OF_SYNC = 'out_of_sync';
    public const SUPPLIER_DISCONTINUED = 'supplier_discontinued';

    public static function all(): array
    {
        return [
            self::SYNCED,
            self::PENDING_SYNC,
            self::SYNC_FAILED,
            self::OUT_OF_SYNC,
            self::SUPPLIER_DISCONTINUED,
        ];
    }

    public static function labels(): array
    {
        return [
            self::SYNCED => 'Synced',
            self::PENDING_SYNC => 'Pending Sync',
            self::SYNC_FAILED => 'Sync Failed',
            self::OUT_OF_SYNC => 'Out of Sync',
            self::SUPPLIER_DISCONTINUED => 'Discontinued by Supplier',
        ];
    }

    public static function getHealthyStatuses(): array
    {
        return [
            self::SYNCED,
        ];
    }

    public static function getUnhealthyStatuses(): array
    {
        return [
            self::SYNC_FAILED,
            self::OUT_OF_SYNC,
            self::SUPPLIER_DISCONTINUED,
        ];
    }
}

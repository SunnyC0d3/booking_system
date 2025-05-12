<?php

namespace App\Constants;

class ReturnStatuses
{
    public const REQUESTED = 'Requested';
    public const UNDER_REVIEW = 'Under Review';
    public const APPROVED = 'Approved';
    public const REJECTED = 'Rejected';
    public const RETURN_SHIPPED = 'Return Shipped';
    public const RETURN_RECIEVED = 'Return Recieved';
    public const COMPLETED = 'Completed';
    public const CANCELLED = 'Cancelled';

    public static function all(): array
    {
        return [
            self::REQUESTED,
            self::UNDER_REVIEW,
            self::APPROVED,
            self::REJECTED,
            self::RETURN_SHIPPED,
            self::RETURN_RECIEVED,
            self::COMPLETED,
            self::CANCELLED,
        ];
    }
}

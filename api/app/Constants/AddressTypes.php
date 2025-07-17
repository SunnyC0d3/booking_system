<?php

namespace App\Constants;

class AddressTypes
{
    public const SHIPPING = 'shipping';
    public const BILLING = 'billing';
    public const BOTH = 'both';

    public static function all(): array
    {
        return [
            self::SHIPPING,
            self::BILLING,
            self::BOTH,
        ];
    }

    public static function getTypeLabel(string $type): string
    {
        return match($type) {
            self::SHIPPING => 'Shipping Address',
            self::BILLING => 'Billing Address',
            self::BOTH => 'Shipping & Billing Address',
            default => ucfirst($type),
        };
    }
}

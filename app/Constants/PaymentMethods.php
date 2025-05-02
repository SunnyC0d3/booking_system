<?php

namespace App\Constants;

class PaymentMethods
{
    public const CARD = 'card';
    public const PAYPAL = 'paypal';
    public const BANK_TRANSFER = 'bank transfer';
    public const APPLE_PAY = 'apple pay';
    public const GOOGLE_PAY = 'google pay';

    public const STRIPE = 'stripe';

    public static function all(): array
    {
        return [
            self::CARD,
            self::PAYPAL,
            self::BANK_TRANSFER,
            self::APPLE_PAY,
            self::GOOGLE_PAY,
            self::STRIPE,
        ];
    }
}

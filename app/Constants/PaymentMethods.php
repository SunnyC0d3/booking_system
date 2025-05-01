<?php

namespace App\Constants;

class PaymentMethods
{
    public const CREDIT_CARD = 'Credit Card';
    public const PAYPAL = 'PayPal';
    public const BANK_TRANSFER = 'Bank Transfer';
    public const APPLEPAY = 'Apple Pay';
    public const GOOGLEPAY = 'Google Pay';

    public static function all(): array
    {
        return [
            self::CREDIT_CARD,
            self::PAYPAL,
            self::BANK_TRANSFER,
            self::APPLEPAY,
            self::GOOGLEPAY,
        ];
    }
}

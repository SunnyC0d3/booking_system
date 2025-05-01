<?php

namespace App\Constants;

class PaymentGateways
{
    public const STRIPE = 'stripe';

    public static function all(): array
    {
        return [
            self::STRIPE
        ];
    }
}

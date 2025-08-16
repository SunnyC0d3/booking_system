<?php

namespace App\Constants;

class ServiceLocationTypes
{
    public const BUSINESS_PREMISES = 'business_premises';
    public const CLIENT_LOCATION = 'client_location';
    public const VIRTUAL = 'virtual';
    public const OUTDOOR = 'outdoor';

    public const ALL = [
        self::BUSINESS_PREMISES,
        self::CLIENT_LOCATION,
        self::VIRTUAL,
        self::OUTDOOR,
    ];
}

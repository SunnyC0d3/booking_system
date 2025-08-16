<?php

namespace App\Constants;

class ServiceAddOnCategories
{
public const EQUIPMENT = 'equipment';
public const SERVICE_ENHANCEMENT = 'service_enhancement';
public const LOCATION = 'location';
public const OTHER = 'other';

public const ALL = [
self::EQUIPMENT,
self::SERVICE_ENHANCEMENT,
self::LOCATION,
self::OTHER,
];
}

<?php

namespace App\Constants;

class SupplierIntegrationTypes
{
    public const API = 'api';
    public const EMAIL = 'email';
    public const WEBHOOK = 'webhook';
    public const FTP = 'ftp';
    public const CSV_UPLOAD = 'csv_upload';
    public const MANUAL = 'manual';

    public static function all(): array
    {
        return [
            self::API,
            self::EMAIL,
            self::WEBHOOK,
            self::FTP,
            self::CSV_UPLOAD,
            self::MANUAL,
        ];
    }

    public static function labels(): array
    {
        return [
            self::API => 'API Integration',
            self::EMAIL => 'Email Integration',
            self::WEBHOOK => 'Webhook Integration',
            self::FTP => 'FTP Integration',
            self::CSV_UPLOAD => 'CSV Upload',
            self::MANUAL => 'Manual Processing',
        ];
    }

    public static function getAutomatedTypes(): array
    {
        return [
            self::API,
            self::WEBHOOK,
            self::FTP,
            self::CSV_UPLOAD,
        ];
    }
}

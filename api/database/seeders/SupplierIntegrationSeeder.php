<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\SupplierIntegration;
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Database\Seeder;

class SupplierIntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::all();

        foreach ($suppliers as $supplier) {
            $this->createIntegrationForSupplier($supplier);
        }
    }

    private function createIntegrationForSupplier(Supplier $supplier): void
    {
        $integrationData = $this->getIntegrationData($supplier);

        if ($integrationData) {
            SupplierIntegration::create(array_merge($integrationData, [
                'supplier_id' => $supplier->id,
                'last_successful_sync' => $this->getRandomSyncTime(),
                'sync_statistics' => $this->generateSyncStatistics(),
            ]));
        }
    }

    private function getIntegrationData(Supplier $supplier): ?array
    {
        return match($supplier->name) {
            'GlobalTech Distributors' => [
                'integration_type' => SupplierIntegrationTypes::API,
                'name' => 'GlobalTech API Integration',
                'is_active' => true,
                'configuration' => [
                    'api_endpoint' => 'https://api.globaltech-dist.com/v1',
                    'rate_limit' => 100,
                    'timeout' => 30,
                    'format' => 'json',
                    'endpoints' => [
                        'products' => '/products',
                        'orders' => '/orders',
                        'stock' => '/stock',
                        'tracking' => '/tracking'
                    ],
                    'pagination' => [
                        'type' => 'page',
                        'page_size' => 50
                    ]
                ],
                'authentication' => [
                    'type' => 'api_key',
                    'api_key' => 'gt_live_sk_test_123456789',
                    'headers' => [
                        'Authorization' => 'Bearer {api_key}',
                        'Content-Type' => 'application/json'
                    ]
                ],
                'status' => 'active',
                'consecutive_failures' => 0,
                'sync_frequency_minutes' => 60,
                'auto_retry_enabled' => true,
                'max_retry_attempts' => 3,
                'webhook_events' => ['order.status_changed', 'product.stock_updated'],
            ],
            'Fashion Forward Wholesale' => [
                'integration_type' => SupplierIntegrationTypes::WEBHOOK,
                'name' => 'Fashion Forward Webhook Integration',
                'is_active' => true,
                'configuration' => [
                    'webhook_url' => 'https://fashionforward.com/api/webhooks',
                    'webhook_events' => ['order.created', 'order.shipped', 'stock.updated', 'product.updated'],
                    'signature_method' => 'sha256',
                    'retry_attempts' => 3,
                    'timeout' => 15,
                    'content_type' => 'application/json'
                ],
                'authentication' => [
                    'type' => 'webhook_secret',
                    'webhook_secret' => 'whsec_ffwh_live_567890abcdef',
                    'signature_header' => 'X-FFW-Signature'
                ],
                'status' => 'active',
                'consecutive_failures' => 0,
                'sync_frequency_minutes' => 120,
                'auto_retry_enabled' => true,
                'max_retry_attempts' => 5,
                'webhook_events' => ['order.created', 'order.shipped', 'stock.updated'],
            ],
            'HomeDecor Direct' => [
                'integration_type' => SupplierIntegrationTypes::EMAIL,
                'name' => 'HomeDecor Email Integration',
                'is_active' => true,
                'configuration' => [
                    'email_address' => 'orders@homedecor-direct.co.uk',
                    'email_template' => 'order_template_v2',
                    'attachment_format' => 'pdf',
                    'confirmation_required' => true,
                    'subject_format' => 'New Order #{order_id} from {store_name}',
                    'send_copy_to' => 'backup@homedecor-direct.co.uk'
                ],
                'authentication' => [
                    'smtp_host' => 'mail.homedecor-direct.co.uk',
                    'smtp_port' => 587,
                    'smtp_username' => 'system@homedecor-direct.co.uk',
                    'smtp_password' => 'encrypted_password_here'
                ],
                'status' => 'active',
                'consecutive_failures' => 1,
                'sync_frequency_minutes' => 1440,
                'auto_retry_enabled' => true,
                'max_retry_attempts' => 2,
                'webhook_events' => [],
            ],
            'Asian Marketplace Hub' => [
                'integration_type' => SupplierIntegrationTypes::FTP,
                'name' => 'Asian Marketplace FTP Integration',
                'is_active' => true,
                'configuration' => [
                    'ftp_host' => 'ftp.asianmarketplace.sg',
                    'ftp_port' => 21,
                    'upload_directory' => '/orders',
                    'download_directory' => '/stock_updates',
                    'file_format' => 'csv',
                    'filename_pattern' => 'order_{order_id}_{timestamp}.csv',
                    'passive_mode' => true
                ],
                'authentication' => [
                    'ftp_username' => 'dropship_user',
                    'ftp_password' => 'encrypted_ftp_password',
                    'connection_type' => 'ftp'
                ],
                'status' => 'active',
                'consecutive_failures' => 0,
                'sync_frequency_minutes' => 360,
                'auto_retry_enabled' => true,
                'max_retry_attempts' => 3,
                'webhook_events' => [],
            ],
            'TechGadgets Pro' => [
                'integration_type' => SupplierIntegrationTypes::API,
                'name' => 'TechGadgets Pro API v2',
                'is_active' => true,
                'configuration' => [
                    'api_endpoint' => 'https://api.techgadgets.com/v2',
                    'rate_limit' => 200,
                    'timeout' => 15,
                    'format' => 'json',
                    'real_time_stock' => true,
                    'bulk_operations' => true,
                    'endpoints' => [
                        'products' => '/products',
                        'orders' => '/orders',
                        'stock' => '/inventory',
                        'tracking' => '/shipments',
                        'bulk_stock' => '/inventory/bulk'
                    ]
                ],
                'authentication' => [
                    'type' => 'api_key',
                    'api_key' => 'tg_live_ak_987654321',
                    'headers' => [
                        'X-API-Key' => '{api_key}',
                        'Content-Type' => 'application/json'
                    ]
                ],
                'status' => 'active',
                'consecutive_failures' => 0,
                'sync_frequency_minutes' => 30,
                'auto_retry_enabled' => true,
                'max_retry_attempts' => 5,
                'webhook_events' => ['order.confirmed', 'order.shipped', 'stock.real_time'],
            ],
            default => null,
        };
    }

    private function getRandomSyncTime(): ?\Carbon\Carbon
    {
        return now()->subMinutes(rand(30, 2880));
    }

    private function generateSyncStatistics(): array
    {
        $totalSyncs = rand(50, 500);
        $failedSyncs = rand(0, (int)($totalSyncs * 0.05));
        $successfulSyncs = $totalSyncs - $failedSyncs;

        return [
            'total_syncs' => $totalSyncs,
            'successful_syncs' => $successfulSyncs,
            'failed_syncs' => $failedSyncs,
            'products_synced' => rand(100, 1000),
            'orders_sent' => rand(20, 200),
            'last_sync_duration' => rand(5, 120),
            'average_sync_duration' => rand(15, 90),
            'data_transferred_mb' => round(rand(10, 500) / 10, 1),
            'success_rate' => round(($successfulSyncs / $totalSyncs) * 100, 2),
        ];
    }
}

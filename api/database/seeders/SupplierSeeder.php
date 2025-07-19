<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'GlobalTech Distributors',
                'company_name' => 'GlobalTech Distributors Ltd',
                'email' => 'orders@globaltech-dist.com',
                'phone' => '+44 20 7946 0958',
                'address' => '123 Business Park, London, E14 5AB',
                'country' => 'GB',
                'contact_person' => 'Sarah Williams',
                'status' => SupplierStatuses::ACTIVE,
                'integration_type' => SupplierIntegrationTypes::API,
                'commission_rate' => 5.00,
                'processing_time_days' => 2,
                'shipping_methods' => ['standard', 'express', 'overnight'],
                'integration_config' => [
                    'api_endpoint' => 'https://api.globaltech-dist.com/v1',
                    'rate_limit' => 100,
                    'timeout' => 30,
                    'format' => 'json'
                ],
                'api_endpoint' => 'https://api.globaltech-dist.com/v1',
                'api_key' => 'gt_live_sk_test_123456789',
                'webhook_url' => 'https://globaltech-dist.com/webhooks/orders',
                'notes' => 'Primary electronics supplier with excellent API integration',
                'auto_fulfill' => true,
                'stock_sync_enabled' => true,
                'price_sync_enabled' => true,
                'minimum_order_value' => 25.00,
                'maximum_order_value' => 5000.00,
                'supported_countries' => ['GB', 'IE', 'FR', 'DE', 'NL', 'BE'],
            ],
            [
                'name' => 'Fashion Forward Wholesale',
                'company_name' => 'Fashion Forward Wholesale Inc',
                'email' => 'wholesale@fashionforward.com',
                'phone' => '+1 555 0123 456',
                'address' => '456 Fashion Ave, New York, NY 10018',
                'country' => 'US',
                'contact_person' => 'Michael Chen',
                'status' => SupplierStatuses::ACTIVE,
                'integration_type' => SupplierIntegrationTypes::WEBHOOK,
                'commission_rate' => 8.50,
                'processing_time_days' => 3,
                'shipping_methods' => ['standard', 'express'],
                'integration_config' => [
                    'webhook_events' => ['order.created', 'order.shipped', 'stock.updated'],
                    'signature_method' => 'sha256',
                    'retry_attempts' => 3
                ],
                'webhook_url' => 'https://fashionforward.com/api/webhooks',
                'notes' => 'Trendy fashion items with webhook integration',
                'auto_fulfill' => true,
                'stock_sync_enabled' => true,
                'price_sync_enabled' => false,
                'minimum_order_value' => 50.00,
                'maximum_order_value' => 2500.00,
                'supported_countries' => ['US', 'CA', 'GB', 'AU'],
            ],
            [
                'name' => 'HomeDecor Direct',
                'company_name' => 'HomeDecor Direct Ltd',
                'email' => 'orders@homedecor-direct.co.uk',
                'phone' => '+44 161 123 4567',
                'address' => '789 Industrial Estate, Manchester, M1 2AB',
                'country' => 'GB',
                'contact_person' => 'Emma Thompson',
                'status' => SupplierStatuses::ACTIVE,
                'integration_type' => SupplierIntegrationTypes::EMAIL,
                'commission_rate' => 12.00,
                'processing_time_days' => 5,
                'shipping_methods' => ['standard'],
                'integration_config' => [
                    'email_template' => 'order_template_v2',
                    'attachment_format' => 'pdf',
                    'confirmation_required' => true
                ],
                'notes' => 'Home decoration items, email-based ordering system',
                'auto_fulfill' => false,
                'stock_sync_enabled' => false,
                'price_sync_enabled' => false,
                'minimum_order_value' => 100.00,
                'maximum_order_value' => 10000.00,
                'supported_countries' => ['GB', 'IE'],
            ],
            [
                'name' => 'Asian Marketplace Hub',
                'company_name' => 'Asian Marketplace Hub Pte Ltd',
                'email' => 'sales@asianmarketplace.sg',
                'phone' => '+65 6123 4567',
                'address' => '88 Marina Bay, Singapore 018956',
                'country' => 'SG',
                'contact_person' => 'Li Wei Zhang',
                'status' => SupplierStatuses::ACTIVE,
                'integration_type' => SupplierIntegrationTypes::FTP,
                'commission_rate' => 15.00,
                'processing_time_days' => 7,
                'shipping_methods' => ['standard', 'economy'],
                'integration_config' => [
                    'ftp_host' => 'ftp.asianmarketplace.sg',
                    'ftp_port' => 21,
                    'upload_directory' => '/orders',
                    'file_format' => 'csv'
                ],
                'notes' => 'Wide variety of Asian products, FTP file transfer',
                'auto_fulfill' => false,
                'stock_sync_enabled' => true,
                'price_sync_enabled' => true,
                'minimum_order_value' => 20.00,
                'maximum_order_value' => 1500.00,
                'supported_countries' => ['SG', 'MY', 'TH', 'VN', 'PH', 'ID'],
            ],
            [
                'name' => 'EcoFriendly Supplies',
                'company_name' => 'EcoFriendly Supplies GmbH',
                'email' => 'bestellungen@ecofriendly.de',
                'phone' => '+49 30 12345678',
                'address' => 'Umweltstraße 45, 10115 Berlin',
                'country' => 'DE',
                'contact_person' => 'Hans Mueller',
                'status' => SupplierStatuses::PENDING_APPROVAL,
                'integration_type' => SupplierIntegrationTypes::MANUAL,
                'commission_rate' => 10.00,
                'processing_time_days' => 4,
                'shipping_methods' => ['standard', 'express'],
                'integration_config' => [],
                'notes' => 'Sustainable and eco-friendly products, manual processing',
                'auto_fulfill' => false,
                'stock_sync_enabled' => false,
                'price_sync_enabled' => false,
                'minimum_order_value' => 75.00,
                'maximum_order_value' => 3000.00,
                'supported_countries' => ['DE', 'AT', 'CH', 'NL', 'BE', 'FR'],
            ],
            [
                'name' => 'TechGadgets Pro',
                'company_name' => 'TechGadgets Pro Corp',
                'email' => 'api@techgadgets.com',
                'phone' => '+1 800 555 0199',
                'address' => '321 Silicon Valley Blvd, San Jose, CA 95110',
                'country' => 'US',
                'contact_person' => 'David Rodriguez',
                'status' => SupplierStatuses::ACTIVE,
                'integration_type' => SupplierIntegrationTypes::API,
                'commission_rate' => 6.50,
                'processing_time_days' => 1,
                'shipping_methods' => ['standard', 'express', 'overnight'],
                'integration_config' => [
                    'api_endpoint' => 'https://api.techgadgets.com/v2',
                    'rate_limit' => 200,
                    'timeout' => 15,
                    'format' => 'json',
                    'real_time_stock' => true
                ],
                'api_endpoint' => 'https://api.techgadgets.com/v2',
                'api_key' => 'tg_live_ak_987654321',
                'webhook_url' => 'https://api.techgadgets.com/webhooks/status',
                'notes' => 'Latest tech gadgets and accessories with real-time API',
                'auto_fulfill' => true,
                'stock_sync_enabled' => true,
                'price_sync_enabled' => true,
                'minimum_order_value' => 30.00,
                'maximum_order_value' => 8000.00,
                'supported_countries' => ['US', 'CA', 'MX', 'GB', 'DE', 'FR', 'AU', 'JP'],
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Standard Delivery',
                'description' => 'Standard delivery service',
                'carrier' => 'Royal Mail',
                'service_code' => 'standard',
                'estimated_days_min' => 3,
                'estimated_days_max' => 5,
                'is_active' => true,
                'sort_order' => 1,
                'metadata' => json_encode([
                    'max_weight' => 20,
                    'max_dimensions' => ['length' => 100, 'width' => 100, 'height' => 100],
                    'supported_shipping_classes' => ['standard', 'fragile']
                ]),
            ],
            [
                'name' => 'Express Delivery',
                'description' => 'Next working day delivery',
                'carrier' => 'DPD',
                'service_code' => 'express',
                'estimated_days_min' => 1,
                'estimated_days_max' => 2,
                'is_active' => true,
                'sort_order' => 2,
                'metadata' => json_encode([
                    'max_weight' => 30,
                    'max_dimensions' => ['length' => 120, 'width' => 80, 'height' => 80],
                    'supported_shipping_classes' => ['standard', 'express', 'fragile']
                ]),
            ],
            [
                'name' => 'Premium Overnight',
                'description' => 'Guaranteed next day by 1pm',
                'carrier' => 'UPS',
                'service_code' => 'overnight',
                'estimated_days_min' => 1,
                'estimated_days_max' => 1,
                'is_active' => true,
                'sort_order' => 3,
                'metadata' => json_encode([
                    'max_weight' => 25,
                    'signature_required' => true,
                    'supported_shipping_classes' => ['standard', 'express', 'overnight', 'fragile']
                ]),
            ],
            [
                'name' => 'International Standard',
                'description' => 'International delivery service',
                'carrier' => 'Royal Mail',
                'service_code' => 'international',
                'estimated_days_min' => 7,
                'estimated_days_max' => 14,
                'is_active' => true,
                'sort_order' => 4,
                'metadata' => json_encode([
                    'max_weight' => 20,
                    'customs_required' => true,
                    'supported_shipping_classes' => ['standard']
                ]),
            ],
            [
                'name' => 'Heavy Item Delivery',
                'description' => 'Specialized delivery for heavy items',
                'carrier' => 'Parcelforce',
                'service_code' => 'heavy',
                'estimated_days_min' => 3,
                'estimated_days_max' => 7,
                'is_active' => true,
                'sort_order' => 5,
                'metadata' => json_encode([
                    'min_weight' => 20,
                    'max_weight' => 100,
                    'supported_shipping_classes' => ['heavy', 'oversized']
                ]),
            ],
        ];

        foreach ($methods as $method) {
            ShippingMethod::create($method);
        }
    }
}

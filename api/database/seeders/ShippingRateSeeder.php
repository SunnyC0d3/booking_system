<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\ShippingRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShippingRateSeeder extends Seeder
{
    public function run(): void
    {
        $methods = ShippingMethod::all()->keyBy('name');
        $zones = ShippingZone::all()->keyBy('name');

        $rates = [];

        // UK Standard Delivery Rates
        $rates = array_merge($rates, $this->getUKStandardRates($methods, $zones));

        // UK Express Delivery Rates
        $rates = array_merge($rates, $this->getUKExpressRates($methods, $zones));

        // UK Premium Overnight Rates
        $rates = array_merge($rates, $this->getUKOvernightRates($methods, $zones));

        // UK Islands Rates
        $rates = array_merge($rates, $this->getUKIslandsRates($methods, $zones));

        // European Union Rates
        $rates = array_merge($rates, $this->getEURates($methods, $zones));

        // Europe Non-EU Rates
        $rates = array_merge($rates, $this->getEuropeNonEURates($methods, $zones));

        // North America Rates
        $rates = array_merge($rates, $this->getNorthAmericaRates($methods, $zones));

        // Australia & New Zealand Rates
        $rates = array_merge($rates, $this->getANZRates($methods, $zones));

        // Asia Pacific Rates
        $rates = array_merge($rates, $this->getAsiaPacificRates($methods, $zones));

        // Rest of World Rates
        $rates = array_merge($rates, $this->getRestOfWorldRates($methods, $zones));

        // Insert all rates
        ShippingRate::insert($rates);

        // Create zone-method associations
        $this->createZoneMethodAssociations($methods, $zones);
    }

    private function getUKStandardRates($methods, $zones): array
    {
        $standardMethod = $methods['Standard Delivery'];
        $ukZone = $zones['United Kingdom'];

        return [
            [
                'shipping_method_id' => $standardMethod->id,
                'shipping_zone_id' => $ukZone->id,
                'min_weight' => 0,
                'max_weight' => 2,
                'min_total' => 0,
                'max_total' => 2500, // £25
                'rate' => 399, // £3.99
                'free_threshold' => 5000, // Free over £50
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shipping_method_id' => $standardMethod->id,
                'shipping_zone_id' => $ukZone->id,
                'min_weight' => 2.01,
                'max_weight' => 10,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 599, // £5.99
                'free_threshold' => 7500, // Free over £75
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shipping_method_id' => $standardMethod->id,
                'shipping_zone_id' => $ukZone->id,
                'min_weight' => 10.01,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 899, // £8.99
                'free_threshold' => 10000, // Free over £100
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getUKExpressRates($methods, $zones): array
    {
        $expressMethod = $methods['Express Delivery'];
        $ukZone = $zones['United Kingdom'];

        return [
            [
                'shipping_method_id' => $expressMethod->id,
                'shipping_zone_id' => $ukZone->id,
                'min_weight' => 0,
                'max_weight' => 5,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 899, // £8.99
                'free_threshold' => 10000, // Free over £100
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shipping_method_id' => $expressMethod->id,
                'shipping_zone_id' => $ukZone->id,
                'min_weight' => 5.01,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 1299, // £12.99
                'free_threshold' => 15000, // Free over £150
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getUKOvernightRates($methods, $zones): array
    {
        $overnightMethod = $methods['Premium Overnight'];
        $ukZone = $zones['United Kingdom'];

        return [
            [
                'shipping_method_id' => $overnightMethod->id,
                'shipping_zone_id' => $ukZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 1599, // £15.99
                'free_threshold' => 20000, // Free over £200
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getUKIslandsRates($methods, $zones): array
    {
        $standardMethod = $methods['Standard Delivery'];
        $expressMethod = $methods['Express Delivery'];
        $islandsZone = $zones['UK Islands'];

        return [
            [
                'shipping_method_id' => $standardMethod->id,
                'shipping_zone_id' => $islandsZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 999, // £9.99
                'free_threshold' => 10000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shipping_method_id' => $expressMethod->id,
                'shipping_zone_id' => $islandsZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 1599, // £15.99
                'free_threshold' => 15000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getEURates($methods, $zones): array
    {
        $internationalMethod = $methods['International Standard'];
        $euZone = $zones['European Union'];

        return [
            [
                'shipping_method_id' => $internationalMethod->id,
                'shipping_zone_id' => $euZone->id,
                'min_weight' => 0,
                'max_weight' => 2,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 1299, // £12.99
                'free_threshold' => 15000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'shipping_method_id' => $internationalMethod->id,
                'shipping_zone_id' => $euZone->id,
                'min_weight' => 2.01,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 1999, // £19.99
                'free_threshold' => 20000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getEuropeNonEURates($methods, $zones): array
    {
        $internationalMethod = $methods['International Standard'];
        $europeZone = $zones['Europe (Non-EU)'];

        return [
            [
                'shipping_method_id' => $internationalMethod->id,
                'shipping_zone_id' => $europeZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 2499, // £24.99
                'free_threshold' => 25000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getNorthAmericaRates($methods, $zones): array
    {
        $internationalMethod = $methods['International Standard'];
        $naZone = $zones['North America'];

        return [
            [
                'shipping_method_id' => $internationalMethod->id,
                'shipping_zone_id' => $naZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 2999, // £29.99
                'free_threshold' => 30000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getANZRates($methods, $zones): array
    {
        $internationalMethod = $methods['International Standard'];
        $anzZone = $zones['Australia & New Zealand'];

        return [
            [
                'shipping_method_id' => $internationalMethod->id,
                'shipping_zone_id' => $anzZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 3499, // £34.99
                'free_threshold' => 35000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getAsiaPacificRates($methods, $zones): array
    {
        $internationalMethod = $methods['International Standard'];
        $apZone = $zones['Asia Pacific'];

        return [
            [
                'shipping_method_id' => $internationalMethod->id,
                'shipping_zone_id' => $apZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 2799, // £27.99
                'free_threshold' => 30000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function getRestOfWorldRates($methods, $zones): array
    {
        $internationalMethod = $methods['International Standard'];
        $rowZone = $zones['Rest of World'];

        return [
            [
                'shipping_method_id' => $internationalMethod->id,
                'shipping_zone_id' => $rowZone->id,
                'min_weight' => 0,
                'max_weight' => null,
                'min_total' => 0,
                'max_total' => null,
                'rate' => 3999, // £39.99
                'free_threshold' => 40000,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }

    private function createZoneMethodAssociations($methods, $zones): void
    {
        $associations = [
            // UK can use all domestic methods
            ['zone' => 'United Kingdom', 'methods' => ['Standard Delivery', 'Express Delivery', 'Premium Overnight', 'Heavy Item Delivery']],

            // UK Islands limited methods
            ['zone' => 'UK Islands', 'methods' => ['Standard Delivery', 'Express Delivery']],

            // International zones use international method only
            ['zone' => 'European Union', 'methods' => ['International Standard']],
            ['zone' => 'Europe (Non-EU)', 'methods' => ['International Standard']],
            ['zone' => 'North America', 'methods' => ['International Standard']],
            ['zone' => 'Australia & New Zealand', 'methods' => ['International Standard']],
            ['zone' => 'Asia Pacific', 'methods' => ['International Standard']],
            ['zone' => 'Rest of World', 'methods' => ['International Standard']],
        ];

        $zoneMethodData = [];
        foreach ($associations as $association) {
            $zone = $zones[$association['zone']];
            foreach ($association['methods'] as $index => $methodName) {
                $method = $methods[$methodName];
                $zoneMethodData[] = [
                    'shipping_zone_id' => $zone->id,
                    'shipping_method_id' => $method->id,
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('shipping_zones_methods')->insert($zoneMethodData);
    }
}

<?php

namespace Database\Seeders;

use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

class ShippingZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            [
                'name' => 'United Kingdom',
                'description' => 'Mainland UK delivery zone',
                'countries' => ['GB'],
                'postcodes' => null, // All UK postcodes
                'excluded_postcodes' => ['BT', 'GY', 'JE', 'IM'], // Northern Ireland, Channel Islands, Isle of Man
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'UK Islands',
                'description' => 'Northern Ireland, Channel Islands, and Isle of Man',
                'countries' => ['GB'],
                'postcodes' => ['BT*', 'GY*', 'JE*', 'IM*'],
                'excluded_postcodes' => null,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'European Union',
                'description' => 'EU member countries',
                'countries' => [
                    'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
                    'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
                    'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
                ],
                'postcodes' => null,
                'excluded_postcodes' => null,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Europe (Non-EU)',
                'description' => 'European countries outside EU',
                'countries' => ['NO', 'CH', 'IS', 'LI', 'MC', 'SM', 'VA', 'AD', 'AL', 'BA', 'BY', 'MD', 'ME', 'MK', 'RS', 'UA'],
                'postcodes' => null,
                'excluded_postcodes' => null,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'North America',
                'description' => 'United States and Canada',
                'countries' => ['US', 'CA'],
                'postcodes' => null,
                'excluded_postcodes' => null,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Australia & New Zealand',
                'description' => 'Australia and New Zealand delivery zone',
                'countries' => ['AU', 'NZ'],
                'postcodes' => null,
                'excluded_postcodes' => null,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Asia Pacific',
                'description' => 'Major Asia Pacific countries',
                'countries' => ['JP', 'KR', 'SG', 'HK', 'MY', 'TH', 'PH', 'ID', 'VN', 'TW'],
                'postcodes' => null,
                'excluded_postcodes' => null,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Rest of World',
                'description' => 'All other international destinations',
                'countries' => ['*'], // Wildcard for all other countries
                'postcodes' => null,
                'excluded_postcodes' => null,
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($zones as $zone) {
            ShippingZone::create($zone);
        }
    }
}

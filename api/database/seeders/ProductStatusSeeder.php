<?php

namespace Database\Seeders;

use App\Models\ProductStatus;
use App\Constants\ProductStatuses;
use Illuminate\Database\Seeder;

class ProductStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ProductStatuses::ACTIVE,
            ProductStatuses::INACTIVE,
            ProductStatuses::OUT_OF_STOCK,
            ProductStatuses::DISCONTINUED,
            ProductStatuses::COMING_SOON
        ];

        foreach ($statuses as $status) {
            ProductStatus::factory()->create(['name' => $status]);
        }
    }
}

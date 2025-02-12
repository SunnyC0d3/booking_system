<?php

namespace Database\Seeders;

use App\Models\ProductStatus;
use Illuminate\Database\Seeder;

class ProductStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = ['Active', 'Inactive', 'Out Of Stock', 'Discontinued', 'Coming Soon'];

        foreach ($statuses as $status) {
            ProductStatus::factory()->create(['name' => $status]);
        }
    }
}

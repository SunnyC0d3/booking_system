<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::insert([
            ['name' => 'card', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'paypal', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'bank transfer', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'apple pay', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'google pay', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'stripe', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'amex', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}

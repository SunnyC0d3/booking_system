<?php

namespace Database\Seeders;

use App\Models\OrderStatus;
use App\Constants\OrderStatuses;
use Illuminate\Database\Seeder;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            OrderStatuses::PENDING_PAYMENT,
            OrderStatuses::PROCESSING,
            OrderStatuses::CONFIRMED,
            OrderStatuses::SHIPPED,
            OrderStatuses::OUT_FOR_DELIVERY,
            OrderStatuses::DELIVERED,
            OrderStatuses::CANCELLED,
            OrderStatuses::REFUNDED,
            OrderStatuses::PARTIALLY_REFUNDED,
            OrderStatuses::FAILED,
            OrderStatuses::ON_HOLD,
        ];

        OrderStatus::insert(array_map(fn (string $name) => [
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], $statuses));
    }
}

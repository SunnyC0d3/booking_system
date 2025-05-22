<?php

namespace Database\Seeders;

use App\Constants\ShipmentStatuses;
use Illuminate\Database\Seeder;
use App\Models\OrderShipmentStatus;

class OrderShipmentStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ShipmentStatuses::PENDING,
            ShipmentStatuses::CANCELLED,
            ShipmentStatuses::DELIVERED,
            ShipmentStatuses::SHIPPED,
            ShipmentStatuses::RETURNED,
            ShipmentStatuses::CANCELLED,
            ShipmentStatuses::IN_TRANSIT,
        ];

        OrderShipmentStatus::insert(array_map(fn (string $name) => [
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], $statuses));
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Constants\RefundStatuses;
use App\Models\OrderRefundStatus;

class OrderRefundStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            RefundStatuses::PENDING,
            RefundStatuses::PROCESSING,
            RefundStatuses::REFUNDED,
            RefundStatuses::PARTIALLY_REFUNDED,
            RefundStatuses::FAILED,
            RefundStatuses::CANCELLED,
            RefundStatuses::DECLINED
        ];

        OrderRefundStatus::insert(array_map(fn (string $name) => [
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], $statuses));
    }
}

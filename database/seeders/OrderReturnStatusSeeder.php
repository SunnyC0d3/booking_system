<?php

namespace Database\Seeders;

use App\Constants\OrderStatuses;
use Illuminate\Database\Seeder;
use App\Models\OrderReturnStatus;
use App\Constants\ReturnStatuses;

class OrderReturnStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ReturnStatuses::REQUESTED,
            ReturnStatuses::UNDER_REVIEW,
            ReturnStatuses::APPROVED,
            ReturnStatuses::REJECTED,
            ReturnStatuses::RETURN_SHIPPED,
            ReturnStatuses::RETURN_RECEIVED,
            ReturnStatuses::COMPLETED,
            ReturnStatuses::CANCELLED
        ];

        OrderReturnStatus::insert(array_map(fn ($name) => [
            'name'       => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], $statuses));
    }
}

<?php

namespace App\Console\Commands;

use App\Services\V1\Orders\OrderFulfillmentService;
use Illuminate\Console\Command;

class ProcessOverdueShipments extends Command
{
    protected $signature = 'orders:process-overdue-shipments';
    protected $description = 'Process overdue shipments and send delay notifications';

    public function handle(OrderFulfillmentService $fulfillmentService): int
    {
        $this->info('Processing overdue shipments...');

        $processedCount = $fulfillmentService->processOverdueShipments();

        $this->info("Processed {$processedCount} overdue shipments.");

        return Command::SUCCESS;
    }
}

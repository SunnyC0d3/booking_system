<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\V1\Inventory\InventoryAlertService;

class CheckInventory extends Command
{
    protected $signature = 'inventory:check {--force : Force check even if cooldown is active}';
    protected $description = 'Check inventory levels and send alerts for low stock items';

    protected InventoryAlertService $inventoryService;

    public function __construct(InventoryAlertService $inventoryService)
    {
        parent::__construct();
        $this->inventoryService = $inventoryService;
    }

    public function handle(): int
    {
        $this->info('Checking inventory levels...');

        if ($this->option('force')) {
            // Clear the cooldown cache to force alerts
            \Cache::forget('inventory_alert_sent');
            $this->info('Forcing inventory check (ignoring cooldown)...');
        }

        $lowStockItems = $this->inventoryService->checkAllStock();
        $outOfStockItems = $this->inventoryService->getOutOfStockItems();

        if (empty($lowStockItems) && empty($outOfStockItems)) {
            $this->info('✅ All items are properly stocked!');
            return Command::SUCCESS;
        }

        if (!empty($lowStockItems)) {
            $this->warn('⚠️  Found ' . count($lowStockItems) . ' low stock items:');
            foreach ($lowStockItems as $item) {
                $this->line("  • {$item['name']} (Stock: {$item['current_stock']}, Threshold: {$item['threshold']})");
            }

            $this->inventoryService->checkAndAlert();
            $this->info('📧 Low stock alerts sent to administrators.');
        }

        if (!empty($outOfStockItems)) {
            $this->error('❌ Found ' . count($outOfStockItems) . ' out of stock items:');
            foreach ($outOfStockItems as $item) {
                $this->line("  • {$item['name']} (Vendor: {$item['vendor']})");
            }
        }

        return Command::SUCCESS;
    }
}

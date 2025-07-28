<?php

namespace App\Console\Commands;

use App\Models\DropshipOrder;
use App\Models\Supplier;
use App\Constants\DropshipStatuses;
use App\Jobs\SendDropshipOrderToSupplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessAutoFulfillment extends Command
{
    protected $signature = 'dropship:auto-fulfill
                            {--supplier= : Process only for specific supplier ID}
                            {--limit=100 : Maximum number of orders to process}
                            {--dry-run : Show what would be processed without taking action}';

    protected $description = 'Process pending dropship orders for auto-fulfillment';

    public function handle(): int
    {
        $supplierId = $this->option('supplier');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no orders will be processed');
        }

        $query = DropshipOrder::where('status', DropshipStatuses::PENDING)
            ->whereHas('supplier', function($q) {
                $q->where('auto_fulfill', true)
                    ->where('status', 'active');
            })
            ->with(['supplier', 'order', 'product'])
            ->oldest(); // Process oldest orders first

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $pendingOrders = $query->limit($limit)->get();

        if ($pendingOrders->isEmpty()) {
            $this->info('No pending orders found for auto-fulfillment');
            return Command::SUCCESS;
        }

        $this->info("Found {$pendingOrders->count()} orders for auto-fulfillment");

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($pendingOrders as $order) {
            try {
                // Additional validation checks
                if (!$this->canAutoFulfill($order)) {
                    $this->warn("Skipping order {$order->id} - validation failed");
                    $skipped++;
                    continue;
                }

                if (!$dryRun) {
                    // Dispatch the job to send order to supplier
                    SendDropshipOrderToSupplier::dispatch($order);

                    // Update order status
                    $order->update([
                        'status' => DropshipStatuses::SENT_TO_SUPPLIER,
                        'auto_fulfilled_at' => now()
                    ]);

                    $processed++;
                    $this->line("✓ Processed order {$order->id} for {$order->supplier->name}");
                } else {
                    $this->line("Would process order {$order->id} for {$order->supplier->name}");
                    $processed++;
                }

            } catch (\Exception $e) {
                $this->error("Failed to process order {$order->id}: {$e->getMessage()}");
                $errors++;

                Log::error('Auto-fulfillment failed for order', [
                    'order_id' => $order->id,
                    'supplier_id' => $order->supplier_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->displaySummary($processed, $skipped, $errors, $dryRun);

        return Command::SUCCESS;
    }

    private function canAutoFulfill(DropshipOrder $order): bool
    {
        // Check if supplier integration is healthy
        $integration = $order->supplier->getActiveIntegration();
        if (!$integration || !$integration->is_active) {
            return false;
        }

        // Check if order is not too old (prevent auto-fulfilling very old orders)
        if ($order->created_at->diffInHours(now()) > 24) {
            return false;
        }

        // Check if product is still available
        if (!$order->product || !$order->product->is_active) {
            return false;
        }

        // Check if order hasn't failed too many times
        if (($order->retry_count ?? 0) >= 3) {
            return false;
        }

        return true;
    }

    private function displaySummary(int $processed, int $skipped, int $errors, bool $dryRun): void
    {
        $this->line('');
        $this->info('=== Auto-Fulfillment Summary ===');

        if ($dryRun) {
            $this->line("Orders that would be processed: {$processed}");
        } else {
            $this->line("Orders processed: {$processed}");
        }

        $this->line("Orders skipped: {$skipped}");

        if ($errors > 0) {
            $this->error("Orders with errors: {$errors}");
        } else {
            $this->line("Orders with errors: {$errors}");
        }

        if (!$dryRun && $processed > 0) {
            Log::info('Auto-fulfillment completed', [
                'processed' => $processed,
                'skipped' => $skipped,
                'errors' => $errors
            ]);
        }
    }
}

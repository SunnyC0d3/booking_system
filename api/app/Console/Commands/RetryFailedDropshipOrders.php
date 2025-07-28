<?php

namespace App\Console\Commands;

use App\Models\DropshipOrder;
use App\Constants\DropshipStatuses;
use App\Jobs\SendDropshipOrderToSupplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryFailedDropshipOrders extends Command
{
    protected $signature = 'dropship:retry-failed
                            {--max-age=24 : Maximum age in hours of failed orders to retry}
                            {--limit=50 : Maximum number of orders to retry}
                            {--dry-run : Show what would be retried without actually retrying}';

    protected $description = 'Retry failed dropship orders within specified time frame';

    public function handle(): int
    {
        $maxAge = (int) $this->option('max-age');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no orders will be retried');
        }

        $failedOrders = DropshipOrder::where('status', DropshipStatuses::FAILED)
            ->where('updated_at', '>=', now()->subHours($maxAge))
            ->with(['supplier', 'order.user'])
            ->limit($limit)
            ->get();

        if ($failedOrders->isEmpty()) {
            $this->info('No failed orders found to retry');
            return Command::SUCCESS;
        }

        $this->info("Found {$failedOrders->count()} failed orders to retry");

        $retried = 0;
        $skipped = 0;

        foreach ($failedOrders as $order) {
            try {
                // Check if supplier is still active
                if (!$order->supplier->isActive()) {
                    $this->warn("Skipping order {$order->id} - supplier {$order->supplier->name} is inactive");
                    $skipped++;
                    continue;
                }

                // Check if order hasn't been retried too recently
                if ($order->last_retry_at && $order->last_retry_at->diffInMinutes(now()) < 30) {
                    $this->warn("Skipping order {$order->id} - retried too recently");
                    $skipped++;
                    continue;
                }

                if (!$dryRun) {
                    // Update status to pending and dispatch job
                    $order->update([
                        'status' => DropshipStatuses::PENDING,
                        'last_retry_at' => now(),
                        'retry_count' => ($order->retry_count ?? 0) + 1
                    ]);

                    SendDropshipOrderToSupplier::dispatch($order);
                    $retried++;

                    $this->line("✓ Retried order {$order->id} for supplier {$order->supplier->name}");
                } else {
                    $this->line("Would retry order {$order->id} for supplier {$order->supplier->name}");
                    $retried++;
                }

            } catch (\Exception $e) {
                $this->error("Failed to retry order {$order->id}: {$e->getMessage()}");
                $skipped++;
            }
        }

        $this->info("\n=== Retry Summary ===");
        $this->line("Orders retried: {$retried}");
        $this->line("Orders skipped: {$skipped}");

        if (!$dryRun && $retried > 0) {
            Log::info('Failed dropship orders retry completed', [
                'retried' => $retried,
                'skipped' => $skipped,
                'max_age_hours' => $maxAge
            ]);
        }

        return Command::SUCCESS;
    }
}

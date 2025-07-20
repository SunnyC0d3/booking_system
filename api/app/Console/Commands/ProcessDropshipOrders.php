<?php

namespace App\Console\Commands;

use App\Models\DropshipOrder;
use App\Models\Supplier;
use App\Constants\DropshipStatuses;
use App\Constants\SupplierStatuses;
use App\Services\V1\Dropshipping\DropshipOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessDropshipOrders extends Command
{
    protected $signature = 'dropship:process-orders
                            {--supplier= : Process orders for specific supplier ID}
                            {--status= : Process orders with specific status}
                            {--limit=50 : Maximum number of orders to process}
                            {--dry-run : Show what would be processed without making changes}';

    protected $description = 'Process pending dropship orders and send them to suppliers';

    private DropshipOrderService $dropshipOrderService;

    public function __construct(DropshipOrderService $dropshipOrderService)
    {
        parent::__construct();
        $this->dropshipOrderService = $dropshipOrderService;
    }

    public function handle()
    {
        $supplierId = $this->option('supplier');
        $status = $this->option('status');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        $orders = $this->getDropshipOrders($supplierId, $status, $limit);

        if ($orders->isEmpty()) {
            $this->info('No dropship orders found for processing');
            return 0;
        }

        $this->info("Found {$orders->count()} dropship order(s) to process");

        $stats = [
            'total_orders' => $orders->count(),
            'processed_successfully' => 0,
            'failed_processing' => 0,
            'skipped_orders' => 0,
            'errors' => []
        ];

        $progressBar = $this->output->createProgressBar($orders->count());
        $progressBar->start();

        foreach ($orders as $order) {
            try {
                $result = $this->processDropshipOrder($order, $dryRun);

                if ($result['processed']) {
                    $stats['processed_successfully']++;
                    $this->logOrderProcessed($order, $result['action']);
                } else {
                    $stats['skipped_orders']++;
                    $this->logOrderSkipped($order, $result['reason']);
                }

            } catch (Exception $e) {
                $stats['failed_processing']++;
                $stats['errors'][] = "Order {$order->id}: " . $e->getMessage();

                $this->logOrderFailed($order, $e);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');

        $this->displayProcessingSummary($stats, $dryRun);

        return $stats['failed_processing'] > 0 ? 1 : 0;
    }

    private function getDropshipOrders($supplierId, $status, $limit)
    {
        $query = DropshipOrder::with(['supplier', 'order', 'dropshipOrderItems'])
            ->whereHas('supplier', function ($q) {
                $q->where('status', SupplierStatuses::ACTIVE);
            });

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        if ($status) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [
                DropshipStatuses::PENDING,
                DropshipStatuses::REJECTED_BY_SUPPLIER
            ])->where(function ($q) {
                $q->where('auto_retry_enabled', true)
                    ->where('retry_count', '<', 3);
            });
        }

        return $query->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    private function processDropshipOrder(DropshipOrder $order, bool $dryRun): array
    {
        if (!$order->supplier->isActive()) {
            return [
                'processed' => false,
                'reason' => 'Supplier is not active'
            ];
        }

        if ($order->isDelivered() || $order->isCancelled()) {
            return [
                'processed' => false,
                'reason' => 'Order already completed or cancelled'
            ];
        }

        if ($dryRun) {
            return [
                'processed' => true,
                'action' => $this->getDryRunAction($order)
            ];
        }

        $action = 'unknown';

        if ($order->isPending()) {
            $this->dropshipOrderService->sendToSupplier($order);
            $action = 'sent_to_supplier';
        } elseif ($order->isRejected() && $order->canRetry()) {
            $this->dropshipOrderService->retryFailedOrder($order);
            $action = 'retried';
        } elseif ($order->isSentToSupplier()) {
            $this->checkForSupplierUpdates($order);
            $action = 'checked_for_updates';
        } else {
            return [
                'processed' => false,
                'reason' => "Order status '{$order->status}' cannot be processed automatically"
            ];
        }

        return [
            'processed' => true,
            'action' => $action
        ];
    }

    private function getDryRunAction(DropshipOrder $order): string
    {
        if ($order->isPending()) {
            return 'would_send_to_supplier';
        } elseif ($order->isRejected() && $order->canRetry()) {
            return 'would_retry';
        } elseif ($order->isSentToSupplier()) {
            return 'would_check_for_updates';
        }

        return 'would_skip';
    }

    private function checkForSupplierUpdates(DropshipOrder $order): void
    {
        $integration = $order->supplier->getActiveIntegration();

        if (!$integration || !$integration->isAutomated()) {
            return;
        }

        switch ($integration->integration_type) {
            case 'api':
                $this->checkApiForUpdates($order, $integration);
                break;
            case 'webhook':
                break;
            default:
                return;
        }
    }

    private function checkApiForUpdates(DropshipOrder $order, $integration): void
    {
        if (!$order->supplier_order_id) {
            return;
        }

        $mockResponse = $this->getMockSupplierResponse($order);

        if ($mockResponse) {
            $this->dropshipOrderService->processSupplierResponse($order, $mockResponse);
        }
    }

    private function getMockSupplierResponse(DropshipOrder $order): ?array
    {
        $hoursSinceCreated = $order->created_at->diffInHours(now());

        if ($hoursSinceCreated > 48 && $order->isSentToSupplier()) {
            return [
                'status' => 'confirmed',
                'supplier_order_id' => 'SUP_' . $order->id . '_' . time(),
                'estimated_delivery' => now()->addDays(rand(3, 7))->toDateString()
            ];
        }

        if ($hoursSinceCreated > 72 && $order->isConfirmed()) {
            return [
                'status' => 'shipped',
                'tracking_number' => 'TRK' . str_pad($order->id, 8, '0', STR_PAD_LEFT),
                'carrier' => 'DHL Express',
                'estimated_delivery' => now()->addDays(rand(1, 3))->toDateString()
            ];
        }

        if ($hoursSinceCreated > 120 && $order->isShipped()) {
            return [
                'status' => 'delivered'
            ];
        }

        return null;
    }

    private function logOrderProcessed(DropshipOrder $order, string $action): void
    {
        Log::info('Dropship order processed via command', [
            'dropship_order_id' => $order->id,
            'order_id' => $order->order_id,
            'supplier_id' => $order->supplier_id,
            'action' => $action,
            'status' => $order->status
        ]);
    }

    private function logOrderSkipped(DropshipOrder $order, string $reason): void
    {
        Log::debug('Dropship order skipped during processing', [
            'dropship_order_id' => $order->id,
            'order_id' => $order->order_id,
            'supplier_id' => $order->supplier_id,
            'reason' => $reason,
            'status' => $order->status
        ]);
    }

    private function logOrderFailed(DropshipOrder $order, Exception $e): void
    {
        Log::error('Failed to process dropship order via command', [
            'dropship_order_id' => $order->id,
            'order_id' => $order->order_id,
            'supplier_id' => $order->supplier_id,
            'error' => $e->getMessage(),
            'status' => $order->status
        ]);
    }

    private function displayProcessingSummary(array $stats, bool $dryRun): void
    {
        $this->info('');
        $this->info('=== Processing Summary ===');
        $this->line("Total orders: {$stats['total_orders']}");

        if (!$dryRun) {
            $this->line("Successfully processed: {$stats['processed_successfully']}");
            $this->line("Failed processing: {$stats['failed_processing']}");
            $this->line("Skipped orders: {$stats['skipped_orders']}");
        } else {
            $this->line("Would be processed: {$stats['processed_successfully']}");
            $this->line("Would be skipped: {$stats['skipped_orders']}");
        }

        if (!empty($stats['errors'])) {
            $this->error('');
            $this->error('Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }

        if ($stats['processed_successfully'] > 0) {
            $this->info('');
            if ($dryRun) {
                $this->info('Run without --dry-run to actually process the orders');
            } else {
                $this->info('Orders have been processed and sent to suppliers');
            }
        }
    }
}

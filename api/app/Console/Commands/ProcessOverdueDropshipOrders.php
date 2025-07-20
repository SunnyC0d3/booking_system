<?php

namespace App\Console\Commands;

use App\Models\DropshipOrder;
use App\Models\Order;
use App\Constants\DropshipStatuses;
use App\Constants\OrderStatuses;
use App\Services\V1\Dropshipping\DropshipOrderService;
use App\Services\V1\Emails\Email;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessOverdueDropshipOrders extends Command
{
    protected $signature = 'dropship:process-overdue
                            {--days=7 : Number of days past estimated delivery to consider overdue}
                            {--action=notify : Action to take (notify, retry, cancel, escalate)}
                            {--supplier= : Process overdue orders for specific supplier ID}
                            {--limit=100 : Maximum number of orders to process}
                            {--dry-run : Show what would be processed without making changes}';

    protected $description = 'Process overdue dropship orders and take appropriate actions';

    private DropshipOrderService $dropshipOrderService;
    private Email $emailService;

    public function __construct(DropshipOrderService $dropshipOrderService, Email $emailService)
    {
        parent::__construct();
        $this->dropshipOrderService = $dropshipOrderService;
        $this->emailService = $emailService;
    }

    public function handle()
    {
        $overdueDays = (int) $this->option('days');
        $action = $this->option('action');
        $supplierId = $this->option('supplier');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        if (!in_array($action, ['notify', 'retry', 'cancel', 'escalate'])) {
            $this->error('Invalid action. Must be one of: notify, retry, cancel, escalate');
            return 1;
        }

        $overdueOrders = $this->getOverdueOrders($overdueDays, $supplierId, $limit);

        if ($overdueOrders->isEmpty()) {
            $this->info('No overdue dropship orders found');
            return 0;
        }

        $this->info("Found {$overdueOrders->count()} overdue dropship order(s)");
        $this->info("Processing with action: {$action}");

        $stats = [
            'total_orders' => $overdueOrders->count(),
            'processed_successfully' => 0,
            'failed_processing' => 0,
            'notifications_sent' => 0,
            'orders_retried' => 0,
            'orders_cancelled' => 0,
            'orders_escalated' => 0,
            'errors' => []
        ];

        $progressBar = $this->output->createProgressBar($overdueOrders->count());
        $progressBar->start();

        foreach ($overdueOrders as $order) {
            try {
                $result = $this->processOverdueOrder($order, $action, $overdueDays, $dryRun);

                if ($result['processed']) {
                    $stats['processed_successfully']++;
                    $this->updateActionStats($stats, $result['action']);
                    $this->logOverdueOrderProcessed($order, $result['action'], $overdueDays);
                }

            } catch (Exception $e) {
                $stats['failed_processing']++;
                $stats['errors'][] = "Order {$order->id}: " . $e->getMessage();

                $this->logOverdueOrderFailed($order, $e);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->line('');

        $this->displayOverdueSummary($stats, $action, $dryRun);

        return $stats['failed_processing'] > 0 ? 1 : 0;
    }

    private function getOverdueOrders(int $overdueDays, $supplierId, int $limit)
    {
        $overdueDate = now()->subDays($overdueDays);

        $query = DropshipOrder::with(['supplier', 'order.user'])
            ->where(function ($q) use ($overdueDate) {
                $q->where('estimated_delivery', '<', $overdueDate)
                    ->orWhere(function ($subQ) use ($overdueDate) {
                        $subQ->whereNull('estimated_delivery')
                            ->where('sent_to_supplier_at', '<', $overdueDate);
                    });
            })
            ->whereNotIn('status', [
                DropshipStatuses::DELIVERED,
                DropshipStatuses::CANCELLED,
                DropshipStatuses::REFUNDED
            ]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    private function processOverdueOrder(DropshipOrder $order, string $action, int $overdueDays, bool $dryRun): array
    {
        $daysPastDue = $this->calculateDaysPastDue($order);

        if ($dryRun) {
            return [
                'processed' => true,
                'action' => "would_{$action}",
                'days_past_due' => $daysPastDue
            ];
        }

        switch ($action) {
            case 'notify':
                return $this->notifyAboutOverdueOrder($order, $daysPastDue);

            case 'retry':
                return $this->retryOverdueOrder($order, $daysPastDue);

            case 'cancel':
                return $this->cancelOverdueOrder($order, $daysPastDue);

            case 'escalate':
                return $this->escalateOverdueOrder($order, $daysPastDue);

            default:
                throw new Exception("Unknown action: {$action}");
        }
    }

    private function notifyAboutOverdueOrder(DropshipOrder $order, int $daysPastDue): array
    {
        $customer = $order->order->user;
        $supplier = $order->supplier;

        if ($customer && $customer->email) {
            $this->sendCustomerNotification($customer, $order, $daysPastDue);
        }

        $this->sendSupplierNotification($supplier, $order, $daysPastDue);
        $this->sendInternalNotification($order, $daysPastDue);

        return [
            'processed' => true,
            'action' => 'notified',
            'days_past_due' => $daysPastDue
        ];
    }

    private function retryOverdueOrder(DropshipOrder $order, int $daysPastDue): array
    {
        if (!$order->canRetry()) {
            throw new Exception('Order cannot be retried (max attempts reached or retry disabled)');
        }

        $this->dropshipOrderService->retryFailedOrder($order);

        return [
            'processed' => true,
            'action' => 'retried',
            'days_past_due' => $daysPastDue
        ];
    }

    private function cancelOverdueOrder(DropshipOrder $order, int $daysPastDue): array
    {
        if ($order->isDelivered()) {
            throw new Exception('Cannot cancel delivered order');
        }

        $reason = "Automatically cancelled - overdue by {$daysPastDue} days";
        $order->markAsCancelled($reason);

        $this->updateMainOrderStatus($order);

        return [
            'processed' => true,
            'action' => 'cancelled',
            'days_past_due' => $daysPastDue
        ];
    }

    private function escalateOverdueOrder(DropshipOrder $order, int $daysPastDue): array
    {
        $this->sendEscalationNotification($order, $daysPastDue);

        $order->update([
            'notes' => $order->notes . "\nEscalated due to being overdue by {$daysPastDue} days on " . now()->toDateTimeString()
        ]);

        return [
            'processed' => true,
            'action' => 'escalated',
            'days_past_due' => $daysPastDue
        ];
    }

    private function calculateDaysPastDue(DropshipOrder $order): int
    {
        if ($order->estimated_delivery) {
            return max(0, $order->estimated_delivery->diffInDays(now()));
        }

        if ($order->sent_to_supplier_at) {
            $estimatedDays = $order->supplier->processing_time_days ?? 7;
            $expectedDelivery = $order->sent_to_supplier_at->addDays($estimatedDays);
            return max(0, $expectedDelivery->diffInDays(now()));
        }

        return 0;
    }

    private function sendCustomerNotification($customer, DropshipOrder $order, int $daysPastDue): void
    {
        Log::info('Sending overdue order notification to customer', [
            'customer_email' => $customer->email,
            'dropship_order_id' => $order->id,
            'days_past_due' => $daysPastDue
        ]);
    }

    private function sendSupplierNotification($supplier, DropshipOrder $order, int $daysPastDue): void
    {
        Log::info('Sending overdue order notification to supplier', [
            'supplier_email' => $supplier->email,
            'supplier_name' => $supplier->name,
            'dropship_order_id' => $order->id,
            'days_past_due' => $daysPastDue
        ]);
    }

    private function sendInternalNotification(DropshipOrder $order, int $daysPastDue): void
    {
        Log::warning('Internal notification: Overdue dropship order', [
            'dropship_order_id' => $order->id,
            'order_id' => $order->order_id,
            'supplier_id' => $order->supplier_id,
            'days_past_due' => $daysPastDue,
            'estimated_delivery' => $order->estimated_delivery,
            'sent_to_supplier_at' => $order->sent_to_supplier_at
        ]);
    }

    private function sendEscalationNotification(DropshipOrder $order, int $daysPastDue): void
    {
        Log::critical('Escalation: Severely overdue dropship order', [
            'dropship_order_id' => $order->id,
            'order_id' => $order->order_id,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier->name,
            'customer_email' => $order->order->user->email ?? 'Guest',
            'days_past_due' => $daysPastDue,
            'total_value' => $order->getTotalRetailFormatted(),
            'requires_immediate_attention' => true
        ]);
    }

    private function updateMainOrderStatus(DropshipOrder $order): void
    {
        $mainOrder = $order->order;
        $allDropshipOrders = $mainOrder->dropshipOrders;

        if ($allDropshipOrders->every(fn($ds) => in_array($ds->status, [
            DropshipStatuses::CANCELLED,
            DropshipStatuses::DELIVERED,
            DropshipStatuses::REFUNDED
        ]))) {
            $hasDelivered = $allDropshipOrders->some(fn($ds) => $ds->status === DropshipStatuses::DELIVERED);
            $allCancelled = $allDropshipOrders->every(fn($ds) => $ds->status === DropshipStatuses::CANCELLED);

            if ($allCancelled) {
                $this->updateOrderStatus($mainOrder, OrderStatuses::CANCELLED);
            } elseif ($hasDelivered) {
                $this->updateOrderStatus($mainOrder, OrderStatuses::DELIVERED);
            }
        }
    }

    private function updateOrderStatus(Order $order, string $status): void
    {
        $statusId = \App\Models\OrderStatus::where('name', $status)->value('id');
        if ($statusId) {
            $order->update(['status_id' => $statusId]);
        }
    }

    private function updateActionStats(array &$stats, string $action): void
    {
        switch ($action) {
            case 'notified':
                $stats['notifications_sent']++;
                break;
            case 'retried':
                $stats['orders_retried']++;
                break;
            case 'cancelled':
                $stats['orders_cancelled']++;
                break;
            case 'escalated':
                $stats['orders_escalated']++;
                break;
        }
    }

    private function logOverdueOrderProcessed(DropshipOrder $order, string $action, int $overdueDays): void
    {
        Log::info('Overdue dropship order processed', [
            'dropship_order_id' => $order->id,
            'order_id' => $order->order_id,
            'supplier_id' => $order->supplier_id,
            'action' => $action,
            'days_overdue_threshold' => $overdueDays,
            'actual_days_past_due' => $this->calculateDaysPastDue($order)
        ]);
    }

    private function logOverdueOrderFailed(DropshipOrder $order, Exception $e): void
    {
        Log::error('Failed to process overdue dropship order', [
            'dropship_order_id' => $order->id,
            'order_id' => $order->order_id,
            'supplier_id' => $order->supplier_id,
            'error' => $e->getMessage()
        ]);
    }

    private function displayOverdueSummary(array $stats, string $action, bool $dryRun): void
    {
        $this->info('');
        $this->info('=== Overdue Processing Summary ===');
        $this->line("Total overdue orders: {$stats['total_orders']}");
        $this->line("Successfully processed: {$stats['processed_successfully']}");
        $this->line("Failed processing: {$stats['failed_processing']}");

        if (!$dryRun) {
            switch ($action) {
                case 'notify':
                    $this->line("Notifications sent: {$stats['notifications_sent']}");
                    break;
                case 'retry':
                    $this->line("Orders retried: {$stats['orders_retried']}");
                    break;
                case 'cancel':
                    $this->line("Orders cancelled: {$stats['orders_cancelled']}");
                    break;
                case 'escalate':
                    $this->line("Orders escalated: {$stats['orders_escalated']}");
                    break;
            }
        } else {
            $this->line("Would be {$action}d: {$stats['processed_successfully']}");
        }

        if (!empty($stats['errors'])) {
            $this->error('');
            $this->error('Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }
    }
}

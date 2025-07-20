<?php

namespace App\Jobs;

use App\Models\DropshipOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Constants\DropshipStatuses;
use App\Constants\SupplierStatuses;
use App\Services\V1\Dropshipping\DropshipOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class RetryFailedDropshipOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 2;
    public $maxExceptions = 2;

    protected DropshipOrder $dropshipOrder;
    protected array $retryOptions;
    protected string $retryReason;

    public function __construct(DropshipOrder $dropshipOrder, string $retryReason = 'scheduled_retry', array $retryOptions = [])
    {
        $this->dropshipOrder = $dropshipOrder;
        $this->retryReason = $retryReason;
        $this->retryOptions = $retryOptions;

        $this->onQueue('dropship_retry');

        $delay = $this->calculateRetryDelay();
        if ($delay > 0) {
            $this->delay($delay);
        }
    }

    public function handle(DropshipOrderService $dropshipOrderService): void
    {
        try {
            Log::info('Processing dropship order retry', [
                'dropship_order_id' => $this->dropshipOrder->id,
                'order_id' => $this->dropshipOrder->order_id,
                'supplier_id' => $this->dropshipOrder->supplier_id,
                'current_status' => $this->dropshipOrder->status,
                'retry_count' => $this->dropshipOrder->retry_count,
                'retry_reason' => $this->retryReason,
                'attempt' => $this->attempts()
            ]);

            if (!$this->shouldRetryOrder()) {
                Log::info('Dropship order retry skipped - conditions not met', [
                    'dropship_order_id' => $this->dropshipOrder->id,
                    'reason' => $this->getSkipReason()
                ]);
                return;
            }

            $this->performPreRetryChecks();
            $this->executeRetryAttempt($dropshipOrderService);
            $this->handleSuccessfulRetry();

        } catch (Exception $e) {
            $this->handleRetryException($e);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('RetryFailedDropshipOrder job failed permanently', [
            'dropship_order_id' => $this->dropshipOrder->id,
            'retry_reason' => $this->retryReason,
            'error' => $exception->getMessage(),
            'final_retry_count' => $this->dropshipOrder->retry_count
        ]);

        $this->markRetryAsFailed($exception->getMessage());
    }

    private function shouldRetryOrder(): bool
    {
        $this->dropshipOrder->refresh();

        if (!$this->dropshipOrder->canRetry()) {
            return false;
        }

        if (!in_array($this->dropshipOrder->status, [
            DropshipStatuses::PENDING,
            DropshipStatuses::REJECTED_BY_SUPPLIER,
            DropshipStatuses::ON_HOLD
        ])) {
            return false;
        }

        if (!$this->dropshipOrder->supplier->isActive()) {
            return false;
        }

        return true;
    }

    private function getSkipReason(): string
    {
        $this->dropshipOrder->refresh();

        if (!$this->dropshipOrder->canRetry()) {
            return 'Maximum retry attempts reached or retry disabled';
        }

        if (!in_array($this->dropshipOrder->status, [
            DropshipStatuses::PENDING,
            DropshipStatuses::REJECTED_BY_SUPPLIER,
            DropshipStatuses::ON_HOLD
        ])) {
            return "Order status '{$this->dropshipOrder->status}' not eligible for retry";
        }

        if (!$this->dropshipOrder->supplier->isActive()) {
            return "Supplier is not active (status: {$this->dropshipOrder->supplier->status})";
        }

        return 'Unknown reason';
    }

    private function performPreRetryChecks(): void
    {
        $this->validateSupplierAvailability();
        $this->validateProductAvailability();
        $this->validateOrderIntegrity();
        $this->checkForAlternativeSuppliers();
    }

    private function validateSupplierAvailability(): void
    {
        $supplier = $this->dropshipOrder->supplier;

        if ($supplier->status !== SupplierStatuses::ACTIVE) {
            throw new Exception("Supplier '{$supplier->name}' is not active (status: {$supplier->status})");
        }

        $integration = $supplier->getActiveIntegration();
        if (!$integration) {
            throw new Exception("No active integration found for supplier '{$supplier->name}'");
        }

        if ($integration->consecutive_failures >= 5) {
            throw new Exception("Supplier integration has too many consecutive failures ({$integration->consecutive_failures})");
        }
    }

    private function validateProductAvailability(): void
    {
        $outOfStockItems = [];
        $discontinuedItems = [];

        foreach ($this->dropshipOrder->dropshipOrderItems as $item) {
            $supplierProduct = $item->supplierProduct;

            if (!$supplierProduct) {
                throw new Exception("Supplier product not found for item: {$item->supplier_sku}");
            }

            if (!$supplierProduct->is_active) {
                $discontinuedItems[] = $item->supplier_sku;
                continue;
            }

            if ($supplierProduct->stock_quantity < $item->quantity) {
                $outOfStockItems[] = [
                    'sku' => $item->supplier_sku,
                    'required' => $item->quantity,
                    'available' => $supplierProduct->stock_quantity
                ];
            }
        }

        if (!empty($discontinuedItems)) {
            throw new Exception("Items discontinued by supplier: " . implode(', ', $discontinuedItems));
        }

        if (!empty($outOfStockItems)) {
            $stockIssues = array_map(function($item) {
                return "{$item['sku']} (need {$item['required']}, have {$item['available']})";
            }, $outOfStockItems);

            throw new Exception("Insufficient stock for items: " . implode(', ', $stockIssues));
        }
    }

    private function validateOrderIntegrity(): void
    {
        if ($this->dropshipOrder->dropshipOrderItems->isEmpty()) {
            throw new Exception('Dropship order has no items');
        }

        if ($this->dropshipOrder->total_cost <= 0) {
            throw new Exception('Dropship order has invalid total cost');
        }

        if (empty($this->dropshipOrder->shipping_address)) {
            throw new Exception('Dropship order has no shipping address');
        }

        $mainOrder = $this->dropshipOrder->order;
        if (!$mainOrder || in_array($mainOrder->status->name, ['Cancelled', 'Refunded'])) {
            throw new Exception('Main order is no longer valid for fulfillment');
        }
    }

    private function checkForAlternativeSuppliers(): void
    {
        if ($this->retryOptions['try_alternative_suppliers'] ?? false) {
            $this->evaluateAlternativeSuppliers();
        }
    }

    private function evaluateAlternativeSuppliers(): void
    {
        $productIds = $this->dropshipOrder->dropshipOrderItems->pluck('orderItem.product_id')->unique();

        $alternativeSuppliers = DB::table('product_supplier_mappings as psm')
            ->join('suppliers as s', 's.id', '=', 'psm.supplier_id')
            ->join('supplier_products as sp', 'sp.id', '=', 'psm.supplier_product_id')
            ->whereIn('psm.product_id', $productIds)
            ->where('psm.supplier_id', '!=', $this->dropshipOrder->supplier_id)
            ->where('psm.is_active', true)
            ->where('s.status', SupplierStatuses::ACTIVE)
            ->where('sp.is_active', true)
            ->where('sp.stock_quantity', '>', 0)
            ->select('s.id', 's.name', 'psm.product_id')
            ->get()
            ->groupBy('product_id');

        $hasAlternatives = $alternativeSuppliers->count() === $productIds->count();

        if ($hasAlternatives) {
            Log::info('Alternative suppliers available for retry', [
                'dropship_order_id' => $this->dropshipOrder->id,
                'alternative_suppliers' => $alternativeSuppliers->map(function($suppliers) {
                    return $suppliers->pluck('name')->unique()->values();
                })->toArray()
            ]);
        } else {
            Log::warning('No alternative suppliers available for all products', [
                'dropship_order_id' => $this->dropshipOrder->id,
                'products_without_alternatives' => $productIds->diff($alternativeSuppliers->keys())->values()
            ]);
        }
    }

    private function executeRetryAttempt(DropshipOrderService $dropshipOrderService): void
    {
        DB::transaction(function () use ($dropshipOrderService) {
            $this->incrementRetryCount();
            $this->resetOrderStatus();
            $this->updateRetryMetadata();

            $success = $dropshipOrderService->sendToSupplier($this->dropshipOrder);

            if (!$success) {
                throw new Exception('Failed to send order to supplier during retry');
            }
        });
    }

    private function incrementRetryCount(): void
    {
        $this->dropshipOrder->increment('retry_count');
        $this->dropshipOrder->update(['last_retry_at' => now()]);
    }

    private function resetOrderStatus(): void
    {
        if ($this->dropshipOrder->status === DropshipStatuses::REJECTED_BY_SUPPLIER) {
            $this->dropshipOrder->updateStatus(DropshipStatuses::PENDING);
        }
    }

    private function updateRetryMetadata(): void
    {
        $currentNotes = $this->dropshipOrder->notes ?? '';
        $retryNote = "Retry #{$this->dropshipOrder->retry_count} initiated on " . now()->toDateTimeString() .
            " (Reason: {$this->retryReason})";

        $this->dropshipOrder->update([
            'notes' => $currentNotes ? $currentNotes . "\n" . $retryNote : $retryNote,
            'webhook_data' => array_merge($this->dropshipOrder->webhook_data ?? [], [
                'last_retry' => [
                    'attempt' => $this->dropshipOrder->retry_count,
                    'reason' => $this->retryReason,
                    'timestamp' => now()->toISOString(),
                    'options' => $this->retryOptions
                ]
            ])
        ]);
    }

    private function handleSuccessfulRetry(): void
    {
        Log::info('Dropship order retry successful', [
            'dropship_order_id' => $this->dropshipOrder->id,
            'retry_count' => $this->dropshipOrder->retry_count,
            'retry_reason' => $this->retryReason,
            'new_status' => $this->dropshipOrder->status,
            'supplier_id' => $this->dropshipOrder->supplier_id
        ]);

        $integration = $this->dropshipOrder->supplier->getActiveIntegration();
        if ($integration) {
            $integration->recordSuccessfulSync([
                'retry_successful' => true,
                'retry_attempt' => $this->dropshipOrder->retry_count,
                'retry_reason' => $this->retryReason
            ]);
        }

        $this->scheduleFollowUpCheck();
    }

    private function scheduleFollowUpCheck(): void
    {
        if ($this->retryOptions['schedule_follow_up'] ?? true) {
            RetryFailedDropshipOrder::dispatch(
                $this->dropshipOrder,
                'follow_up_check',
                ['check_only' => true]
            )->delay(now()->addHours(2));
        }
    }

    private function markRetryAsFailed(string $errorMessage): void
    {
        $this->dropshipOrder->update([
            'notes' => ($this->dropshipOrder->notes ?? '') .
                "\nRetry failed permanently: {$errorMessage} (Attempt #{$this->dropshipOrder->retry_count})"
        ]);

        if ($this->dropshipOrder->retry_count >= 3) {
            $this->dropshipOrder->markAsCancelled("Maximum retry attempts exceeded: {$errorMessage}");
        }

        $integration = $this->dropshipOrder->supplier->getActiveIntegration();
        if ($integration) {
            $integration->recordFailedSync("Retry failed: {$errorMessage}");
        }
    }

    private function calculateRetryDelay(): int
    {
        $retryCount = $this->dropshipOrder->retry_count;
        $baseDelay = $this->retryOptions['base_delay'] ?? 300;

        $delayMultipliers = [
            0 => 1,    // 5 minutes
            1 => 3,    // 15 minutes
            2 => 12,   // 1 hour
            3 => 48,   // 4 hours
        ];

        $multiplier = $delayMultipliers[$retryCount] ?? 96; // 8 hours for attempts 4+

        return $baseDelay * $multiplier;
    }

    private function handleRetryException(Exception $e): void
    {
        Log::error('Exception during dropship order retry', [
            'dropship_order_id' => $this->dropshipOrder->id,
            'retry_count' => $this->dropshipOrder->retry_count,
            'retry_reason' => $this->retryReason,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts()
        ]);

        $this->dropshipOrder->update([
            'notes' => ($this->dropshipOrder->notes ?? '') .
                "\nRetry attempt #{$this->dropshipOrder->retry_count} failed: " . $e->getMessage()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addDays(3);
    }

    public function backoff(): array
    {
        return [300, 900];
    }
}

<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\SupplierIntegration;
use App\Models\DropshipOrder;
use App\Models\SupplierProduct;
use App\Models\ProductSupplierMapping;
use App\Constants\DropshipStatuses;
use App\Constants\DropshipProductSyncStatuses;
use App\Services\V1\Dropshipping\DropshipOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProcessSupplierWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    public $maxExceptions = 3;

    protected array $webhookData;
    protected ?Supplier $supplier;
    protected ?SupplierIntegration $integration;
    protected string $eventType;

    public function __construct(array $webhookData, ?Supplier $supplier = null, ?SupplierIntegration $integration = null)
    {
        $this->webhookData = $webhookData;
        $this->supplier = $supplier;
        $this->integration = $integration;
        $this->eventType = $webhookData['event_type'] ?? $webhookData['type'] ?? 'unknown';

        $this->onQueue('webhook_processing');
    }

    public function handle(DropshipOrderService $dropshipOrderService): void
    {
        try {
            Log::info('Processing supplier webhook', [
                'event_type' => $this->eventType,
                'supplier_id' => $this->supplier?->id,
                'webhook_id' => $this->webhookData['webhook_id'] ?? $this->webhookData['id'] ?? null,
                'attempt' => $this->attempts()
            ]);

            if (!$this->supplier) {
                $this->supplier = $this->identifySupplier();
            }

            if (!$this->integration) {
                $this->integration = $this->supplier?->getActiveIntegration();
            }

            if (!$this->supplier || !$this->integration) {
                throw new Exception('Could not identify supplier or integration for webhook');
            }

            $this->validateWebhookSignature();
            $this->processWebhookEvent($dropshipOrderService);
            $this->recordSuccessfulProcessing();

        } catch (Exception $e) {
            $this->handleWebhookException($e);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('ProcessSupplierWebhook job failed permanently', [
            'event_type' => $this->eventType,
            'supplier_id' => $this->supplier?->id,
            'error' => $exception->getMessage(),
            'webhook_data' => $this->webhookData,
            'attempts' => $this->attempts()
        ]);

        if ($this->integration) {
            $this->integration->recordFailedSync('Webhook processing failed: ' . $exception->getMessage());
        }
    }

    private function identifySupplier(): ?Supplier
    {
        $supplierIdentifiers = [
            'supplier_id' => $this->webhookData['supplier_id'] ?? null,
            'source' => $this->webhookData['source'] ?? null,
            'from' => $this->webhookData['from'] ?? null
        ];

        foreach ($supplierIdentifiers as $key => $value) {
            if ($value) {
                $supplier = Supplier::where('id', $value)
                    ->orWhere('name', $value)
                    ->orWhere('company_name', $value)
                    ->first();

                if ($supplier) {
                    return $supplier;
                }
            }
        }

        if (isset($this->webhookData['api_key'])) {
            $integration = SupplierIntegration::whereJsonContains('authentication->api_key', $this->webhookData['api_key'])
                ->with('supplier')
                ->first();

            return $integration?->supplier;
        }

        return null;
    }

    private function validateWebhookSignature(): void
    {
        if (!$this->integration->getWebhookSecret()) {
            return;
        }

        $expectedSignature = $this->webhookData['signature'] ?? $this->webhookData['X-Webhook-Signature'] ?? null;

        if (!$expectedSignature) {
            throw new Exception('Webhook signature missing');
        }

        $payload = json_encode($this->webhookData);
        $computedSignature = hash_hmac('sha256', $payload, $this->integration->getWebhookSecret());

        if (!hash_equals($computedSignature, $expectedSignature)) {
            throw new Exception('Webhook signature validation failed');
        }
    }

    private function processWebhookEvent(DropshipOrderService $dropshipOrderService): void
    {
        switch ($this->eventType) {
            case 'order.status_changed':
            case 'order.updated':
                $this->processOrderStatusUpdate($dropshipOrderService);
                break;

            case 'order.shipped':
                $this->processOrderShipped($dropshipOrderService);
                break;

            case 'order.delivered':
                $this->processOrderDelivered($dropshipOrderService);
                break;

            case 'order.cancelled':
            case 'order.rejected':
                $this->processOrderCancellation($dropshipOrderService);
                break;

            case 'product.updated':
            case 'product.price_changed':
                $this->processProductUpdate();
                break;

            case 'product.stock_changed':
            case 'inventory.updated':
                $this->processStockUpdate();
                break;

            case 'product.discontinued':
                $this->processProductDiscontinued();
                break;

            default:
                Log::warning('Unknown webhook event type', [
                    'event_type' => $this->eventType,
                    'supplier_id' => $this->supplier->id
                ]);
        }
    }

    private function processOrderStatusUpdate(DropshipOrderService $dropshipOrderService): void
    {
        $orderData = $this->webhookData['order'] ?? $this->webhookData['data'] ?? [];
        $externalOrderId = $orderData['external_order_id'] ?? $orderData['order_id'] ?? null;
        $supplierOrderId = $orderData['supplier_order_id'] ?? $orderData['id'] ?? null;

        if (!$externalOrderId && !$supplierOrderId) {
            throw new Exception('No order identifier found in webhook');
        }

        $dropshipOrder = $this->findDropshipOrder($externalOrderId, $supplierOrderId);

        if (!$dropshipOrder) {
            Log::warning('Dropship order not found for webhook', [
                'external_order_id' => $externalOrderId,
                'supplier_order_id' => $supplierOrderId,
                'supplier_id' => $this->supplier->id
            ]);
            return;
        }

        $newStatus = $this->mapSupplierStatusToDropshipStatus($orderData['status'] ?? '');

        if ($newStatus && $newStatus !== $dropshipOrder->status) {
            $response = [
                'status' => $newStatus,
                'supplier_order_id' => $supplierOrderId,
                'webhook_data' => $orderData,
                'updated_at' => $orderData['updated_at'] ?? now()->toISOString()
            ];

            $dropshipOrderService->processSupplierResponse($dropshipOrder, $response);

            Log::info('Dropship order status updated via webhook', [
                'dropship_order_id' => $dropshipOrder->id,
                'old_status' => $dropshipOrder->status,
                'new_status' => $newStatus,
                'supplier_order_id' => $supplierOrderId
            ]);
        }
    }

    private function processOrderShipped(DropshipOrderService $dropshipOrderService): void
    {
        $orderData = $this->webhookData['order'] ?? $this->webhookData['data'] ?? [];
        $trackingData = $this->webhookData['tracking'] ?? $orderData['tracking'] ?? [];

        $externalOrderId = $orderData['external_order_id'] ?? $orderData['order_id'] ?? null;
        $supplierOrderId = $orderData['supplier_order_id'] ?? $orderData['id'] ?? null;
        $trackingNumber = $trackingData['tracking_number'] ?? $orderData['tracking_number'] ?? null;

        if (!$trackingNumber) {
            throw new Exception('No tracking number provided in shipping webhook');
        }

        $dropshipOrder = $this->findDropshipOrder($externalOrderId, $supplierOrderId);

        if (!$dropshipOrder) {
            return;
        }

        $response = [
            'status' => 'shipped',
            'tracking_number' => $trackingNumber,
            'carrier' => $trackingData['carrier'] ?? $orderData['carrier'] ?? null,
            'estimated_delivery' => $orderData['estimated_delivery'] ?? null,
            'shipped_at' => $orderData['shipped_at'] ?? now()->toISOString()
        ];

        $dropshipOrderService->processSupplierResponse($dropshipOrder, $response);
    }

    private function processOrderDelivered(DropshipOrderService $dropshipOrderService): void
    {
        $orderData = $this->webhookData['order'] ?? $this->webhookData['data'] ?? [];
        $externalOrderId = $orderData['external_order_id'] ?? $orderData['order_id'] ?? null;
        $supplierOrderId = $orderData['supplier_order_id'] ?? $orderData['id'] ?? null;

        $dropshipOrder = $this->findDropshipOrder($externalOrderId, $supplierOrderId);

        if (!$dropshipOrder) {
            return;
        }

        $response = [
            'status' => 'delivered',
            'delivered_at' => $orderData['delivered_at'] ?? now()->toISOString()
        ];

        $dropshipOrderService->processSupplierResponse($dropshipOrder, $response);
    }

    private function processOrderCancellation(DropshipOrderService $dropshipOrderService): void
    {
        $orderData = $this->webhookData['order'] ?? $this->webhookData['data'] ?? [];
        $externalOrderId = $orderData['external_order_id'] ?? $orderData['order_id'] ?? null;
        $supplierOrderId = $orderData['supplier_order_id'] ?? $orderData['id'] ?? null;

        $dropshipOrder = $this->findDropshipOrder($externalOrderId, $supplierOrderId);

        if (!$dropshipOrder) {
            return;
        }

        $status = $this->eventType === 'order.rejected' ? 'rejected' : 'cancelled';
        $reason = $orderData['reason'] ?? $orderData['cancellation_reason'] ?? 'Cancelled by supplier';

        $response = [
            'status' => $status,
            'reason' => $reason,
            'cancelled_at' => $orderData['cancelled_at'] ?? now()->toISOString()
        ];

        $dropshipOrderService->processSupplierResponse($dropshipOrder, $response);
    }

    private function processProductUpdate(): void
    {
        $productData = $this->webhookData['product'] ?? $this->webhookData['data'] ?? [];
        $sku = $productData['sku'] ?? $productData['supplier_sku'] ?? null;

        if (!$sku) {
            throw new Exception('No SKU provided in product update webhook');
        }

        $supplierProduct = SupplierProduct::where('supplier_id', $this->supplier->id)
            ->where('supplier_sku', $sku)
            ->first();

        if (!$supplierProduct) {
            Log::warning('Supplier product not found for webhook update', [
                'sku' => $sku,
                'supplier_id' => $this->supplier->id
            ]);
            return;
        }

        $updates = [];

        if (isset($productData['name'])) {
            $updates['name'] = $productData['name'];
        }

        if (isset($productData['description'])) {
            $updates['description'] = $productData['description'];
        }

        if (isset($productData['price'])) {
            $newPrice = (int)round(floatval($productData['price']) * 100);
            if ($newPrice !== $supplierProduct->supplier_price) {
                $updates['supplier_price'] = $newPrice;
            }
        }

        if (!empty($updates)) {
            $updates['sync_status'] = DropshipProductSyncStatuses::SYNCED;
            $updates['last_synced_at'] = now();

            DB::transaction(function () use ($supplierProduct, $updates) {
                $supplierProduct->update($updates);
                $this->updateMappedProduct($supplierProduct, $updates);
            });

            Log::info('Supplier product updated via webhook', [
                'supplier_product_id' => $supplierProduct->id,
                'sku' => $sku,
                'updates' => array_keys($updates)
            ]);
        }
    }

    private function processStockUpdate(): void
    {
        $stockData = $this->webhookData['stock'] ?? $this->webhookData['inventory'] ?? $this->webhookData['data'] ?? [];
        $sku = $stockData['sku'] ?? $stockData['supplier_sku'] ?? null;
        $newStock = $stockData['quantity'] ?? $stockData['stock_quantity'] ?? null;

        if (!$sku || $newStock === null) {
            throw new Exception('Incomplete stock data in webhook');
        }

        $supplierProduct = SupplierProduct::where('supplier_id', $this->supplier->id)
            ->where('supplier_sku', $sku)
            ->first();

        if (!$supplierProduct) {
            return;
        }

        if ((int)$newStock !== $supplierProduct->stock_quantity) {
            DB::transaction(function () use ($supplierProduct, $newStock) {
                $supplierProduct->updateStock((int)$newStock);

                $mapping = ProductSupplierMapping::where('supplier_product_id', $supplierProduct->id)
                    ->where('is_active', true)
                    ->where('auto_update_stock', true)
                    ->first();

                if ($mapping) {
                    $mapping->updateStock((int)$newStock);
                }
            });

            Log::info('Supplier product stock updated via webhook', [
                'supplier_product_id' => $supplierProduct->id,
                'sku' => $sku,
                'old_stock' => $supplierProduct->stock_quantity,
                'new_stock' => (int)$newStock
            ]);
        }
    }

    private function processProductDiscontinued(): void
    {
        $productData = $this->webhookData['product'] ?? $this->webhookData['data'] ?? [];
        $sku = $productData['sku'] ?? $productData['supplier_sku'] ?? null;

        if (!$sku) {
            throw new Exception('No SKU provided in discontinuation webhook');
        }

        $supplierProduct = SupplierProduct::where('supplier_id', $this->supplier->id)
            ->where('supplier_sku', $sku)
            ->first();

        if (!$supplierProduct) {
            return;
        }

        $supplierProduct->update([
            'is_active' => false,
            'sync_status' => DropshipProductSyncStatuses::SUPPLIER_DISCONTINUED,
            'last_synced_at' => now()
        ]);

        Log::warning('Supplier product discontinued via webhook', [
            'supplier_product_id' => $supplierProduct->id,
            'sku' => $sku,
            'supplier_id' => $this->supplier->id
        ]);
    }

    private function findDropshipOrder(?string $externalOrderId, ?string $supplierOrderId): ?DropshipOrder
    {
        $query = DropshipOrder::where('supplier_id', $this->supplier->id);

        if ($externalOrderId) {
            $query->where('id', $externalOrderId);
        } elseif ($supplierOrderId) {
            $query->where('supplier_order_id', $supplierOrderId);
        } else {
            return null;
        }

        return $query->first();
    }

    private function mapSupplierStatusToDropshipStatus(string $supplierStatus): ?string
    {
        $statusMap = [
            'confirmed' => DropshipStatuses::CONFIRMED_BY_SUPPLIER,
            'processing' => DropshipStatuses::PROCESSING,
            'shipped' => DropshipStatuses::SHIPPED_BY_SUPPLIER,
            'delivered' => DropshipStatuses::DELIVERED,
            'cancelled' => DropshipStatuses::CANCELLED,
            'rejected' => DropshipStatuses::REJECTED_BY_SUPPLIER,
            'on_hold' => DropshipStatuses::ON_HOLD,
            'pending' => DropshipStatuses::PENDING
        ];

        return $statusMap[strtolower($supplierStatus)] ?? null;
    }

    private function updateMappedProduct(SupplierProduct $supplierProduct, array $updates): void
    {
        $mapping = ProductSupplierMapping::where('supplier_product_id', $supplierProduct->id)
            ->where('is_active', true)
            ->first();

        if (!$mapping) {
            return;
        }

        if (isset($updates['supplier_price']) && $mapping->canUpdatePrice()) {
            $mapping->updatePricing($updates['supplier_price']);
        }
    }

    private function recordSuccessfulProcessing(): void
    {
        $this->integration->recordSuccessfulSync([
            'webhook_processed' => true,
            'event_type' => $this->eventType,
            'processed_at' => now()->toISOString()
        ]);

        Log::info('Webhook processed successfully', [
            'event_type' => $this->eventType,
            'supplier_id' => $this->supplier->id,
            'integration_id' => $this->integration->id
        ]);
    }

    private function handleWebhookException(Exception $e): void
    {
        Log::error('Exception in ProcessSupplierWebhook job', [
            'event_type' => $this->eventType,
            'supplier_id' => $this->supplier?->id,
            'error' => $e->getMessage(),
            'webhook_data' => $this->webhookData,
            'attempt' => $this->attempts()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    public function backoff(): array
    {
        return [60, 180, 300];
    }
}

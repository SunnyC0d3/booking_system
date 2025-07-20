<?php

namespace App\Services\V1\Dropshipping;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\DropshipOrder;
use App\Models\DropshipOrderItem;
use App\Models\ProductSupplierMapping;
use App\Constants\DropshipStatuses;
use App\Constants\OrderStatuses;
use App\Services\V1\Emails\Email;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DropshipOrderService
{
    protected Email $emailService;

    public function __construct(Email $emailService)
    {
        $this->emailService = $emailService;
    }

    public function createDropshipOrdersFromOrder(Order $order): array
    {
        try {
            $dropshipOrders = [];

            $dropshipItems = $this->identifyDropshipItems($order);

            if (empty($dropshipItems)) {
                return [];
            }

            $groupedItems = $this->groupItemsBySupplier($dropshipItems);

            DB::transaction(function () use ($order, $groupedItems, &$dropshipOrders) {
                foreach ($groupedItems as $supplierId => $items) {
                    $dropshipOrder = $this->createDropshipOrderForSupplier($order, $supplierId, $items);
                    $dropshipOrders[] = $dropshipOrder;
                }
            });

            Log::info('Dropship orders created from order', [
                'order_id' => $order->id,
                'dropship_orders_created' => count($dropshipOrders),
                'total_value' => array_sum(array_column($dropshipOrders, 'total_retail'))
            ]);

            return $dropshipOrders;
        } catch (Exception $e) {
            Log::error('Failed to create dropship orders from order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function processDropshipOrderWorkflow(DropshipOrder $dropshipOrder): void
    {
        try {
            if (!$dropshipOrder->supplier->canAutoFulfill()) {
                Log::info('Dropship order requires manual processing', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'supplier' => $dropshipOrder->supplier->name
                ]);
                return;
            }

            if ($dropshipOrder->isPending()) {
                $this->sendToSupplier($dropshipOrder);
            }
        } catch (Exception $e) {
            Log::error('Failed to process dropship order workflow', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function sendToSupplier(DropshipOrder $dropshipOrder): bool
    {
        try {
            if (!$dropshipOrder->isPending()) {
                throw new Exception('Dropship order is not in pending status');
            }

            $supplier = $dropshipOrder->supplier;

            if (!$supplier->isActive()) {
                throw new Exception('Supplier is not active');
            }

            $integration = $supplier->getActiveIntegration();

            if ($integration && $integration->isAutomated()) {
                $result = $this->sendViaIntegration($dropshipOrder, $integration);
            } else {
                $result = $this->sendManually($dropshipOrder);
            }

            if ($result) {
                $dropshipOrder->markAsSentToSupplier([
                    'sent_at' => now(),
                    'integration_type' => $integration?->integration_type ?? 'manual',
                    'method' => $integration ? 'automated' : 'manual'
                ]);

                Log::info('Dropship order sent to supplier', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'supplier_id' => $supplier->id,
                    'method' => $integration ? 'automated' : 'manual'
                ]);
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to send dropship order to supplier', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function retryFailedOrder(DropshipOrder $dropshipOrder): bool
    {
        try {
            if (!$dropshipOrder->canRetry()) {
                return false;
            }

            $dropshipOrder->incrementRetryCount();

            if ($dropshipOrder->isRejected() || in_array($dropshipOrder->status, [DropshipStatuses::PENDING, DropshipStatuses::REJECTED_BY_SUPPLIER])) {
                $dropshipOrder->updateStatus(DropshipStatuses::PENDING);

                $success = $this->sendToSupplier($dropshipOrder);

                if ($success) {
                    Log::info('Dropship order retry successful', [
                        'dropship_order_id' => $dropshipOrder->id,
                        'retry_count' => $dropshipOrder->retry_count
                    ]);
                } else {
                    Log::warning('Dropship order retry failed', [
                        'dropship_order_id' => $dropshipOrder->id,
                        'retry_count' => $dropshipOrder->retry_count
                    ]);
                }

                return $success;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Failed to retry dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function processSupplierResponse(DropshipOrder $dropshipOrder, array $response): void
    {
        try {
            $status = $response['status'] ?? null;
            $supplierOrderId = $response['supplier_order_id'] ?? null;
            $trackingNumber = $response['tracking_number'] ?? null;
            $estimatedDelivery = $response['estimated_delivery'] ?? null;

            switch ($status) {
                case 'confirmed':
                    if ($supplierOrderId) {
                        $dropshipOrder->markAsConfirmed($supplierOrderId, $response);
                    }
                    break;

                case 'shipped':
                    if ($trackingNumber) {
                        $estimatedDeliveryDate = $estimatedDelivery ? \Carbon\Carbon::parse($estimatedDelivery) : null;
                        $dropshipOrder->markAsShipped(
                            $trackingNumber,
                            $response['carrier'] ?? null,
                            $estimatedDeliveryDate
                        );
                    }
                    break;

                case 'delivered':
                    $dropshipOrder->markAsDelivered();
                    break;

                case 'rejected':
                    $dropshipOrder->markAsRejected($response['reason'] ?? 'Order rejected by supplier');
                    break;

                case 'cancelled':
                    $dropshipOrder->markAsCancelled($response['reason'] ?? 'Order cancelled by supplier');
                    break;
            }

            Log::info('Supplier response processed', [
                'dropship_order_id' => $dropshipOrder->id,
                'status' => $status,
                'supplier_order_id' => $supplierOrderId
            ]);
        } catch (Exception $e) {
            Log::error('Failed to process supplier response', [
                'dropship_order_id' => $dropshipOrder->id,
                'response' => $response,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function calculateOrderProfitability(Order $order): array
    {
        try {
            $profitability = [
                'total_cost' => 0,
                'total_retail' => 0,
                'total_profit' => 0,
                'profit_margin_percentage' => 0,
                'items' => []
            ];

            foreach ($order->orderItems as $orderItem) {
                $product = $orderItem->product;
                $mapping = $this->getPrimaryMapping($product);

                if ($mapping && $mapping->supplierProduct) {
                    $supplierPrice = $mapping->supplierProduct->supplier_price;
                    $retailPrice = $orderItem->price;
                    $quantity = $orderItem->quantity;

                    $itemCost = $supplierPrice * $quantity;
                    $itemRetail = $retailPrice * $quantity;
                    $itemProfit = $itemRetail - $itemCost;

                    $profitability['total_cost'] += $itemCost;
                    $profitability['total_retail'] += $itemRetail;
                    $profitability['total_profit'] += $itemProfit;

                    $profitability['items'][] = [
                        'order_item_id' => $orderItem->id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'supplier_price' => $supplierPrice,
                        'retail_price' => $retailPrice,
                        'total_cost' => $itemCost,
                        'total_retail' => $itemRetail,
                        'profit' => $itemProfit,
                        'profit_margin' => $retailPrice > 0 ? round(($itemProfit / $itemRetail) * 100, 2) : 0,
                        'supplier' => $mapping->supplier->name
                    ];
                }
            }

            $profitability['profit_margin_percentage'] = $profitability['total_retail'] > 0
                ? round(($profitability['total_profit'] / $profitability['total_retail']) * 100, 2)
                : 0;

            return $profitability;
        } catch (Exception $e) {
            Log::error('Failed to calculate order profitability', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateOrderFromDropshipStatus(DropshipOrder $dropshipOrder): void
    {
        try {
            $order = $dropshipOrder->order;
            $allDropshipOrders = $order->dropshipOrders;

            if ($allDropshipOrders->every(fn($ds) => $ds->isDelivered())) {
                $this->updateOrderStatus($order, OrderStatuses::DELIVERED);
            } elseif ($allDropshipOrders->some(fn($ds) => $ds->isShipped())) {
                $this->updateOrderStatus($order, OrderStatuses::SHIPPED);
            } elseif ($allDropshipOrders->some(fn($ds) => $ds->isConfirmed())) {
                $this->updateOrderStatus($order, OrderStatuses::PROCESSING);
            }

            Log::info('Order status updated from dropship status', [
                'order_id' => $order->id,
                'dropship_order_id' => $dropshipOrder->id,
                'new_status' => $order->status->name ?? 'unknown'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to update order from dropship status', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function processOverdueOrders(): int
    {
        try {
            $overdueOrders = DropshipOrder::overdue()->get();
            $processedCount = 0;

            foreach ($overdueOrders as $dropshipOrder) {
                try {
                    $this->handleOverdueOrder($dropshipOrder);
                    $processedCount++;
                } catch (Exception $e) {
                    Log::error('Failed to process overdue dropship order', [
                        'dropship_order_id' => $dropshipOrder->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Processed overdue dropship orders', [
                'total_overdue' => $overdueOrders->count(),
                'processed' => $processedCount
            ]);

            return $processedCount;
        } catch (Exception $e) {
            Log::error('Failed to process overdue orders', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function getSupplierOrderData(DropshipOrder $dropshipOrder): array
    {
        try {
            $orderData = [
                'order_id' => $dropshipOrder->order_id,
                'dropship_order_id' => $dropshipOrder->id,
                'customer' => [
                    'name' => $dropshipOrder->order->user->name ?? 'Guest Customer',
                    'email' => $dropshipOrder->order->user->email ?? null,
                ],
                'shipping_address' => $dropshipOrder->shipping_address,
                'items' => [],
                'total_cost' => $dropshipOrder->getTotalCostInPounds(),
                'notes' => $dropshipOrder->notes,
                'created_at' => $dropshipOrder->created_at->toISOString(),
            ];

            foreach ($dropshipOrder->dropshipOrderItems as $item) {
                $orderData['items'][] = [
                    'supplier_sku' => $item->supplier_sku,
                    'product_name' => $item->getProductName(),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->getSupplierPriceInPounds(),
                    'total_price' => $item->getTotalSupplierCostInPounds(),
                    'product_details' => $item->product_details,
                ];
            }

            return $orderData;
        } catch (Exception $e) {
            Log::error('Failed to get supplier order data', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function identifyDropshipItems(Order $order): array
    {
        $dropshipItems = [];

        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;
            $mapping = $this->getPrimaryMapping($product);

            if ($mapping && $mapping->is_active && $mapping->supplierProduct) {
                $dropshipItems[] = [
                    'order_item' => $orderItem,
                    'product' => $product,
                    'mapping' => $mapping,
                    'supplier_product' => $mapping->supplierProduct,
                    'supplier' => $mapping->supplier
                ];
            }
        }

        return $dropshipItems;
    }

    protected function groupItemsBySupplier(array $dropshipItems): array
    {
        $grouped = [];

        foreach ($dropshipItems as $item) {
            $supplierId = $item['supplier']->id;

            if (!isset($grouped[$supplierId])) {
                $grouped[$supplierId] = [];
            }

            $grouped[$supplierId][] = $item;
        }

        return $grouped;
    }

    protected function createDropshipOrderForSupplier(Order $order, int $supplierId, array $items): DropshipOrder
    {
        $supplier = Supplier::find($supplierId);
        $totalCost = 0;
        $totalRetail = 0;

        foreach ($items as $item) {
            $orderItem = $item['order_item'];
            $supplierProduct = $item['supplier_product'];

            $itemCost = $supplierProduct->supplier_price * $orderItem->quantity;
            $itemRetail = $orderItem->price * $orderItem->quantity;

            $totalCost += $itemCost;
            $totalRetail += $itemRetail;
        }

        $dropshipOrder = DropshipOrder::create([
            'order_id' => $order->id,
            'supplier_id' => $supplierId,
            'status' => DropshipStatuses::PENDING,
            'total_cost' => $totalCost,
            'total_retail' => $totalRetail,
            'profit_margin' => $totalRetail - $totalCost,
            'shipping_address' => $order->shippingAddress?->toArray() ?? [],
            'auto_retry_enabled' => $supplier->auto_fulfill,
        ]);

        foreach ($items as $item) {
            $this->createDropshipOrderItem($dropshipOrder, $item);
        }

        return $dropshipOrder;
    }

    protected function createDropshipOrderItem(DropshipOrder $dropshipOrder, array $itemData): DropshipOrderItem
    {
        $orderItem = $itemData['order_item'];
        $supplierProduct = $itemData['supplier_product'];

        return $dropshipOrder->dropshipOrderItems()->create([
            'order_item_id' => $orderItem->id,
            'supplier_product_id' => $supplierProduct->id,
            'supplier_sku' => $supplierProduct->supplier_sku,
            'quantity' => $orderItem->quantity,
            'supplier_price' => $supplierProduct->supplier_price,
            'retail_price' => $orderItem->price,
            'profit_per_item' => $orderItem->price - $supplierProduct->supplier_price,
            'product_details' => [
                'name' => $supplierProduct->name,
                'description' => $supplierProduct->description,
                'weight' => $supplierProduct->weight,
                'dimensions' => $supplierProduct->getDimensions(),
                'images' => $supplierProduct->images,
                'attributes' => $supplierProduct->attributes,
            ],
            'status' => DropshipStatuses::PENDING,
        ]);
    }

    protected function getPrimaryMapping(Product $product): ?ProductSupplierMapping
    {
        return $product->productMappings()
            ->where('is_primary', true)
            ->where('is_active', true)
            ->with(['supplier', 'supplierProduct'])
            ->first();
    }

    protected function sendViaIntegration(DropshipOrder $dropshipOrder, $integration): bool
    {
        Log::info('Sending dropship order via integration', [
            'dropship_order_id' => $dropshipOrder->id,
            'integration_type' => $integration->integration_type
        ]);

        return true;
    }

    protected function sendManually(DropshipOrder $dropshipOrder): bool
    {
        try {
            $orderData = $this->getSupplierOrderData($dropshipOrder);

            Log::info('Dropship order prepared for manual processing', [
                'dropship_order_id' => $dropshipOrder->id,
                'supplier' => $dropshipOrder->supplier->name,
                'order_data' => $orderData
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Failed to prepare manual dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    protected function updateOrderStatus(Order $order, string $status): void
    {
        $statusId = \App\Models\OrderStatus::where('name', $status)->value('id');
        if ($statusId) {
            $order->update(['status_id' => $statusId]);
        }
    }

    protected function handleOverdueOrder(DropshipOrder $dropshipOrder): void
    {
        if ($dropshipOrder->canRetry()) {
            $this->retryFailedOrder($dropshipOrder);
        } else {
            Log::warning('Dropship order is overdue and cannot be retried', [
                'dropship_order_id' => $dropshipOrder->id,
                'estimated_delivery' => $dropshipOrder->estimated_delivery,
                'days_overdue' => $dropshipOrder->estimated_delivery ? now()->diffInDays($dropshipOrder->estimated_delivery) : null
            ]);
        }
    }
}

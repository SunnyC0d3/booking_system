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

    public function markAsConfirmedWithEmail(DropshipOrder $dropshipOrder, string $supplierOrderId, array $supplierResponse = []): void
    {
        $dropshipOrder->markAsConfirmed($supplierOrderId, $supplierResponse);

        event(new DropshipOrderConfirmed($dropshipOrder));

        Log::info('Dropship order confirmed with email notification', [
            'dropship_order_id' => $dropshipOrder->id,
            'supplier_order_id' => $supplierOrderId
        ]);
    }

    public function markAsShippedWithEmail(DropshipOrder $dropshipOrder, string $trackingNumber, ?string $carrier = null, ?\Carbon\Carbon $estimatedDelivery = null): void
    {
        $dropshipOrder->markAsShipped($trackingNumber, $carrier, $estimatedDelivery);

        event(new DropshipOrderShipped($dropshipOrder));

        Log::info('Dropship order shipped with email notification', [
            'dropship_order_id' => $dropshipOrder->id,
            'tracking_number' => $trackingNumber
        ]);
    }

    public function markAsRejectedWithEmail(DropshipOrder $dropshipOrder, string $reason): void
    {
        // Your existing rejection logic
        $dropshipOrder->markAsRejected($reason);

        // Fire event for email notification
        event(new DropshipOrderRejected($dropshipOrder, $reason));

        Log::info('Dropship order rejected with email notification', [
            'dropship_order_id' => $dropshipOrder->id,
            'reason' => $reason
        ]);
    }

    public function handleOrderDelayWithEmail(DropshipOrder $dropshipOrder, array $delayInfo): void
    {
        // Update the order with delay information
        $dropshipOrder->update([
            'estimated_delivery' => $delayInfo['new_delivery'] ?? null,
            'notes' => ($dropshipOrder->notes ?? '') . "\nDelayed: " . ($delayInfo['reason'] ?? 'Unknown reason')
        ]);

        // Fire event for email notification
        event(new DropshipOrderDelayed($dropshipOrder, $delayInfo));

        Log::info('Dropship order delay handled with email notification', [
            'dropship_order_id' => $dropshipOrder->id,
            'days_delayed' => $delayInfo['days_delayed'] ?? 'unknown'
        ]);
    }

    public function retryFailedOrderWithEmail(DropshipOrder $dropshipOrder, string $reason = null): bool
    {
        if (!$dropshipOrder->canRetry()) {
            return false;
        }

        $attemptNumber = $dropshipOrder->retry_count + 1;

        $dropshipOrder->incrementRetryCount();
        $dropshipOrder->updateStatus(DropshipStatuses::PENDING);

        event(new DropshipOrderRetried($dropshipOrder, $attemptNumber, $reason));

        $success = $this->sendToSupplier($dropshipOrder);

        Log::info('Dropship order retry attempted with email notification', [
            'dropship_order_id' => $dropshipOrder->id,
            'attempt' => $attemptNumber,
            'success' => $success
        ]);

        return $success;
    }

    public function handleIntegrationFailure(Supplier $supplier, \Exception $exception, array $context = []): void
    {
        $integrationData = [
            'type' => $supplier->integration_type,
            'failed_at' => now()->toISOString(),
            'error_message' => $exception->getMessage(),
            'consecutive_failures' => $supplier->consecutive_failures + 1,
            'last_successful_sync' => $supplier->last_successful_sync?->toISOString(),
            'affected_operations' => $context['operations'] ?? [],
            'pending_orders' => DropshipOrder::where('supplier_id', $supplier->id)
                ->whereIn('status', [DropshipStatuses::PENDING])
                ->count(),
        ];

        $supplier->increment('consecutive_failures');
        $supplier->update(['last_failed_sync' => now()]);

        event(new SupplierIntegrationFailed($supplier, $integrationData));

        Log::error('Supplier integration failed with email notification', [
            'supplier_id' => $supplier->id,
            'error' => $exception->getMessage(),
            'context' => $context
        ]);
    }

    public function checkSupplierPerformance(Supplier $supplier): void
    {
        $performanceData = $this->calculateSupplierPerformance($supplier);

        $alerts = [];

        if ($performanceData['success_rate'] < 90) {
            $alerts[] = 'Success rate below 90%';
        }

        if ($performanceData['avg_fulfillment_time'] > 5) {
            $alerts[] = 'Average fulfillment time over 5 days';
        }

        if ($performanceData['failed_orders'] > 10) {
            $alerts[] = 'High number of failed orders';
        }

        if (!empty($alerts)) {
            $performanceData['metric'] = implode(', ', $alerts);
            $performanceData['recommendations'] = $this->getPerformanceRecommendations($performanceData);
            $performanceData['action_required'] = $performanceData['success_rate'] < 80;

            event(new SupplierPerformanceAlert($supplier, $performanceData));

            Log::warning('Supplier performance alert triggered', [
                'supplier_id' => $supplier->id,
                'alerts' => $alerts,
                'performance' => $performanceData
            ]);
        }
    }

    private function calculateSupplierPerformance(Supplier $supplier): array
    {
        $orders = $supplier->dropshipOrders()
            ->where('created_at', '>=', now()->subMonth())
            ->get();

        $totalOrders = $orders->count();
        $successfulOrders = $orders->where('status', DropshipStatuses::DELIVERED)->count();
        $failedOrders = $orders->whereIn('status', [
            DropshipStatuses::REJECTED_BY_SUPPLIER,
            DropshipStatuses::CANCELLED
        ])->count();

        $avgFulfillmentTime = $orders
            ->whereNotNull('shipped_by_supplier_at')
            ->whereNotNull('sent_to_supplier_at')
            ->avg(function ($order) {
                return $order->sent_to_supplier_at->diffInDays($order->shipped_by_supplier_at);
            });

        return [
            'success_rate' => $totalOrders > 0 ? round(($successfulOrders / $totalOrders) * 100, 2) : 0,
            'avg_fulfillment_time' => round($avgFulfillmentTime ?? 0, 1),
            'orders_this_month' => $totalOrders,
            'failed_orders' => $failedOrders,
            'complaints' => 0,
            'current_value' => $totalOrders > 0 ? round(($successfulOrders / $totalOrders) * 100, 2) : 0,
            'threshold' => 90,
            'detected_at' => now()->toISOString(),
            'recent_issues' => $this->getRecentIssues($supplier),
        ];
    }

    private function getPerformanceRecommendations(array $performanceData): array
    {
        $recommendations = [];

        if ($performanceData['success_rate'] < 90) {
            $recommendations[] = 'Review order processing workflow with supplier';
            $recommendations[] = 'Implement quality control measures';
        }

        if ($performanceData['avg_fulfillment_time'] > 5) {
            $recommendations[] = 'Discuss faster fulfillment options';
            $recommendations[] = 'Consider backup suppliers for urgent orders';
        }

        if ($performanceData['failed_orders'] > 10) {
            $recommendations[] = 'Analyze root causes of order failures';
            $recommendations[] = 'Implement pre-order inventory verification';
        }

        return $recommendations;
    }

    private function getRecentIssues(Supplier $supplier): array
    {
        return $supplier->dropshipOrders()
            ->whereIn('status', [
                DropshipStatuses::REJECTED_BY_SUPPLIER,
                DropshipStatuses::CANCELLED
            ])
            ->where('updated_at', '>=', now()->subWeek())
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'type' => ucfirst(str_replace('_', ' ', $order->status)),
                    'description' => "Order #{$order->order_id} - {$order->notes}",
                    'date' => $order->updated_at->format('M j, Y'),
                ];
            })
            ->toArray();
    }
}

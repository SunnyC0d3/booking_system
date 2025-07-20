<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductSupplierMapping;
use App\Services\V1\Dropshipping\DropshipOrderService;
use App\Constants\OrderStatuses;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Exception;

class CreateDropshipOrdersFromOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'dropshipping';
    public $delay = 30;

    private DropshipOrderService $dropshipOrderService;

    public function __construct(DropshipOrderService $dropshipOrderService)
    {
        $this->dropshipOrderService = $dropshipOrderService;
    }

    public function handle(OrderCreated $event): void
    {
        $order = $event->order;

        try {
            if (!$this->shouldCreateDropshipOrders($order)) {
                Log::debug('Order does not require dropship processing', [
                    'order_id' => $order->id,
                    'reason' => 'No dropship items or order not eligible'
                ]);
                return;
            }

            Log::info('Processing order for dropship order creation', [
                'order_id' => $order->id,
                'customer_id' => $order->user_id,
                'total_items' => $order->orderItems->count()
            ]);

            $dropshipOrders = $this->dropshipOrderService->createDropshipOrdersFromOrder($order);

            if (empty($dropshipOrders)) {
                Log::info('No dropship orders created for order', [
                    'order_id' => $order->id,
                    'reason' => 'No eligible dropship items found'
                ]);
                return;
            }

            Log::info('Dropship orders created successfully', [
                'order_id' => $order->id,
                'dropship_orders_created' => count($dropshipOrders),
                'suppliers_involved' => array_unique(array_column($dropshipOrders, 'supplier_id'))
            ]);

            $this->updateOrderStatus($order);
            $this->processDropshipWorkflow($dropshipOrders);

        } catch (Exception $e) {
            Log::error('Failed to create dropship orders from order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->failed($e);
        }
    }

    public function failed(Exception $exception): void
    {
        Log::critical('CreateDropshipOrdersFromOrder listener failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    private function shouldCreateDropshipOrders(Order $order): bool
    {
        if (!$order->isPaid()) {
            return false;
        }

        if ($order->status->name === OrderStatuses::CANCELLED) {
            return false;
        }

        if ($order->dropshipOrders()->exists()) {
            return false;
        }

        return $this->hasDropshipItems($order);
    }

    private function hasDropshipItems(Order $order): bool
    {
        foreach ($order->orderItems as $orderItem) {
            $product = $orderItem->product;

            if ($product->is_dropship && $this->hasActiveSupplierMapping($product)) {
                return true;
            }
        }

        return false;
    }

    private function hasActiveSupplierMapping(Product $product): bool
    {
        return ProductSupplierMapping::where('product_id', $product->id)
            ->where('is_active', true)
            ->whereHas('supplier', function ($query) {
                $query->where('status', 'active');
            })
            ->whereHas('supplierProduct', function ($query) {
                $query->where('is_active', true)
                    ->where('stock_quantity', '>', 0);
            })
            ->exists();
    }

    private function updateOrderStatus(Order $order): void
    {
        try {
            $statusId = \App\Models\OrderStatus::where('name', OrderStatuses::PROCESSING)->value('id');

            if ($statusId && $order->status->name !== OrderStatuses::PROCESSING) {
                $order->update(['status_id' => $statusId]);

                Log::info('Order status updated for dropship processing', [
                    'order_id' => $order->id,
                    'new_status' => OrderStatuses::PROCESSING
                ]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to update order status', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function processDropshipWorkflow(array $dropshipOrders): void
    {
        foreach ($dropshipOrders as $dropshipOrder) {
            try {
                $this->dropshipOrderService->processDropshipOrderWorkflow($dropshipOrder);

                Log::info('Dropship order workflow initiated', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'supplier_id' => $dropshipOrder->supplier_id,
                    'auto_fulfill' => $dropshipOrder->supplier->canAutoFulfill()
                ]);

            } catch (Exception $e) {
                Log::error('Failed to process dropship order workflow', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function viaQueue(): string
    {
        return 'dropshipping';
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function backoff(): array
    {
        return [30, 60, 120];
    }
}

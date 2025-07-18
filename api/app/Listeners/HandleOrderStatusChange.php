<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\V1\Orders\OrderFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleOrderStatusChange implements ShouldQueue
{
    use InteractsWithQueue;

    protected OrderFulfillmentService $fulfillmentService;

    public function __construct(OrderFulfillmentService $fulfillmentService)
    {
        $this->fulfillmentService = $fulfillmentService;
    }

    public function handle(OrderStatusChanged $event): void
    {
        try {
            $this->fulfillmentService->processOrderStatusChange(
                $event->order,
                $event->newStatus,
                $event->oldStatus
            );
        } catch (\Exception $e) {
            Log::error('Failed to handle order status change', [
                'order_id' => $event->order->id,
                'new_status' => $event->newStatus,
                'old_status' => $event->oldStatus,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

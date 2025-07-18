<?php

namespace App\Listeners;

use App\Events\ShipmentStatusChanged;
use App\Services\V1\Orders\OrderFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleShipmentStatusChange implements ShouldQueue
{
    use InteractsWithQueue;

    protected OrderFulfillmentService $fulfillmentService;

    public function __construct(OrderFulfillmentService $fulfillmentService)
    {
        $this->fulfillmentService = $fulfillmentService;
    }

    public function handle(ShipmentStatusChanged $event): void
    {
        try {
            $this->fulfillmentService->processShipmentStatusChange(
                $event->shipment,
                $event->newStatus,
                $event->oldStatus
            );
        } catch (\Exception $e) {
            Log::error('Failed to handle shipment status change', [
                'shipment_id' => $event->shipment->id,
                'order_id' => $event->shipment->order_id,
                'new_status' => $event->newStatus,
                'old_status' => $event->oldStatus,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

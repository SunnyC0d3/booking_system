<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Events\ShipmentStatusChanged;
use Illuminate\Support\Facades\Log;

class ShipmentObserver
{
    public function updated(Shipment $shipment): void
    {
        if ($shipment->isDirty('status')) {
            $oldStatus = $shipment->getOriginal('status');
            $newStatus = $shipment->status;

            if ($oldStatus !== $newStatus) {
                Log::info('Shipment status changed, firing event', [
                    'shipment_id' => $shipment->id,
                    'order_id' => $shipment->order_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);

                ShipmentStatusChanged::dispatch($shipment, $newStatus, $oldStatus);
            }
        }
    }
}

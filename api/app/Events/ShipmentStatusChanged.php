<?php

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Shipment $shipment;
    public string $newStatus;
    public ?string $oldStatus;

    public function __construct(Shipment $shipment, string $newStatus, ?string $oldStatus = null)
    {
        $this->shipment = $shipment;
        $this->newStatus = $newStatus;
        $this->oldStatus = $oldStatus;
    }
}

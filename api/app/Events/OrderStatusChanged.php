<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;
    public string $newStatus;
    public ?string $oldStatus;

    public function __construct(Order $order, string $newStatus, ?string $oldStatus = null)
    {
        $this->order = $order;
        $this->newStatus = $newStatus;
        $this->oldStatus = $oldStatus;
    }
}

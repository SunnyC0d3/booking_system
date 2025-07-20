<?php

namespace App\Events;

use App\Models\DropshipOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DropshipOrderStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public DropshipOrder $dropshipOrder;
    public string $oldStatus;
    public string $newStatus;

    public function __construct(DropshipOrder $dropshipOrder, string $oldStatus, string $newStatus)
    {
        $this->dropshipOrder = $dropshipOrder;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }
}

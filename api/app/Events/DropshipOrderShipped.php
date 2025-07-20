<?php

namespace App\Events;

use App\Models\DropshipOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DropshipOrderShipped
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DropshipOrder $dropshipOrder
    ) {
    }
}

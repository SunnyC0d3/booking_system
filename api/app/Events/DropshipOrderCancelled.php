<?php

namespace App\Events;

use App\Models\DropshipOrder;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DropshipOrderCancelled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DropshipOrder $dropshipOrder,
        public ?string $reason = null
    ) {
    }
}

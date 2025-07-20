<?php

namespace App\Events;

use App\Models\Supplier;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventorySyncFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Supplier $supplier,
        public array $syncData
    ) {
    }
}

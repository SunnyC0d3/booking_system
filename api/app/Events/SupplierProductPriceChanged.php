<?php

namespace App\Events;

use App\Models\SupplierProduct;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierProductPriceChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SupplierProduct $supplierProduct;
    public int $oldPrice;
    public int $newPrice;

    public function __construct(SupplierProduct $supplierProduct, int $oldPrice, int $newPrice)
    {
        $this->supplierProduct = $supplierProduct;
        $this->oldPrice = $oldPrice;
        $this->newPrice = $newPrice;
    }
}

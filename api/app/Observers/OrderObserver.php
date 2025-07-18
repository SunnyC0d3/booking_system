<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Events\OrderStatusChanged;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    public function updated(Order $order): void
    {
        if ($order->isDirty('status_id')) {
            $oldStatusId = $order->getOriginal('status_id');
            $newStatusId = $order->status_id;

            $oldStatusName = $oldStatusId ? OrderStatus::find($oldStatusId)?->name : null;
            $newStatusName = OrderStatus::find($newStatusId)?->name;

            if ($newStatusName && $oldStatusName !== $newStatusName) {
                Log::info('Order status changed, firing event', [
                    'order_id' => $order->id,
                    'old_status' => $oldStatusName,
                    'new_status' => $newStatusName,
                ]);

                OrderStatusChanged::dispatch($order, $newStatusName, $oldStatusName);
            }
        }
    }
}

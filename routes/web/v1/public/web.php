<?php

use App\Models\Order;
use Illuminate\Support\Facades\Route;

// Payment Page Demo

Route::get('/demo-checkout/{orderId}/pay', function ($orderId) {
    $order = Order::with('orderItems.product')->findOrFail($orderId);

    if($order) {
        return view('demo.demo-checkout', [
            'orderId' => $order->id,
            'orderItems' => json_encode($order->orderItems),
        ]);
    }

    return 'Order failed to load.';
})->name('checkout.pay');

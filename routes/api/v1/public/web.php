<?php

use Illuminate\Support\Facades\Route;

// Payment Page Demo

Route::get('/demo-orders/{orderId}/pay', function ($orderId) {
    return view('demo-order', [
        'orderId' => $orderId
    ]);
})->name('order.pay');

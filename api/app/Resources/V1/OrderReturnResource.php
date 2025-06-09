<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderReturnResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'status' => $this->status->name,
            'created_at' => $this->created_at,
            'order_item' => [
                'id' => $this->orderItem->id,
                'product' => [
                    'name' => $this->orderItem->product->name,
                ],
                'order' => [
                    'id' => $this->orderItem->order->id,
                    'user' => [
                        'email' => $this->orderItem->order->user->email,
                    ],
                    'payments' => $this->orderItem->order->payments->map(function ($payment) {
                        return [
                            'gateway' => $payment->paymentMethod->name,
                            'amount' => $payment->amount,
                            'status' => $payment->status,
                        ];
                    }),
                ]
            ]
        ];
    }
}

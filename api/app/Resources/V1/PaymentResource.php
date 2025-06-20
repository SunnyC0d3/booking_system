<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'gateway' => $this->whenLoaded('paymentMethod', function() {
                return $this->paymentMethod->name;
            }),
            'amount' => $this->amount,
            'amount_formatted' => $this->formatPrice($this->amount),
            'method' => $this->method ?? $this->whenLoaded('paymentMethod', function() {
                    return $this->paymentMethod->name;
                }),
            'status' => $this->status,
            'transaction_reference' => $this->transaction_reference,
            'processed_at' => $this->processed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'user' => new UserResource($this->whenLoaded('user')),
            'order' => new OrderResource($this->whenLoaded('order')),
            'payment_method' => $this->whenLoaded('paymentMethod', function() {
                return [
                    'id' => $this->paymentMethod->id,
                    'name' => $this->paymentMethod->name
                ];
            }),
        ];
    }

    private function formatPrice(int $priceInPennies): string
    {
        return '£' . number_format($priceInPennies / 100, 2);
    }
}

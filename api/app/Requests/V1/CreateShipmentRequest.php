<?php

namespace App\Requests\V1;

use App\Constants\ShippingStatuses;

class CreateShipmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],
            'shipping_method_id' => [
                'required',
                'integer',
                'exists:shipping_methods,id',
            ],
            'carrier' => [
                'required',
                'string',
                'max:100',
            ],
            'service_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'shipping_cost' => [
                'required',
                'numeric',
                'min:0',
            ],
            'estimated_delivery' => [
                'nullable',
                'date',
                'after:today',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'auto_purchase_label' => [
                'nullable',
                'boolean',
            ],
            'send_notification' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required.',
            'order_id.exists' => 'The specified order does not exist.',
            'shipping_method_id.required' => 'Shipping method is required.',
            'shipping_method_id.exists' => 'The specified shipping method does not exist.',
            'carrier.required' => 'Carrier name is required.',
            'carrier.max' => 'Carrier name cannot exceed 100 characters.',
            'shipping_cost.required' => 'Shipping cost is required.',
            'shipping_cost.min' => 'Shipping cost cannot be negative.',
            'estimated_delivery.after' => 'Estimated delivery must be in the future.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Convert shipping cost to pennies
        if ($this->has('shipping_cost')) {
            $this->merge([
                'shipping_cost' => (int) round($this->input('shipping_cost') * 100)
            ]);
        }

        // Set defaults
        $this->merge([
            'auto_purchase_label' => $this->boolean('auto_purchase_label', false),
            'send_notification' => $this->boolean('send_notification', true),
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $orderId = $this->input('order_id');

            if ($orderId) {
                // Check if order already has active shipments
                $existingShipment = \App\Models\Shipment::where('order_id', $orderId)
                    ->whereNotIn('status', [
                        ShippingStatuses::CANCELLED,
                        ShippingStatuses::FAILED,
                        ShippingStatuses::DELIVERED
                    ])
                    ->exists();

                if ($existingShipment) {
                    $validator->errors()->add('order_id', 'Order already has an active shipment.');
                }

                // Check if order can be shipped
                $order = \App\Models\Order::find($orderId);
                if ($order && !$order->canShip()) {
                    $validator->errors()->add('order_id', 'Order cannot be shipped in its current state.');
                }
            }
        });
    }
}

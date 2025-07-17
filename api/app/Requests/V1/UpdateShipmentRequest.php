<?php

namespace App\Requests\V1;

use App\Constants\ShippingStatuses;

class UpdateShipmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => [
                'sometimes',
                'nullable',
                'string',
                'min:8',
                'max:50',
                'regex:/^[A-Z0-9\-]+$/',
            ],
            'carrier' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],
            'service_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', ShippingStatuses::all()),
            ],
            'shipping_cost' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],
            'label_url' => [
                'sometimes',
                'nullable',
                'url',
                'max:500',
            ],
            'tracking_url' => [
                'sometimes',
                'nullable',
                'url',
                'max:500',
            ],
            'shipped_at' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'delivered_at' => [
                'sometimes',
                'nullable',
                'date',
                'after_or_equal:shipped_at',
            ],
            'estimated_delivery' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'notes' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'carrier_data' => [
                'sometimes',
                'nullable',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'tracking_number.min' => 'Tracking number must be at least 8 characters.',
            'tracking_number.max' => 'Tracking number cannot exceed 50 characters.',
            'tracking_number.regex' => 'Invalid tracking number format. Only letters, numbers, and hyphens are allowed.',
            'carrier.required' => 'Carrier name is required.',
            'carrier.max' => 'Carrier name cannot exceed 100 characters.',
            'status.required' => 'Shipment status is required.',
            'status.in' => 'Invalid shipment status.',
            'shipping_cost.required' => 'Shipping cost is required.',
            'shipping_cost.min' => 'Shipping cost cannot be negative.',
            'label_url.url' => 'Label URL must be a valid URL.',
            'label_url.max' => 'Label URL cannot exceed 500 characters.',
            'tracking_url.url' => 'Tracking URL must be a valid URL.',
            'tracking_url.max' => 'Tracking URL cannot exceed 500 characters.',
            'delivered_at.after_or_equal' => 'Delivery date must be after or equal to ship date.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Convert shipping cost to pennies if provided
        if ($this->has('shipping_cost')) {
            $this->merge([
                'shipping_cost' => (int) round($this->input('shipping_cost') * 100)
            ]);
        }

        // Normalize tracking number
        if ($this->has('tracking_number') && $this->input('tracking_number')) {
            $this->merge([
                'tracking_number' => strtoupper(trim($this->input('tracking_number')))
            ]);
        }

        // Handle status-specific logic
        if ($this->has('status')) {
            $status = $this->input('status');

            // Auto-set shipped_at when status changes to shipped
            if ($status === ShippingStatuses::SHIPPED && !$this->has('shipped_at')) {
                $this->merge(['shipped_at' => now()]);
            }

            // Auto-set delivered_at when status changes to delivered
            if ($status === ShippingStatuses::DELIVERED && !$this->has('delivered_at')) {
                $this->merge(['delivered_at' => now()]);
            }
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $shipment = $this->route('shipment');

            if ($shipment) {
                // Prevent updates to delivered shipments
                if ($shipment->isDelivered()) {
                    $validator->errors()->add('status', 'Cannot update delivered shipments.');
                }

                // Prevent updates to cancelled shipments
                if ($shipment->isCancelled()) {
                    $validator->errors()->add('status', 'Cannot update cancelled shipments.');
                }

                // Validate status transitions
                $newStatus = $this->input('status');
                if ($newStatus && !$this->isValidStatusTransition($shipment->status, $newStatus)) {
                    $validator->errors()->add('status', 'Invalid status transition from ' . $shipment->status . ' to ' . $newStatus . '.');
                }
            }

            // Validate tracking number uniqueness if provided
            if ($this->has('tracking_number') && $this->input('tracking_number')) {
                $trackingNumber = $this->input('tracking_number');
                $shipmentId = $shipment ? $shipment->id : null;

                $exists = \App\Models\Shipment::where('tracking_number', $trackingNumber)
                    ->when($shipmentId, function ($query) use ($shipmentId) {
                        return $query->where('id', '!=', $shipmentId);
                    })
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('tracking_number', 'This tracking number is already in use.');
                }
            }
        });
    }

    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = [
            ShippingStatuses::PENDING => [
                ShippingStatuses::PROCESSING,
                ShippingStatuses::READY_TO_SHIP,
                ShippingStatuses::CANCELLED,
                ShippingStatuses::FAILED,
            ],
            ShippingStatuses::PROCESSING => [
                ShippingStatuses::READY_TO_SHIP,
                ShippingStatuses::SHIPPED,
                ShippingStatuses::CANCELLED,
                ShippingStatuses::FAILED,
            ],
            ShippingStatuses::READY_TO_SHIP => [
                ShippingStatuses::SHIPPED,
                ShippingStatuses::CANCELLED,
                ShippingStatuses::FAILED,
            ],
            ShippingStatuses::SHIPPED => [
                ShippingStatuses::IN_TRANSIT,
                ShippingStatuses::OUT_FOR_DELIVERY,
                ShippingStatuses::DELIVERED,
                ShippingStatuses::EXCEPTION,
                ShippingStatuses::RETURNED,
            ],
            ShippingStatuses::IN_TRANSIT => [
                ShippingStatuses::OUT_FOR_DELIVERY,
                ShippingStatuses::DELIVERED,
                ShippingStatuses::EXCEPTION,
                ShippingStatuses::RETURNED,
            ],
            ShippingStatuses::OUT_FOR_DELIVERY => [
                ShippingStatuses::DELIVERED,
                ShippingStatuses::EXCEPTION,
                ShippingStatuses::FAILED,
            ],
            ShippingStatuses::EXCEPTION => [
                ShippingStatuses::IN_TRANSIT,
                ShippingStatuses::OUT_FOR_DELIVERY,
                ShippingStatuses::DELIVERED,
                ShippingStatuses::RETURNED,
                ShippingStatuses::FAILED,
            ],
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
}

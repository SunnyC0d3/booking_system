<?php

namespace App\Requests\V1;

use App\Constants\DropshipStatuses;
use Illuminate\Validation\Rule;

class UpdateDropshipOrderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('edit_dropship_orders');
    }

    public function rules(): array
    {
        return [
            'supplier_order_id' => 'sometimes|nullable|string|max:255',
            'status' => ['sometimes', 'required', 'string', Rule::in(DropshipStatuses::all())],
            'total_cost' => 'sometimes|required|integer|min:1',
            'total_retail' => 'sometimes|required|integer|min:1',
            'profit_margin' => 'sometimes|nullable|integer',
            'shipping_address' => 'sometimes|required|array',
            'shipping_address.name' => 'required_with:shipping_address|string|max:255',
            'shipping_address.line1' => 'required_with:shipping_address|string|max:255',
            'shipping_address.line2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required_with:shipping_address|string|max:255',
            'shipping_address.county' => 'nullable|string|max:255',
            'shipping_address.postcode' => 'required_with:shipping_address|string|max:20',
            'shipping_address.country' => 'required_with:shipping_address|string|size:2',
            'shipping_address.phone' => 'nullable|string|max:50',
            'tracking_number' => 'sometimes|nullable|string|max:255',
            'carrier' => 'sometimes|nullable|string|max:255',
            'estimated_delivery' => 'sometimes|nullable|date|after:today',
            'supplier_response' => 'sometimes|nullable|array',
            'notes' => 'sometimes|nullable|string|max:2000',
            'supplier_notes' => 'sometimes|nullable|string|max:2000',
            'auto_retry_enabled' => 'sometimes|boolean',
            'webhook_data' => 'sometimes|nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid dropship order status.',
            'total_cost.required' => 'Total cost is required.',
            'total_cost.min' => 'Total cost must be at least 1 penny.',
            'total_retail.required' => 'Total retail price is required.',
            'total_retail.min' => 'Total retail price must be at least 1 penny.',
            'shipping_address.required' => 'Shipping address is required.',
            'shipping_address.name.required_with' => 'Recipient name is required.',
            'shipping_address.line1.required_with' => 'Address line 1 is required.',
            'shipping_address.city.required_with' => 'City is required.',
            'shipping_address.postcode.required_with' => 'Postcode is required.',
            'shipping_address.country.required_with' => 'Country is required.',
            'shipping_address.country.size' => 'Country code must be exactly 2 characters.',
            'estimated_delivery.date' => 'Estimated delivery must be a valid date.',
            'estimated_delivery.after' => 'Estimated delivery must be in the future.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('auto_retry_enabled')) {
            $this->merge(['auto_retry_enabled' => $this->boolean('auto_retry_enabled')]);
        }

        if ($this->has('total_cost') && is_numeric($this->input('total_cost'))) {
            $this->merge([
                'total_cost' => (int) round($this->input('total_cost') * 100)
            ]);
        }

        if ($this->has('total_retail') && is_numeric($this->input('total_retail'))) {
            $this->merge([
                'total_retail' => (int) round($this->input('total_retail') * 100)
            ]);
        }

        if ($this->has('profit_margin') && is_numeric($this->input('profit_margin'))) {
            $this->merge([
                'profit_margin' => (int) round($this->input('profit_margin') * 100)
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('total_cost') && $this->has('total_retail')) {
                if ($this->input('total_retail') <= $this->input('total_cost')) {
                    $validator->errors()->add('total_retail', 'Total retail price must be greater than total cost.');
                }
            }

            $dropshipOrder = $this->route('dropshipOrder');
            if ($this->has('status') && $dropshipOrder) {
                $currentStatus = $dropshipOrder->status;
                $newStatus = $this->input('status');

                if ($currentStatus === DropshipStatuses::DELIVERED && $newStatus !== DropshipStatuses::DELIVERED) {
                    $validator->errors()->add('status', 'Cannot change status of delivered dropship order.');
                }

                if ($currentStatus === DropshipStatuses::CANCELLED && $newStatus !== DropshipStatuses::CANCELLED) {
                    $validator->errors()->add('status', 'Cannot change status of cancelled dropship order.');
                }
            }
        });
    }
}

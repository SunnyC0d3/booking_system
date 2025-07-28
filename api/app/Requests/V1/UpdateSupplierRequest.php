<?php

namespace App\Requests\V1;

use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('edit_suppliers');
    }

    public function rules(): array
    {
        $supplierId = $this->route('supplier')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplierId)],
            'company_name' => 'sometimes|nullable|string|max:255',
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('suppliers', 'email')->ignore($supplierId)],
            'phone' => 'sometimes|nullable|string|max:50',
            'address' => 'sometimes|nullable|string|max:1000',
            'country' => 'sometimes|required|string|size:2',
            'contact_person' => 'sometimes|nullable|string|max:255',
            'status' => ['sometimes', 'required', 'string', Rule::in(SupplierStatuses::all())],
            'integration_type' => ['sometimes', 'required', 'string', Rule::in(SupplierIntegrationTypes::all())],
            'commission_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'processing_time_days' => 'sometimes|required|integer|min:1|max:30',
            'shipping_methods' => 'sometimes|nullable|array',
            'shipping_methods.*' => 'string|max:100',
            'integration_config' => 'sometimes|nullable|array',
            'api_endpoint' => 'sometimes|nullable|url|max:500',
            'api_key' => 'sometimes|nullable|string|max:500',
            'webhook_url' => 'sometimes|nullable|url|max:500',
            'notes' => 'sometimes|nullable|string|max:2000',
            'auto_fulfill' => 'sometimes|boolean',
            'stock_sync_enabled' => 'sometimes|boolean',
            'price_sync_enabled' => 'sometimes|boolean',
            'minimum_order_value' => 'sometimes|nullable|numeric|min:0',
            'maximum_order_value' => 'sometimes|nullable|numeric|min:0',
            'supported_countries' => 'sometimes|nullable|array',
            'supported_countries.*' => 'string|size:2',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Supplier name is required.',
            'name.unique' => 'A supplier with this name already exists.',
            'email.required' => 'Supplier email is required.',
            'email.unique' => 'A supplier with this email already exists.',
            'country.required' => 'Country code is required.',
            'country.size' => 'Country code must be exactly 2 characters.',
            'status.required' => 'Supplier status is required.',
            'status.in' => 'Invalid supplier status.',
            'integration_type.required' => 'Integration type is required.',
            'integration_type.in' => 'Invalid integration type.',
            'processing_time_days.required' => 'Processing time is required.',
            'processing_time_days.min' => 'Processing time must be at least 1 day.',
            'processing_time_days.max' => 'Processing time cannot exceed 30 days.',
            'commission_rate.min' => 'Commission rate cannot be negative.',
            'commission_rate.max' => 'Commission rate cannot exceed 100%.',
            'api_endpoint.url' => 'API endpoint must be a valid URL.',
            'webhook_url.url' => 'Webhook URL must be a valid URL.',
            'minimum_order_value.min' => 'Minimum order value cannot be negative.',
            'maximum_order_value.min' => 'Maximum order value cannot be negative.',
            'supported_countries.*.size' => 'Each country code must be exactly 2 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('auto_fulfill')) {
            $this->merge(['auto_fulfill' => $this->boolean('auto_fulfill')]);
        }
        if ($this->has('stock_sync_enabled')) {
            $this->merge(['stock_sync_enabled' => $this->boolean('stock_sync_enabled')]);
        }
        if ($this->has('price_sync_enabled')) {
            $this->merge(['price_sync_enabled' => $this->boolean('price_sync_enabled')]);
        }
    }
}

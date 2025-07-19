<?php

namespace App\Requests\V1;

use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('view_supplier_products');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'sync_status' => ['sometimes', 'string', Rule::in(DropshipProductSyncStatuses::all())],
            'is_active' => 'sometimes|boolean',
            'is_mapped' => 'sometimes|boolean',
            'search' => 'sometimes|string|max:255',
            'stock_status' => 'sometimes|string|in:in_stock,out_of_stock,low_stock',
            'category' => 'sometimes|string|max:255',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'min_stock' => 'sometimes|integer|min:0',
            'max_stock' => 'sometimes|integer|min:0',
            'last_synced_from' => 'sometimes|date',
            'last_synced_to' => 'sometimes|date|after_or_equal:last_synced_from',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:name,supplier_sku,supplier_price,retail_price,stock_quantity,last_synced_at,created_at',
            'sort_direction' => 'sometimes|string|in:asc,desc',
            'include_images' => 'sometimes|boolean',
            'include_attributes' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'sync_status.in' => 'Invalid sync status.',
            'stock_status.in' => 'Invalid stock status. Must be one of: in_stock, out_of_stock, low_stock.',
            'min_price.min' => 'Minimum price cannot be negative.',
            'max_price.min' => 'Maximum price cannot be negative.',
            'min_stock.min' => 'Minimum stock cannot be negative.',
            'max_stock.min' => 'Maximum stock cannot be negative.',
            'last_synced_from.date' => 'Last synced from date must be a valid date.',
            'last_synced_to.date' => 'Last synced to date must be a valid date.',
            'last_synced_to.after_or_equal' => 'Last synced to date must be after or equal to from date.',
            'per_page.max' => 'Cannot retrieve more than 100 products per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        if ($this->has('is_mapped')) {
            $this->merge(['is_mapped' => $this->boolean('is_mapped')]);
        }

        if ($this->has('include_images')) {
            $this->merge(['include_images' => $this->boolean('include_images')]);
        }

        if ($this->has('include_attributes')) {
            $this->merge(['include_attributes' => $this->boolean('include_attributes')]);
        }

        if ($this->has('min_price') && is_numeric($this->input('min_price'))) {
            $this->merge([
                'min_price' => (int) round($this->input('min_price') * 100)
            ]);
        }

        if ($this->has('max_price') && is_numeric($this->input('max_price'))) {
            $this->merge([
                'max_price' => (int) round($this->input('max_price') * 100)
            ]);
        }
    }
}

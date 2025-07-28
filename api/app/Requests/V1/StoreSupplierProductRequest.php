<?php

namespace App\Requests\V1;

use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Validation\Rule;

class StoreSupplierProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_supplier_products');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'product_id' => 'nullable|exists:products,id',
            'supplier_sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('supplier_products')->where(function ($query) {
                    return $query->where('supplier_id', $this->input('supplier_id'));
                })
            ],
            'supplier_product_id' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'supplier_price' => 'required|integer|min:1',
            'retail_price' => 'nullable|integer|min:1',
            'stock_quantity' => 'required|integer|min:0',
            'weight' => 'nullable|numeric|min:0|max:999999.99',
            'length' => 'nullable|numeric|min:0|max:999999.99',
            'width' => 'nullable|numeric|min:0|max:999999.99',
            'height' => 'nullable|numeric|min:0|max:999999.99',
            'sync_status' => ['required', 'string', Rule::in(DropshipProductSyncStatuses::all())],
            'images' => 'nullable|array',
            'images.*' => 'string|max:500',
            'attributes' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:255',
            'is_active' => 'boolean',
            'is_mapped' => 'boolean',
            'minimum_order_quantity' => 'nullable|integer|min:1|max:1000',
            'processing_time_days' => 'nullable|integer|min:1|max:30',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'supplier_sku.required' => 'Supplier SKU is required.',
            'supplier_sku.unique' => 'This SKU already exists for the selected supplier.',
            'name.required' => 'Product name is required.',
            'supplier_price.required' => 'Supplier price is required.',
            'supplier_price.min' => 'Supplier price must be at least 1 penny.',
            'retail_price.min' => 'Retail price must be at least 1 penny.',
            'stock_quantity.required' => 'Stock quantity is required.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',
            'weight.min' => 'Weight cannot be negative.',
            'weight.max' => 'Weight cannot exceed 999,999.99kg.',
            'length.min' => 'Length cannot be negative.',
            'length.max' => 'Length cannot exceed 999,999.99cm.',
            'width.min' => 'Width cannot be negative.',
            'width.max' => 'Width cannot exceed 999,999.99cm.',
            'height.min' => 'Height cannot be negative.',
            'height.max' => 'Height cannot exceed 999,999.99cm.',
            'sync_status.required' => 'Sync status is required.',
            'sync_status.in' => 'Invalid sync status.',
            'minimum_order_quantity.min' => 'Minimum order quantity must be at least 1.',
            'minimum_order_quantity.max' => 'Minimum order quantity cannot exceed 1000.',
            'processing_time_days.min' => 'Processing time must be at least 1 day.',
            'processing_time_days.max' => 'Processing time cannot exceed 30 days.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_mapped' => $this->boolean('is_mapped', false),
        ]);

        if ($this->has('supplier_price') && is_numeric($this->input('supplier_price'))) {
            $this->merge([
                'supplier_price' => (int) round($this->input('supplier_price') * 100)
            ]);
        }

        if ($this->has('retail_price') && is_numeric($this->input('retail_price'))) {
            $this->merge([
                'retail_price' => (int) round($this->input('retail_price') * 100)
            ]);
        }
    }
}

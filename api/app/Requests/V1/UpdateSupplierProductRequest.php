<?php

namespace App\Requests\V1;

use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('edit_supplier_products');
    }

    public function rules(): array
    {
        $supplierProduct = $this->route('supplierProduct');

        return [
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'product_id' => 'sometimes|nullable|exists:products,id',
            'supplier_sku' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('supplier_products')->where(function ($query) use ($supplierProduct) {
                    return $query->where('supplier_id', $this->input('supplier_id', $supplierProduct->supplier_id));
                })->ignore($supplierProduct->id)
            ],
            'supplier_product_id' => 'sometimes|nullable|string|max:255',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string|max:5000',
            'supplier_price' => 'sometimes|required|integer|min:1',
            'retail_price' => 'sometimes|nullable|integer|min:1',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'weight' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'length' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'width' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'height' => 'sometimes|nullable|numeric|min:0|max:999999.99',
            'sync_status' => ['sometimes', 'required', 'string', Rule::in(DropshipProductSyncStatuses::all())],
            'images' => 'sometimes|nullable|array',
            'images.*' => 'string|max:500',
            'attributes' => 'sometimes|nullable|array',
            'categories' => 'sometimes|nullable|array',
            'categories.*' => 'string|max:255',
            'is_active' => 'sometimes|boolean',
            'is_mapped' => 'sometimes|boolean',
            'minimum_order_quantity' => 'sometimes|nullable|integer|min:1|max:1000',
            'processing_time_days' => 'sometimes|nullable|integer|min:1|max:30',
            'sync_errors' => 'sometimes|nullable|string|max:2000',
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
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        if ($this->has('is_mapped')) {
            $this->merge(['is_mapped' => $this->boolean('is_mapped')]);
        }

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

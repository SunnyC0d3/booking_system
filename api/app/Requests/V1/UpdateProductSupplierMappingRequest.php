<?php

namespace App\Requests\V1;

use Illuminate\Validation\Rule;

class UpdateProductSupplierMappingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_product_mappings');
    }

    public function rules(): array
    {
        $mapping = $this->route('productSupplierMapping');

        return [
            'product_id' => 'sometimes|required|exists:products,id',
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'supplier_product_id' => 'sometimes|required|exists:supplier_products,id',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'priority_order' => 'sometimes|integer|min:1|max:999',
            'markup_percentage' => 'sometimes|nullable|numeric|min:0|max:1000',
            'fixed_markup' => 'sometimes|nullable|integer|min:0',
            'markup_type' => 'sometimes|required|string|in:percentage,fixed',
            'minimum_stock_threshold' => 'sometimes|integer|min:0|max:1000',
            'auto_update_price' => 'sometimes|boolean',
            'auto_update_stock' => 'sometimes|boolean',
            'auto_update_description' => 'sometimes|boolean',
            'field_mappings' => 'sometimes|nullable|array',
            'field_mappings.name' => 'nullable|string|max:255',
            'field_mappings.description' => 'nullable|string|max:255',
            'field_mappings.price' => 'nullable|string|max:255',
            'field_mappings.stock' => 'nullable|string|max:255',
            'field_mappings.sku' => 'nullable|string|max:255',
            'field_mappings.weight' => 'nullable|string|max:255',
            'field_mappings.images' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'Selected product does not exist.',
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'supplier_product_id.required' => 'Supplier product is required.',
            'supplier_product_id.exists' => 'Selected supplier product does not exist.',
            'priority_order.min' => 'Priority order must be at least 1.',
            'priority_order.max' => 'Priority order cannot exceed 999.',
            'markup_percentage.min' => 'Markup percentage cannot be negative.',
            'markup_percentage.max' => 'Markup percentage cannot exceed 1000%.',
            'fixed_markup.min' => 'Fixed markup cannot be negative.',
            'markup_type.required' => 'Markup type is required.',
            'markup_type.in' => 'Markup type must be either percentage or fixed.',
            'minimum_stock_threshold.min' => 'Minimum stock threshold cannot be negative.',
            'minimum_stock_threshold.max' => 'Minimum stock threshold cannot exceed 1000.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_primary')) {
            $this->merge(['is_primary' => $this->boolean('is_primary')]);
        }

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        if ($this->has('auto_update_price')) {
            $this->merge(['auto_update_price' => $this->boolean('auto_update_price')]);
        }

        if ($this->has('auto_update_stock')) {
            $this->merge(['auto_update_stock' => $this->boolean('auto_update_stock')]);
        }

        if ($this->has('auto_update_description')) {
            $this->merge(['auto_update_description' => $this->boolean('auto_update_description')]);
        }

        if ($this->has('fixed_markup') && is_numeric($this->input('fixed_markup'))) {
            $this->merge([
                'fixed_markup' => (int) round($this->input('fixed_markup') * 100)
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mapping = $this->route('productSupplierMapping');

            if ($this->has('product_id') && $this->has('supplier_id')) {
                $existingMapping = \App\Models\ProductSupplierMapping::where('product_id', $this->input('product_id'))
                    ->where('supplier_id', $this->input('supplier_id'))
                    ->where('id', '!=', $mapping->id)
                    ->first();

                if ($existingMapping) {
                    $validator->errors()->add('supplier_id', 'A mapping already exists between this product and supplier.');
                }
            }

            if ($this->has('supplier_product_id') && $this->has('supplier_id')) {
                $supplierProduct = \App\Models\SupplierProduct::find($this->input('supplier_product_id'));
                if ($supplierProduct && $supplierProduct->supplier_id !== $this->input('supplier_id')) {
                    $validator->errors()->add('supplier_product_id', 'The supplier product does not belong to the selected supplier.');
                }
            }

            if ($this->has('markup_type')) {
                if ($this->input('markup_type') === 'percentage' && !$this->has('markup_percentage')) {
                    $validator->errors()->add('markup_percentage', 'Markup percentage is required when markup type is percentage.');
                }

                if ($this->input('markup_type') === 'fixed' && !$this->has('fixed_markup')) {
                    $validator->errors()->add('fixed_markup', 'Fixed markup is required when markup type is fixed.');
                }
            }

            if ($this->has('is_active') && !$this->input('is_active') && $mapping->is_primary) {
                $validator->errors()->add('is_active', 'Cannot deactivate primary mapping. Make another mapping primary first.');
            }
        });
    }
}

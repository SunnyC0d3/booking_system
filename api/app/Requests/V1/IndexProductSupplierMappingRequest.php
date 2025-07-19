<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductSupplierMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('view_product_mappings');
    }

    public function rules(): array
    {
        return [
            'product_id' => 'sometimes|exists:products,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'markup_type' => 'sometimes|string|in:percentage,fixed',
            'search' => 'sometimes|string|max:255',
            'auto_update_price' => 'sometimes|boolean',
            'auto_update_stock' => 'sometimes|boolean',
            'auto_update_description' => 'sometimes|boolean',
            'health_status' => 'sometimes|string|in:healthy,inactive,supplier_inactive',
            'min_markup' => 'sometimes|numeric|min:0',
            'max_markup' => 'sometimes|numeric|min:0',
            'last_price_update_from' => 'sometimes|date',
            'last_price_update_to' => 'sometimes|date|after_or_equal:last_price_update_from',
            'last_stock_update_from' => 'sometimes|date',
            'last_stock_update_to' => 'sometimes|date|after_or_equal:last_stock_update_from',
            'priority_order' => 'sometimes|integer|min:1|max:999',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:priority_order,markup_percentage,fixed_markup,last_price_update,last_stock_update,created_at',
            'sort_direction' => 'sometimes|string|in:asc,desc',
            'include_product' => 'sometimes|boolean',
            'include_supplier' => 'sometimes|boolean',
            'include_supplier_product' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.exists' => 'Selected product does not exist.',
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'markup_type.in' => 'Markup type must be either percentage or fixed.',
            'health_status.in' => 'Invalid health status. Must be one of: healthy, inactive, supplier_inactive.',
            'min_markup.min' => 'Minimum markup cannot be negative.',
            'max_markup.min' => 'Maximum markup cannot be negative.',
            'last_price_update_from.date' => 'Last price update from date must be a valid date.',
            'last_price_update_to.date' => 'Last price update to date must be a valid date.',
            'last_price_update_to.after_or_equal' => 'Last price update to date must be after or equal to from date.',
            'last_stock_update_from.date' => 'Last stock update from date must be a valid date.',
            'last_stock_update_to.date' => 'Last stock update to date must be a valid date.',
            'last_stock_update_to.after_or_equal' => 'Last stock update to date must be after or equal to from date.',
            'priority_order.min' => 'Priority order must be at least 1.',
            'priority_order.max' => 'Priority order cannot exceed 999.',
            'per_page.max' => 'Cannot retrieve more than 100 mappings per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
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

        if ($this->has('include_product')) {
            $this->merge(['include_product' => $this->boolean('include_product')]);
        }

        if ($this->has('include_supplier')) {
            $this->merge(['include_supplier' => $this->boolean('include_supplier')]);
        }

        if ($this->has('include_supplier_product')) {
            $this->merge(['include_supplier_product' => $this->boolean('include_supplier_product')]);
        }
    }
}

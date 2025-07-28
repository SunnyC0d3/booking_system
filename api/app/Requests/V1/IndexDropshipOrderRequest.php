<?php

namespace App\Requests\V1;

use App\Constants\DropshipStatuses;
use Illuminate\Validation\Rule;

class IndexDropshipOrderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('view_dropship_orders');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'sometimes|exists:suppliers,id',
            'status' => ['sometimes', 'string', Rule::in(DropshipStatuses::all())],
            'order_id' => 'sometimes|exists:orders,id',
            'search' => 'sometimes|string|max:255',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'overdue' => 'sometimes|boolean',
            'needs_retry' => 'sometimes|boolean',
            'has_tracking' => 'sometimes|boolean',
            'min_total' => 'sometimes|numeric|min:0',
            'max_total' => 'sometimes|numeric|min:0',
            'carrier' => 'sometimes|string|max:255',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:id,created_at,updated_at,total_cost,total_retail,profit_margin,estimated_delivery',
            'sort_direction' => 'sometimes|string|in:asc,desc',
            'include_items' => 'sometimes|boolean',
            'include_supplier' => 'sometimes|boolean',
            'include_order' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.exists' => 'Selected supplier does not exist.',
            'status.in' => 'Invalid dropship order status.',
            'order_id.exists' => 'Selected order does not exist.',
            'date_from.date' => 'Date from must be a valid date.',
            'date_to.date' => 'Date to must be a valid date.',
            'date_to.after_or_equal' => 'Date to must be after or equal to date from.',
            'min_total.min' => 'Minimum total cannot be negative.',
            'max_total.min' => 'Maximum total cannot be negative.',
            'per_page.max' => 'Cannot retrieve more than 100 orders per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('overdue')) {
            $this->merge(['overdue' => $this->boolean('overdue')]);
        }

        if ($this->has('needs_retry')) {
            $this->merge(['needs_retry' => $this->boolean('needs_retry')]);
        }

        if ($this->has('has_tracking')) {
            $this->merge(['has_tracking' => $this->boolean('has_tracking')]);
        }

        if ($this->has('include_items')) {
            $this->merge(['include_items' => $this->boolean('include_items')]);
        }

        if ($this->has('include_supplier')) {
            $this->merge(['include_supplier' => $this->boolean('include_supplier')]);
        }

        if ($this->has('include_order')) {
            $this->merge(['include_order' => $this->boolean('include_order')]);
        }

        if ($this->has('min_total') && is_numeric($this->input('min_total'))) {
            $this->merge([
                'min_total' => (int) round($this->input('min_total') * 100)
            ]);
        }

        if ($this->has('max_total') && is_numeric($this->input('max_total'))) {
            $this->merge([
                'max_total' => (int) round($this->input('max_total') * 100)
            ]);
        }
    }
}

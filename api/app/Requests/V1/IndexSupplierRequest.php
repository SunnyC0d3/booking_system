<?php

namespace App\Requests\V1;

use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Validation\Rule;

class IndexSupplierRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('view_suppliers');
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(SupplierStatuses::all())],
            'integration_type' => ['sometimes', 'string', Rule::in(SupplierIntegrationTypes::all())],
            'search' => 'sometimes|string|max:255',
            'country' => 'sometimes|string|size:2',
            'auto_fulfill' => 'sometimes|boolean',
            'stock_sync_enabled' => 'sometimes|boolean',
            'price_sync_enabled' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort_by' => 'sometimes|string|in:name,created_at,last_sync_at,processing_time_days',
            'sort_direction' => 'sometimes|string|in:asc,desc',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Invalid supplier status.',
            'integration_type.in' => 'Invalid integration type.',
            'country.size' => 'Country code must be exactly 2 characters.',
            'per_page.max' => 'Cannot retrieve more than 100 suppliers per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
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

<?php

namespace App\Requests\V1;

use App\Constants\SupplierIntegrationTypes;
use Illuminate\Validation\Rule;

class IndexSupplierIntegrationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('view_supplier_integrations');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'sometimes|integer|exists:suppliers,id',
            'integration_type' => ['sometimes', 'string', Rule::in(SupplierIntegrationTypes::all())],
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:active,inactive,error,maintenance,failed,disabled',
            'search' => 'sometimes|string|max:255',
            'healthy' => 'sometimes|boolean',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:50',
            'sort_by' => 'sometimes|string|in:name,created_at,last_successful_sync,status,supplier_name,consecutive_failures',
            'sort_direction' => 'sometimes|string|in:asc,desc',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.integer' => 'The supplier ID must be an integer.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
            'integration_type.in' => 'Invalid integration type. Must be one of: ' . implode(', ', SupplierIntegrationTypes::all()) . '.',
            'is_active.boolean' => 'The active status must be true or false.',
            'status.in' => 'Invalid status. Must be one of: active, inactive, error, maintenance, failed, disabled.',
            'search.max' => 'Search query cannot exceed 255 characters.',
            'healthy.boolean' => 'The healthy filter must be true or false.',
            'page.integer' => 'Page must be an integer.',
            'page.min' => 'Page must be at least 1.',
            'per_page.integer' => 'Per page must be an integer.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Cannot retrieve more than 50 integrations per page.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        if ($this->has('healthy')) {
            $this->merge(['healthy' => $this->boolean('healthy')]);
        }

        if ($this->has('search')) {
            $this->merge([
                'search' => trim($this->input('search'))
            ]);
        }

        // Set defaults
        $this->merge([
            'per_page' => $this->input('per_page', 15),
            'sort_by' => $this->input('sort_by', 'created_at'),
            'sort_direction' => $this->input('sort_direction', 'desc'),
        ]);
    }
}

<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

/**
 * Handles validation for filtering products via API queries
 */
class FilterProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => 'array',
            'filter.name' => 'string|max:255',
            'filter.price' => 'string|regex:/^\d+(\.\d{1,2})?(,\d+(\.\d{1,2})?)*$/',
            'filter.category' => 'string|regex:/^(\d,?)+$/',
            'filter.quantity' => 'integer|min:0',
            'filter.created_at' => 'string|regex:/^\d{4}-\d{2}-\d{2}(,\d{4}-\d{2}-\d{2})?$/',
            'filter.updated_at' => 'string|regex:/^\d{4}-\d{2}-\d{2}(,\d{4}-\d{2}-\d{2})?$/',
            'filter.search' => 'string|max:255',
            'filter.include' => 'string|regex:/^(\w+(,\w+)*)?$/',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'string|regex:/^(-?[a-zA-Z0-9_]+)(,-?[a-zA-Z0-9_]+)*$/',
        ];
    }
}

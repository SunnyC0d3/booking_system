<?php

namespace App\Requests\V1;

class FilterVendorRequest extends BaseFormRequest
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
            'filter.description' => 'string|max:1000',
            'filter.user_id' => 'integer|exists:users,id',
            'filter.created_at' => 'string|regex:/^\\d{4}-\\d{2}-\\d{2}(,\\d{4}-\\d{2}-\\d{2})?$/',
            'filter.updated_at' => 'string|regex:/^\\d{4}-\\d{2}-\\d{2}(,\\d{4}-\\d{2}-\\d{2})?$/',
            'filter.include' => 'string|regex:/^(\\w+(,\\w+)*)?$/',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort' => 'string|regex:/^(-?[a-zA-Z0-9_]+)(,-?[a-zA-Z0-9_]+)*$/',
        ];
    }
}

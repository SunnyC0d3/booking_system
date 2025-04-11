<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class FilterUserRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'filter' => 'array',
            'filter.name' => 'string|max:255',
            'filter.email' => 'string|email|max:255',
            'filter.role' => 'string|regex:/^(\d,?)+$/',
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

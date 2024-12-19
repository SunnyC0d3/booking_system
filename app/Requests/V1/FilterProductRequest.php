<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for filtering products via API queries
 */
class FilterProductRequest extends FormRequest
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
            'filter.price' => 'string',
            'filter.category_id' => 'integer|exists:categories,id',
            'sort' => 'string',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ];
    }
}
<?php

namespace App\Requests\V1;

/**
 * Handles validation for storing product statuses
 */
class StoreProductTagRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:product_tags,name',
        ];
    }
}

<?php

namespace App\Requests\V1;

/**
 * Handles validation for storing product statuses
 */
class UpdateProductTagRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productTagId = $this->route('productTag')?->id ?? 'null';

        return [
            'name' => 'required|string|max:255|unique:product_tags,name,' . $productTagId,
        ];
    }
}

<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

/**
 * Handles validation for storing product attributes
 */
class StoreProductAttributeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:product_attributes,name',
        ];
    }
}

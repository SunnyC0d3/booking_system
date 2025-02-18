<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

/**
 * Handles validation for updating product attributes
 */
class UpdateProductAttributeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productAttributeId = $this->route('productAttribute')?->id ?? 'NULL';

        return [
            'name' => 'required|string|max:255|unique:product_attributes,name,' . $productAttributeId,
        ];
    }
}

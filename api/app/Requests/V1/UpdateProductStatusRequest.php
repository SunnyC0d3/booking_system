<?php

namespace App\Requests\V1;

/**
 * Handles validation for updating product statuses
 */
class UpdateProductStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productStatusId = $this->route('productStatus')->id ?? 'null';

        return [
            'name' => 'required|string|max:255|unique:product_statuses,name,' . $productStatusId,
        ];
    }
}

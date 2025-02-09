<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class StoreProductCategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:product_categories,name',
            'parent_id' => 'nullable|exists:product_categories,id',
        ];
    }
}

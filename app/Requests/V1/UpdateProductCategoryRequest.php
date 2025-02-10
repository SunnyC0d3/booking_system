<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class UpdateProductCategoryRequest extends BaseFormRequest
{
    protected ?int $productCategoryId = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->productCategoryId = $this->route('productCategory')->id ?? null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:product_categories,name,' . $this->productCategoryId,
            'parent_id' => 'nullable|exists:product_categories,id|not_in:' . $this->productCategoryId,
        ];
    }
}

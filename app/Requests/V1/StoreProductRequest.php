<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

/**
 * Handles validation for creating new products
 */
class StoreProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'product_category_id' => 'required|exists:product_categories,id',
            'quantity' => 'required|integer|min:0',
            'product_variants' => 'array',
            'product_variants.*.value' => 'required_with:product_variants|string|max:255',
            'product_variants.*.additional_price' => 'nullable|numeric|min:0',
            'product_variants.*.quantity' => 'integer|min:0',
            'media' => 'array',
            'media.*' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}

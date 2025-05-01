<?php

namespace App\Requests\V1;

/**
 * Handles validation for updating existing products
 */
class UpdateProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'product_category_id' => 'sometimes|required|exists:product_categories,id',
            'product_status_id' => 'sometimes|required|exists:product_statuses,id',
            'quantity' => 'sometimes|required|integer|min:0',
            'product_tags' => 'array',
            'product_tags.*' => 'exists:product_tags,id',
            'product_variants' => 'array',
            'product_variants.*.product_attribute_id' => 'required|exists:product_attributes,id',
            'product_variants.*.value' => 'required_with:product_variants|string|max:255',
            'product_variants.*.additional_price' => 'nullable|numeric|min:0',
            'product_variants.*.quantity' => 'required|integer|min:0',
            'media.*' => 'array',
            'media.featured_image' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
            'media.gallery.*' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}

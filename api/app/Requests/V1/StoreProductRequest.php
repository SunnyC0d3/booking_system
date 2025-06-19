<?php

namespace App\Requests\V1;

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
            'product_status_id' => 'required|exists:product_statuses,id',
            'quantity' => 'required|integer|min:0',
            'product_tags' => 'array',
            'product_tags.*' => 'exists:product_tags,id',
            'product_variants' => 'array',
            'product_variants.*.product_attribute_id' => 'required|exists:product_attributes,id',
            'product_variants.*.value' => 'required_with:product_variants|string|max:255',
            'product_variants.*.additional_price' => 'nullable|numeric|min:0',
            'product_variants.*.quantity' => 'required|integer|min:0',
            'media.*' => 'array',
            'media.featured_image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'
            ],
            'media.gallery.*' => [
                'file',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'
            ],
        ];
    }
}

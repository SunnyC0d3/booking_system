<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for creating new products
 */
class StoreProductRequest extends FormRequest
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
            'category_id' => 'nullable|exists:categories,id',
            'attributes' => 'array',
            'attributes.*.key' => 'required_with:attributes|string|max:255',
            'attributes.*.value' => 'required_with:attributes|string|max:255',
            'images' => 'array',
            'images.*' => 'file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }
}

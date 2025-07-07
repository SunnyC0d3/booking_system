<?php

namespace App\Requests\V1;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id',
                function ($attribute, $value, $fail) {
                    if ($value && $this->product_id) {
                        $variant = ProductVariant::find($value);
                        if ($variant && $variant->product_id != $this->product_id) {
                            $fail('The selected product variant does not belong to the specified product.');
                        }
                    }
                }
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Please select a product to add to cart.',
            'product_id.exists' => 'The selected product is not available.',
            'product_variant_id.exists' => 'The selected product variant is not available.',
            'quantity.required' => 'Please specify the quantity.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Cannot add more than 999 items at once.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->product_variant_id === '') {
            $this->merge(['product_variant_id' => null]);
        }
    }
}

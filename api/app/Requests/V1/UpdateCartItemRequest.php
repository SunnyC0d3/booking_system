<?php

namespace App\Requests\V1;

class UpdateCartItemRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => [
                'required',
                'integer',
                'min:0',
                'max:999',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Please specify the quantity.',
            'quantity.integer' => 'Quantity must be a valid number.',
            'quantity.min' => 'Quantity cannot be negative.',
            'quantity.max' => 'Cannot have more than 999 items of the same product.',
        ];
    }
}

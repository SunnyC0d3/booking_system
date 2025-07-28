<?php

namespace App\Requests\V1;

class CreateOrderFromCartRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_cart' => ['required', 'boolean', 'accepted'],
            'shipping_method_id' => ['nullable', 'integer', 'exists:shipping_methods,id'],
            'shipping_address_id' => ['nullable', 'integer', 'exists:shipping_addresses,id'],
            'shipping_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_cart.accepted' => 'This endpoint is for creating orders from cart only.',
            'shipping_method_id.exists' => 'The selected shipping method is not valid.',
            'shipping_address_id.exists' => 'The selected shipping address is not valid.',
        ];
    }
}

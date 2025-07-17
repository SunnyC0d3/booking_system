<?php

namespace App\Requests\V1;

class GetShippingQuoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address_id' => [
                'required',
                'integer',
                'exists:shipping_addresses,id',
                function ($attribute, $value, $fail) {
                    $user = $this->user();
                    if ($user) {
                        $address = \App\Models\ShippingAddress::where('id', $value)
                            ->where('user_id', $user->id)
                            ->first();

                        if (!$address) {
                            $fail('The selected shipping address does not belong to you.');
                        }
                    }
                }
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_address_id.required' => 'Shipping address is required to calculate shipping costs.',
            'shipping_address_id.integer' => 'Invalid shipping address ID format.',
            'shipping_address_id.exists' => 'The selected shipping address does not exist.',
        ];
    }
}

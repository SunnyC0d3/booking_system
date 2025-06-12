<?php

namespace App\Requests\V1;

class VerifyPaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_intent_id' => [
                'required',
                'string',
                'regex:/^pi_[a-zA-Z0-9_]+$/',
                'max:255'
            ],
            'order_id' => ['required', 'exists:orders,id']
        ];
    }

    public function messages(): array
    {
        return [
            'payment_intent_id.required' => 'Payment intent ID is required.',
            'payment_intent_id.string' => 'Payment intent ID must be a string.',
            'payment_intent_id.regex' => 'Payment intent ID format is invalid. Must be a valid Stripe payment intent ID.',
            'payment_intent_id.max' => 'Payment intent ID cannot exceed 255 characters.',
            'order_id.required' => 'Order ID is required.',
            'order_id.integer' => 'Order ID must be an integer.',
            'order_id.exists' => 'The specified order does not exist.'
        ];
    }
}

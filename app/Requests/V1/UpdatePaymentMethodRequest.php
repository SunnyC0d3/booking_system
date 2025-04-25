<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class UpdatePaymentMethodRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // Add policy if needed
    }

    public function rules(): array
    {
        $paymentMethodId = $this->route('paymentMethod')?->id ?? 'null';

        return [
            'name' => 'required|string|max:255|in:Credit Card,PayPal,Bank Transfer,Apple Pay,Google Pay|unique:payment_methods,name,' . $paymentMethodId,
        ];
    }
}

<?php

namespace App\Requests\V1;

use App\Constants\PaymentMethods;

class UpdatePaymentMethodRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentMethodId = $this->route('paymentMethod')?->id ?? 'null';

        return [
            'name' => 'required|string|max:255|in:'. implode(',', PaymentMethods::all()) . '|unique:payment_methods,name,' . $paymentMethodId,
        ];
    }
}

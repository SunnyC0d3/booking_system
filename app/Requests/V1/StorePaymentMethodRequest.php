<?php

namespace App\Requests\V1;

use App\Constants\PaymentMethods;

class StorePaymentMethodRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|in:'. implode(',', PaymentMethods::all()) . '|unique:payment_methods,name',
        ];
    }
}

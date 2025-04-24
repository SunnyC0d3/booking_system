<?php

namespace App\Http\Requests;

use App\Requests\V1\BaseFormRequest;
use Illuminate\Validation\Rule;

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
            'name' => 'required|string|max:255|unique:payment_methods,name,' . $paymentMethodId,
        ];
    }
}

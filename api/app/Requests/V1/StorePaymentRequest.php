<?php

namespace App\Requests\V1;

class StorePaymentRequest extends BaseFormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'order_id' => ['required', 'exists:orders,id'],
        ];
    }
}

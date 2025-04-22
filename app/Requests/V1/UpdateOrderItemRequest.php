<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class UpdateOrderItemRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'sometimes|integer|min:1',
            'price' => 'sometimes|numeric|min:0',
        ];
    }
}

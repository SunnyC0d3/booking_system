<?php

namespace App\Requests\V1;

class IndexOrderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'status_id' => 'nullable|exists:order_statuses,id'
        ];
    }
}

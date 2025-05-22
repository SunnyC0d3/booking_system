<?php

namespace App\Requests\V1;

class StoreReturnRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_item_id' => 'required|exists:order_items,id',
            'reason' => 'required|string|max:1000',
        ];
    }
}

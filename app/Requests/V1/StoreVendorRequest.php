<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class StoreVendorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }
}

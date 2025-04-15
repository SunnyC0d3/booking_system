<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class UpdateVendorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'sometimes|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }
}

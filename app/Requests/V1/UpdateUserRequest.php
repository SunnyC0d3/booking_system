<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class UpdateUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email',
            'password' => 'sometimes|nullable|min:8',
            'role_id' => 'sometimes|exists:roles,id',
            'address.address_line1' => 'sometimes|required|string|max:255',
            'address.city' => 'sometimes|required|string|max:255',
            'address.country' => 'sometimes|required|string|max:255',
            'address.postal_code' => 'sometimes|required|string|max:20',
            'address.address_line2' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
        ];
    }
}

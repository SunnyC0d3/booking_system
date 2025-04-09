<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class StoreUserRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'role_id' => 'required|exists:roles,id',
            'address.address_line1' => 'required|string|max:255',
            'address.city' => 'required|string|max:255',
            'address.country' => 'required|string|max:255',
            'address.postal_code' => 'required|string|max:20',
            'address.address_line2' => 'nullable|string|max:255',
            'address.state' => 'nullable|string|max:255',
        ];
    }
}

<?php

namespace App\Requests\V1;

class ChangePasswordRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:12|confirmed',
            'new_password_confirmation' => 'required|string'
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Your current password is required to change your password.',
            'new_password.required' => 'Please enter your new password.',
            'new_password.min' => 'Your new password must be at least 12 characters long.',
            'new_password.confirmed' => 'Your new password confirmation does not match.',
            'new_password_confirmation.required' => 'Please confirm your new password.'
        ];
    }
}

<?php

namespace App\Requests\V1;

class AssignPermissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }
}

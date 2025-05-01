<?php

namespace App\Requests\V1;

class UpdatePermissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission')?->id ?? 'null';

        return [
            'name' => 'required|string|max:255|unique:permissions,name,' . $permissionId,
        ];
    }
}

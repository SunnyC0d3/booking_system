<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class LoginUserRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'string', 'exists:oauth_clients,id'],
            'client_secret' => ['required', 'string', 'exists:oauth_clients,secret'],
            'redirect_uri' => ['required', 'string', 'url'],
            'grant_type' => ['required', 'string', 'in:authorization_code'],
            'code' => ['required', 'string']
        ];
    }
}
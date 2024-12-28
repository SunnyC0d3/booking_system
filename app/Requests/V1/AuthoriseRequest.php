<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

class AuthoriseRequest extends BaseFormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'client_id' => ['required', 'string', 'exists:oauth_clients,id'],
            'redirect_uri' => ['required', 'string', 'url'],
            'response_type' => ['required', 'string', 'in:code'],
            'scope' => ['nullable', 'string'],
            'state' => ['required', 'string'],
            'prompt' => ['nullable', 'string', 'in:login,none,consent']
        ];
    }
}

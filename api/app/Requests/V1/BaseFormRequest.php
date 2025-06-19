<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Traits\V1\ApiResponses;

class BaseFormRequest extends FormRequest
{
    use ApiResponses;

    protected function prepareForValidation()
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(trim($this->input('email')))
            ]);
        }

        if ($this->has('name')) {
            $this->merge([
                'name' => strip_tags(trim($this->input('name')))
            ]);
        }

        if ($this->has('description')) {
            $this->merge([
                'description' => strip_tags($this->input('description'), '<p><br><strong><em>')
            ]);
        }

        if ($this->has('search')) {
            $this->merge([
                'search' => htmlspecialchars(trim($this->input('search')), ENT_QUOTES, 'UTF-8')
            ]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        foreach ($validator->errors()->all() as $error) {
            $errors[] = $error;
        }

        throw new HttpResponseException($this->error($errors, 422));
    }
}

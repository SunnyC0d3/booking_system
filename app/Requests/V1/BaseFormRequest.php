<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Traits\V1\ApiResponses;

class BaseFormRequest extends FormRequest
{
    use ApiResponses;

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        foreach ($validator->errors()->all() as $error) {
            $errors[] = $error;
        }

        throw new HttpResponseException($this->error($errors, 422));
    }

    public function passedValidation()
    {
        if ($this->header('Content-Type') !== 'application/json') {
            throw new HttpResponseException($this->error('Content-Type must be application/json', 422));
        }
    }
}

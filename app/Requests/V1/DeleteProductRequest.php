<?php

namespace App\Requests\V1;

use App\Requests\V1\BaseFormRequest;

/**
 * Handles validation for deleting existing products
 */
class DeleteProductRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:products,id',
        ];
    }
}
<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for deleting existing products
 */
class DeleteProductRequest extends FormRequest
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
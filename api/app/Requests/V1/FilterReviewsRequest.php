<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterReviewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'sometimes',
                'integer',
                'exists:products,id',
            ],
            'rating' => [
                'sometimes',
                'array',
            ],
            'rating.*' => [
                'integer',
                'between:1,5',
            ],
            'verified_only' => [
                'sometimes',
                'boolean',
            ],
            'with_media' => [
                'sometimes',
                'boolean',
            ],
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in(['newest', 'oldest', 'rating_high', 'rating_low', 'helpful']),
            ],
            'per_page' => [
                'sometimes',
                'integer',
                'between:1,50',
            ],
            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.*.between' => 'Rating filter must be between 1 and 5.',
            'sort_by.in' => 'Invalid sort option.',
            'per_page.between' => 'Per page must be between 1 and 50.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sort_by' => $this->input('sort_by', 'newest'),
            'per_page' => $this->input('per_page', 15),
            'page' => $this->input('page', 1),
        ]);
    }
}

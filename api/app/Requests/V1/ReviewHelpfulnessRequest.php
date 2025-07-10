<?php

namespace App\Requests\V1;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class ReviewHelpfulnessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $review = $this->route('review');

        if (!$user || !$review instanceof Review) {
            return false;
        }

        return $review->user_id !== $user->id;
    }

    public function rules(): array
    {
        return [
            'is_helpful' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'is_helpful.required' => 'You must specify whether the review was helpful.',
            'is_helpful.boolean' => 'Helpfulness vote must be true or false.',
        ];
    }
}

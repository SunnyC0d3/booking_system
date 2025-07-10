<?php

namespace App\Requests\V1;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportReviewRequest extends FormRequest
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
            'reason' => [
                'required',
                'string',
                Rule::in([
                    'spam',
                    'inappropriate_language',
                    'fake_review',
                    'off_topic',
                    'personal_information',
                    'other'
                ]),
            ],
            'details' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:reason,other',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please select a reason for reporting this review.',
            'reason.in' => 'Invalid report reason selected.',
            'details.required_if' => 'Please provide details when selecting "Other" as the reason.',
            'details.max' => 'Report details cannot exceed 1000 characters.',
        ];
    }
}

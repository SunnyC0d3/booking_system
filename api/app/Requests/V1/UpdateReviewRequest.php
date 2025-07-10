<?php

namespace App\Requests\V1;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $review = $this->route('review');

        if (!$user || !$review instanceof Review) {
            return false;
        }

        return $review->canBeEditedBy($user);
    }

    public function rules(): array
    {
        return [
            'rating' => [
                'sometimes',
                'required',
                'integer',
                'between:1,5',
            ],
            'title' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'min:3',
            ],
            'content' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'media' => [
                'sometimes',
                'nullable',
                'array',
                'max:5',
            ],
            'media.*' => [
                'file',
                'mimes:jpg,jpeg,png,gif,mp4,mov,avi',
                'max:10240',
            ],
            'remove_media' => [
                'sometimes',
                'array',
            ],
            'remove_media.*' => [
                'integer',
                'exists:review_media,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.between' => 'Rating must be between 1 and 5 stars.',
            'content.min' => 'Review content must be at least 10 characters long.',
            'content.max' => 'Review content cannot exceed 2000 characters.',
            'title.min' => 'Review title must be at least 3 characters long.',
            'media.max' => 'You can upload a maximum of 5 files.',
            'media.*.max' => 'Each file must not exceed 10MB.',
            'media.*.mimes' => 'Only images and videos are allowed.',
        ];
    }
}

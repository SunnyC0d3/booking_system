<?php

namespace App\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminReviewModerationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasRole(['super admin', 'admin']);
    }

    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::in(['approve', 'reject', 'feature', 'unfeature']),
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
                'required_if:action,reject',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Moderation action is required.',
            'action.in' => 'Invalid moderation action.',
            'reason.required_if' => 'Reason is required when rejecting a review.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }
}

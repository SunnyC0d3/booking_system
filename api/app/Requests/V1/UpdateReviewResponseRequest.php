<?php

namespace App\Requests\V1;

use App\Models\ReviewResponse;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $response = $this->route('response');

        if (!$user || !$response instanceof ReviewResponse) {
            return false;
        }

        return $response->canBeEditedBy($user);
    }

    public function rules(): array
    {
        return [
            'content' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Response content is required.',
            'content.min' => 'Response must be at least 10 characters long.',
            'content.max' => 'Response cannot exceed 1000 characters.',
        ];
    }
}

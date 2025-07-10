<?php

namespace App\Requests\V1;

use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $review = $this->route('review');

        if (!$user || !$review instanceof Review) {
            return false;
        }

        if (!$user->hasRole('vendor')) {
            return false;
        }

        $vendor = Vendor::where('user_id', $user->id)->first();
        if (!$vendor) {
            return false;
        }

        if ($review->product->vendor_id !== $vendor->id) {
            return false;
        }

        return !$vendor->hasRespondedToReview($review);
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

    protected function prepareForValidation(): void
    {
        $vendor = Vendor::where('user_id', $this->user()->id)->first();
        if ($vendor) {
            $this->merge(['vendor_id' => $vendor->id]);
        }
    }
}

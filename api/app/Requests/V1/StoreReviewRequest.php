<?php

namespace App\Requests\V1;

use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $productId = $this->input('product_id');

        if (!$user || !$productId) {
            return false;
        }

        $product = Product::find($productId);

        if (!$product) {
            return false;
        }

        return $product->canUserReview($user);
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
                Rule::unique('reviews')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                }),
            ],
            'order_item_id' => [
                'nullable',
                'integer',
                'exists:order_items,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $orderItem = OrderItem::with('order')->find($value);
                        if (!$orderItem || $orderItem->order->user_id !== $this->user()->id) {
                            $fail('Invalid order item.');
                        }
                        if ($orderItem->product_id !== $this->input('product_id')) {
                            $fail('Order item does not match the product.');
                        }
                    }
                },
            ],
            'rating' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
                'min:3',
            ],
            'content' => [
                'required',
                'string',
                'min:10',
                'max:2000',
            ],
            'media' => [
                'nullable',
                'array',
                'max:5',
            ],
            'media.*' => [
                'file',
                'mimes:jpg,jpeg,png,gif,mp4,mov,avi',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.unique' => 'You have already reviewed this product.',
            'rating.between' => 'Rating must be between 1 and 5 stars.',
            'content.min' => 'Review content must be at least 10 characters long.',
            'content.max' => 'Review content cannot exceed 2000 characters.',
            'title.min' => 'Review title must be at least 3 characters long.',
            'media.max' => 'You can upload a maximum of 5 files.',
            'media.*.max' => 'Each file must not exceed 10MB.',
            'media.*.mimes' => 'Only images (jpg, jpeg, png, gif) and videos (mp4, mov, avi) are allowed.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('order_item_id') && $this->input('order_item_id')) {
            $this->merge(['is_verified_purchase' => true]);
        }
    }
}

<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->when($this->relationLoaded('user'), function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->maskEmail($this->user->email),
                ];
            }),
            'product' => $this->when($this->relationLoaded('product'), function () {
                return [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                    'price_formatted' => $this->product->price_formatted,
                    'featured_image' => $this->product->featured_image,
                ];
            }),
            'order_item_id' => $this->order_item_id,
            'rating' => $this->rating,
            'title' => $this->title,
            'content' => $this->content,
            'is_verified_purchase' => $this->is_verified_purchase,
            'is_featured' => $this->is_featured,
            'is_approved' => $this->is_approved,
            'helpful_votes' => $this->helpful_votes,
            'total_votes' => $this->total_votes,
            'helpfulness_ratio' => $this->getHelpfulnessRatio(),
            'user_voted' => $this->when(isset($this->user_voted), $this->user_voted),
            'media' => ReviewMediaResource::collection($this->whenLoaded('media')),
            'response' => $this->when($this->relationLoaded('response') && $this->response, function () {
                return new ReviewResponseResource($this->response);
            }),
            'approved_at' => $this->when($this->approved_at, function () {
                return $this->approved_at->toISOString();
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            'can_edit' => $this->when(isset($this->can_edit), $this->can_edit),
            'can_delete' => $this->when(isset($this->can_delete), $this->can_delete),

            // Admin-only fields
            'reports_count' => $this->when($this->shouldShowAdminData($request), function () {
                return $this->reports()->count();
            }),
            'last_reported_at' => $this->when($this->shouldShowAdminData($request), function () {
                $lastReport = $this->reports()->latest()->first();
                return $lastReport ? $lastReport->created_at->toISOString() : null;
            }),
        ];
    }

    protected function maskEmail(string $email): string
    {
        $parts = explode('@', $email);

        if (count($parts) !== 2) {
            return $email;
        }

        $username = $parts[0];
        $domain = $parts[1];

        // Show first character and mask the rest
        $maskedUsername = $username[0] . str_repeat('*', max(0, strlen($username) - 1));

        return $maskedUsername . '@' . $domain;
    }

    protected function shouldShowAdminData(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->hasRole(['super admin', 'admin']);
    }
}

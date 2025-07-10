<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor' => $this->when($this->relationLoaded('vendor'), function () {
                return [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->name,
                    'description' => $this->vendor->description,
                ];
            }),
            'user' => $this->when($this->relationLoaded('user'), function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'content' => $this->content,
            'is_approved' => $this->is_approved,
            'approved_at' => $this->when($this->approved_at, function () {
                return $this->approved_at->toISOString();
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),

            'can_edit' => $this->when(isset($this->can_edit), $this->can_edit),
            'can_delete' => $this->when(isset($this->can_delete), $this->can_delete),

            'review_id' => $this->when($this->shouldShowAdminData($request), $this->review_id),
            'vendor_id' => $this->when($this->shouldShowAdminData($request), $this->vendor_id),
            'user_id' => $this->when($this->shouldShowAdminData($request), $this->user_id),
        ];
    }

    protected function shouldShowAdminData(Request $request): bool
    {
        $user = $request->user();
        return $user && $user->hasRole(['super admin', 'admin']);
    }
}

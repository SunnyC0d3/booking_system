<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at,
            'stripe_customer_id' => $this->stripe_customer_id,
            'password_changed_at' => $this->password_changed_at,
            'last_login_at' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'user_address' => $this->whenLoaded('userAddress', function() {
                return [
                    'id' => $this->userAddress->id,
                    'address_line1' => $this->userAddress->address_line1,
                    'address_line2' => $this->userAddress->address_line2,
                    'city' => $this->userAddress->city,
                    'state' => $this->userAddress->state,
                    'country' => $this->userAddress->country,
                    'postal_code' => $this->userAddress->postal_code,
                ];
            }),
            'role' => $this->whenLoaded('role', function() {
                return [
                    'id' => $this->role->id,
                    'name' => $this->role->name
                ];
            }),
            'vendors' => VendorResource::collection($this->whenLoaded('vendors')),
            'security_info' => $this->when($request->user() && $request->user()->id === $this->id, function() {
                return [
                    'requires_password_change' => $this->requiresPasswordChange(),
                    'days_until_password_expiry' => $this->getDaysUntilPasswordExpiry(),
                    'security_score' => $this->getSecurityScore(),
                    'is_account_locked' => $this->isAccountLocked(),
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}

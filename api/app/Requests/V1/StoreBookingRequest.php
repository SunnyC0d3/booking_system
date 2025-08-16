<?php

namespace App\Requests\V1;

use Carbon\Carbon;

class StoreBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('create_own_bookings');
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|max:500',
            'notify_client' => 'boolean',
            'request_refund' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $booking = $this->route('booking');

            // Check if booking can be cancelled
            if (!in_array($booking->status, ['pending', 'confirmed'])) {
                $validator->errors()->add('status', 'This booking cannot be cancelled.');
            }

            // Check cancellation policy
            $scheduledAt = Carbon::parse($booking->scheduled_at);
            $hoursUntilBooking = now()->diffInHours($scheduledAt, false);

            if ($hoursUntilBooking < 24) {
                $validator->errors()->add('cancellation', 'Bookings can only be cancelled at least 24 hours in advance.');
            }
        });
    }
}

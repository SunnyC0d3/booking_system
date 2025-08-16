<?php

namespace App\Requests\V1;

class UpdateBookingRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $this->user()->hasPermission('update_own_bookings') &&
            $booking->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'scheduled_at' => 'nullable|date|after:now',
            'client_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'special_requirements' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $booking = $this->route('booking');

            // Check if booking can be updated
            if (!in_array($booking->status, ['pending', 'confirmed'])) {
                $validator->errors()->add('status', 'This booking cannot be updated.');
            }

            // Check if trying to reschedule too close to booking time
            if ($this->has('scheduled_at')) {
                $newTime = Carbon::parse($this->scheduled_at);
                $currentTime = Carbon::parse($booking->scheduled_at);

                if ($currentTime->lt(now()->addHours(24))) {
                    $validator->errors()->add('scheduled_at', 'Cannot reschedule bookings less than 24 hours in advance.');
                }
            }
        });
    }
}

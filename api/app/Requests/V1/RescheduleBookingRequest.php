<?php

namespace App\Requests\V1;

class RescheduleBookingRequest extends BaseFormRequest
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
            'new_scheduled_at' => 'required|date|after:now',
            'reason' => 'nullable|string|max:500',
            'notify_client' => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $booking = $this->route('booking');

            // Check if booking can be rescheduled
            if (!in_array($booking->status, ['pending', 'confirmed'])) {
                $validator->errors()->add('status', 'This booking cannot be rescheduled.');
            }

            // Check minimum advance time for rescheduling
            $newTime = Carbon::parse($this->new_scheduled_at);
            $service = $booking->service;

            if ($service && $service->min_advance_booking_hours) {
                $minTime = now()->addHours($service->min_advance_booking_hours);
                if ($newTime->lt($minTime)) {
                    $validator->errors()->add('new_scheduled_at', "Bookings must be rescheduled at least {$service->min_advance_booking_hours} hours in advance.");
                }
            }

            // Check maximum advance time
            if ($service && $service->max_advance_booking_days) {
                $maxTime = now()->addDays($service->max_advance_booking_days);
                if ($newTime->gt($maxTime)) {
                    $validator->errors()->add('new_scheduled_at', "Bookings cannot be rescheduled more than {$service->max_advance_booking_days} days in advance.");
                }
            }
        });
    }
}

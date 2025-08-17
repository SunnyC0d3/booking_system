<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class BookingRescheduledMail extends BaseSystemMail
{
    use Queueable, SerializesModels;

    protected string $templateName = 'booking.rescheduled';

    private Booking $booking;
    private ?Carbon $originalDateTime;
    private ?string $rescheduleReason;
    private ?string $rescheduledBy;

    public function __construct(
        Booking $booking,
        ?Carbon $originalDateTime = null,
        ?string $rescheduleReason = null,
        ?string $rescheduledBy = null
    ) {
        $this->booking = $booking;
        $this->originalDateTime = $originalDateTime;
        $this->rescheduleReason = $rescheduleReason;
        $this->rescheduledBy = $rescheduledBy;

        // Prepare email data using the booking
        parent::__construct($this->prepareEmailData());
    }

    protected function getSubject(): string
    {
        return "Booking Rescheduled - {$this->booking->service->name} (#{$this->booking->booking_reference})";
    }

    private function prepareEmailData(): array
    {
        return [
            'booking' => [
                'id' => $this->booking->id,
                'reference' => $this->booking->booking_reference,
                'status' => ucfirst($this->booking->status),
                'scheduled_at' => $this->booking->scheduled_at->format('l, F j, Y'),
                'scheduled_time' => $this->booking->scheduled_at->format('g:i A'),
                'ends_at' => $this->booking->ends_at->format('g:i A'),
                'duration_minutes' => $this->booking->duration_minutes,
                'duration_display' => $this->formatDuration($this->booking->duration_minutes),
                'total_amount' => $this->formatPrice($this->booking->total_amount),
                'payment_status' => ucfirst($this->booking->payment_status),
                'requires_consultation' => $this->booking->requires_consultation,
                'notes' => $this->booking->notes,
                'special_requirements' => $this->booking->special_requirements,
            ],

            'reschedule_details' => [
                'original_date' => $this->originalDateTime?->format('l, F j, Y'),
                'original_time' => $this->originalDateTime?->format('g:i A'),
                'original_datetime_full' => $this->originalDateTime?->format('l, F j, Y \a\t g:i A'),
                'new_date' => $this->booking->scheduled_at->format('l, F j, Y'),
                'new_time' => $this->booking->scheduled_at->format('g:i A'),
                'new_datetime_full' => $this->booking->scheduled_at->format('l, F j, Y \a\t g:i A'),
                'time_difference' => $this->getTimeDifference(),
                'date_difference' => $this->getDateDifference(),
                'reason' => $this->rescheduleReason,
                'rescheduled_by' => $this->rescheduledBy ?? 'system',
                'is_earlier' => $this->isEarlierTime(),
                'is_later' => $this->isLaterTime(),
                'is_same_day' => $this->isSameDay(),
                'days_moved' => $this->getDaysMoved(),
            ],

            'service' => [
                'name' => $this->booking->service->name,
                'description' => $this->booking->service->description,
                'short_description' => $this->booking->service->short_description,
                'duration_display' => $this->formatDuration($this->booking->service->duration_minutes),
                'category' => $this->booking->service->category,
                'preparation_notes' => $this->booking->service->preparation_notes,
            ],

            'client' => [
                'name' => $this->booking->client_name,
                'email' => $this->booking->client_email,
                'phone' => $this->booking->client_phone,
            ],

            'location' => $this->booking->serviceLocation ? [
                'name' => $this->booking->serviceLocation->name,
                'address' => $this->booking->serviceLocation->address,
                'city' => $this->booking->serviceLocation->city,
                'postcode' => $this->booking->serviceLocation->postcode,
                'phone' => $this->booking->serviceLocation->phone,
                'notes' => $this->booking->serviceLocation->notes,
                'full_address' => $this->formatFullAddress($this->booking->serviceLocation),
                'directions' => $this->booking->serviceLocation->directions,
                'parking_info' => $this->booking->serviceLocation->parking_info,
                'access_instructions' => $this->booking->serviceLocation->access_instructions,
            ] : null,

            'vendor' => $this->booking->service->vendor ? [
                'name' => $this->booking->service->vendor->name,
                'email' => $this->booking->service->vendor->email,
                'phone' => $this->booking->service->vendor->phone,
            ] : null,

            'add_ons' => $this->booking->bookingAddOns->map(function ($addOn) {
                return [
                    'name' => $addOn->serviceAddOn->name,
                    'quantity' => $addOn->quantity,
                    'description' => $addOn->serviceAddOn->description,
                    'price' => $this->formatPrice($addOn->total_price),
                ];
            })->toArray(),

            'consultation' => $this->booking->requires_consultation ? [
                'duration' => $this->booking->service->consultation_duration_minutes,
                'duration_display' => $this->formatDuration($this->booking->service->consultation_duration_minutes ?? 30),
                'notes' => 'Please be available for a brief consultation before we begin setup.',
                'is_completed' => $this->booking->consultation_completed_at !== null,
                'completed_at' => $this->booking->consultation_completed_at?->format('l, F j, Y \a\t g:i A'),
                'may_need_rescheduling' => !$this->booking->consultation_completed_at && $this->isSignificantChange(),
            ] : null,

            'next_steps' => $this->getNextSteps(),
            'important_notes' => $this->getImportantNotes(),
            'calendar_info' => $this->getCalendarInfo(),
            'contact_info' => $this->getContactInfo(),
            'cancellation_policy' => $this->booking->service->cancellation_policy,
        ];
    }

    /**
     * Get time difference description
     */
    private function getTimeDifference(): ?string
    {
        if (!$this->originalDateTime) {
            return null;
        }

        $diff = $this->originalDateTime->diffInMinutes($this->booking->scheduled_at);
        $hours = floor($diff / 60);
        $minutes = $diff % 60;

        if ($diff < 60) {
            return "{$minutes} minutes";
        } elseif ($diff < 1440) { // Less than 24 hours
            return $hours > 0 && $minutes > 0 ?
                "{$hours} hours and {$minutes} minutes" :
                "{$hours} hours";
        } else {
            $days = floor($diff / 1440);
            $remainingHours = floor(($diff % 1440) / 60);
            return $remainingHours > 0 ?
                "{$days} days and {$remainingHours} hours" :
                "{$days} days";
        }
    }

    /**
     * Get date difference description
     */
    private function getDateDifference(): ?string
    {
        if (!$this->originalDateTime) {
            return null;
        }

        $daysDiff = $this->originalDateTime->diffInDays($this->booking->scheduled_at);

        if ($daysDiff === 0) {
            return 'same day';
        } elseif ($daysDiff === 1) {
            return $this->isEarlierTime() ? '1 day earlier' : '1 day later';
        } else {
            return $this->isEarlierTime() ?
                "{$daysDiff} days earlier" :
                "{$daysDiff} days later";
        }
    }

    /**
     * Check if new time is earlier than original
     */
    private function isEarlierTime(): bool
    {
        if (!$this->originalDateTime) {
            return false;
        }

        return $this->booking->scheduled_at->lt($this->originalDateTime);
    }

    /**
     * Check if new time is later than original
     */
    private function isLaterTime(): bool
    {
        if (!$this->originalDateTime) {
            return false;
        }

        return $this->booking->scheduled_at->gt($this->originalDateTime);
    }

    /**
     * Check if it's the same day
     */
    private function isSameDay(): bool
    {
        if (!$this->originalDateTime) {
            return false;
        }

        return $this->booking->scheduled_at->isSameDay($this->originalDateTime);
    }

    /**
     * Get number of days moved
     */
    private function getDaysMoved(): int
    {
        if (!$this->originalDateTime) {
            return 0;
        }

        return abs($this->originalDateTime->diffInDays($this->booking->scheduled_at));
    }

    /**
     * Check if this is a significant change that might affect consultation
     */
    private function isSignificantChange(): bool
    {
        if (!$this->originalDateTime) {
            return false;
        }

        // Consider significant if moved by more than 2 days or different time of day
        $daysDiff = abs($this->originalDateTime->diffInDays($this->booking->scheduled_at));
        $hoursDiff = abs($this->originalDateTime->diffInHours($this->booking->scheduled_at)) % 24;

        return $daysDiff > 2 || $hoursDiff > 4;
    }

    /**
     * Get next steps for the client
     */
    private function getNextSteps(): array
    {
        $steps = [];

        $steps[] = 'Update your calendar with the new appointment time';
        $steps[] = 'Confirm your availability for the new date and time';

        if ($this->booking->serviceLocation) {
            $steps[] = 'Review the location details and directions';
        }

        if ($this->booking->requires_consultation && !$this->booking->consultation_completed_at) {
            if ($this->isSignificantChange()) {
                $steps[] = 'We may need to reschedule your consultation to discuss any changes';
            } else {
                $steps[] = 'Your consultation details remain the same';
            }
        }

        if ($this->booking->special_requirements) {
            $steps[] = 'Ensure your special requirements are still applicable';
        }

        $steps[] = 'Contact us if you have any questions about the new schedule';

        return $steps;
    }

    /**
     * Get important notes about the rescheduling
     */
    private function getImportantNotes(): array
    {
        $notes = [];

        if ($this->rescheduledBy === 'admin' || $this->rescheduledBy === 'vendor') {
            $notes[] = 'This rescheduling was initiated by our team due to operational requirements';
        }

        if ($this->isSignificantChange()) {
            $notes[] = 'Due to the significant schedule change, please review all booking details carefully';
        }

        if ($this->booking->payment_status === 'deposit_paid') {
            $notes[] = 'Your deposit remains valid for the new appointment date';
        }

        if ($this->booking->serviceLocation && $this->getDaysMoved() > 0) {
            $notes[] = 'Please reconfirm venue availability and access arrangements for the new date';
        }

        if (empty($notes)) {
            $notes[] = 'All other booking details remain unchanged';
        }

        return $notes;
    }

    /**
     * Get calendar information for easy updating
     */
    private function getCalendarInfo(): array
    {
        return [
            'event_title' => "{$this->booking->service->name} - {$this->booking->booking_reference}",
            'start_date' => $this->booking->scheduled_at->format('Y-m-d'),
            'start_time' => $this->booking->scheduled_at->format('H:i'),
            'end_time' => $this->booking->ends_at->format('H:i'),
            'duration_minutes' => $this->booking->duration_minutes,
            'location_summary' => $this->booking->serviceLocation ?
                $this->booking->serviceLocation->name : 'To be confirmed',
            'description' => "Rescheduled booking for {$this->booking->service->name}. " .
                ($this->rescheduleReason ? "Reason: {$this->rescheduleReason}" : ''),
        ];
    }

    /**
     * Get contact information for questions
     */
    private function getContactInfo(): array
    {
        return [
            'support_email' => config('mail.from.address'),
            'support_phone' => config('app.support_phone', 'Available in your account'),
            'booking_reference' => $this->booking->booking_reference,
            'response_time' => 'We typically respond within 2 hours during business hours',
            'urgent_contact' => 'For urgent changes, please call us directly',
        ];
    }
}

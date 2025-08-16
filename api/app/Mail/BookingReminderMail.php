<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class BookingReminderMail extends BaseSystemMail
{
    use Queueable, SerializesModels;

    protected string $templateName = 'booking.reminder';

    private Booking $booking;
    private int $hoursUntilBooking;
    private string $reminderType;

    public function __construct(Booking $booking, int $hoursUntilBooking = 24, string $reminderType = 'standard')
    {
        $this->booking = $booking;
        $this->hoursUntilBooking = $hoursUntilBooking;
        $this->reminderType = $reminderType; // 'standard', 'final', 'same_day'

        // Prepare email data using the booking
        parent::__construct($this->prepareEmailData());
    }

    protected function getSubject(): string
    {
        $timeText = $this->getTimeUntilText();
        return "Reminder: Your balloon decoration service {$timeText} - #{$this->booking->booking_reference}";
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
                'time_until' => $this->getTimeUntilText(),
                'countdown' => $this->getCountdownDetails(),
            ],
            'service' => [
                'name' => $this->booking->service->name,
                'description' => $this->booking->service->description,
                'short_description' => $this->booking->service->short_description,
                'duration_display' => $this->formatDuration($this->booking->service->duration_minutes),
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
                ];
            })->toArray(),
            'consultation' => $this->booking->requires_consultation ? [
                'duration' => $this->booking->service->consultation_duration_minutes,
                'duration_display' => $this->formatDuration($this->booking->service->consultation_duration_minutes ?? 30),
                'notes' => 'Please be available for a brief consultation before we begin setup.',
            ] : null,
            'reminder' => [
                'type' => $this->reminderType,
                'hours_until' => $this->hoursUntilBooking,
                'urgency_level' => $this->getUrgencyLevel(),
                'preparation_time' => $this->getPreparationTime(),
                'checklist' => $this->getPreparationChecklist(),
            ],
            'weather' => $this->getWeatherConsiderations(),
            'contact' => [
                'emergency_phone' => config('app.emergency_phone', config('app.phone', '')),
                'whatsapp' => config('app.whatsapp', ''),
                'support_hours' => 'Monday-Friday 9 AM - 6 PM, Saturday 10 AM - 4 PM',
            ],
            'actions' => [
                'can_reschedule' => $this->canReschedule(),
                'can_cancel' => $this->canCancel(),
                'reschedule_deadline' => $this->getRescheduleDeadline(),
                'cancel_deadline' => $this->getCancelDeadline(),
            ],
            'company' => [
                'name' => config('app.name'),
                'email' => config('mail.from.address'),
                'phone' => config('app.phone', ''),
                'website' => config('app.url'),
            ],
        ];
    }

    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours === 1 ? "1 hour" : "{$hours} hours";
        }

        $hoursText = $hours === 1 ? "1 hour" : "{$hours} hours";
        $minutesText = "{$remainingMinutes} minutes";

        return "{$hoursText} {$minutesText}";
    }

    private function formatFullAddress($location): string
    {
        $parts = array_filter([
            $location->address,
            $location->city,
            $location->postcode,
        ]);

        return implode(', ', $parts);
    }

    private function getTimeUntilText(): string
    {
        if ($this->hoursUntilBooking <= 2) {
            return 'is in 2 hours';
        } elseif ($this->hoursUntilBooking <= 6) {
            return 'is in ' . $this->hoursUntilBooking . ' hours';
        } elseif ($this->hoursUntilBooking <= 24) {
            return 'is tomorrow';
        } else {
            $days = ceil($this->hoursUntilBooking / 24);
            return $days === 1 ? 'is tomorrow' : "is in {$days} days";
        }
    }

    private function getCountdownDetails(): array
    {
        $now = Carbon::now();
        $scheduledAt = $this->booking->scheduled_at;

        $diff = $now->diff($scheduledAt);

        return [
            'days' => $diff->days,
            'hours' => $diff->h,
            'minutes' => $diff->i,
            'total_hours' => $this->hoursUntilBooking,
            'is_urgent' => $this->hoursUntilBooking <= 6,
            'is_same_day' => $this->hoursUntilBooking <= 24,
        ];
    }

    private function getUrgencyLevel(): string
    {
        if ($this->hoursUntilBooking <= 2) {
            return 'high';
        } elseif ($this->hoursUntilBooking <= 6) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function getPreparationTime(): string
    {
        if ($this->hoursUntilBooking <= 2) {
            return 'Please prepare now';
        } elseif ($this->hoursUntilBooking <= 6) {
            return 'Start preparing soon';
        } else {
            return 'You have time to prepare';
        }
    }

    private function getPreparationChecklist(): array
    {
        $checklist = [
            'Ensure access to the venue is available',
            'Clear the decoration area of any obstacles',
            'Have contact numbers readily available',
        ];

        if ($this->booking->serviceLocation) {
            $checklist[] = 'Verify the address and parking arrangements';
        }

        if ($this->booking->requires_consultation) {
            $checklist[] = 'Be available for the consultation call';
        }

        if ($this->booking->payment_status === 'pending') {
            array_unshift($checklist, 'Complete your payment to confirm the booking');
        }

        if ($this->booking->special_requirements) {
            $checklist[] = 'Prepare any specific requirements mentioned in your booking';
        }

        // Add weather-related items for outdoor events
        if ($this->booking->serviceLocation &&
            (stripos($this->booking->serviceLocation->notes, 'outdoor') !== false ||
                stripos($this->booking->notes, 'outdoor') !== false)) {
            $checklist[] = 'Check weather conditions and have backup plans ready';
        }

        return $checklist;
    }

    private function getWeatherConsiderations(): ?array
    {
        // Check if this might be an outdoor event
        $isOutdoor = false;

        if ($this->booking->serviceLocation) {
            $isOutdoor = stripos($this->booking->serviceLocation->notes, 'outdoor') !== false ||
                stripos($this->booking->serviceLocation->notes, 'garden') !== false ||
                stripos($this->booking->serviceLocation->notes, 'park') !== false;
        }

        if (stripos($this->booking->notes, 'outdoor') !== false ||
            stripos($this->booking->notes, 'garden') !== false ||
            stripos($this->booking->notes, 'park') !== false) {
            $isOutdoor = true;
        }

        if (!$isOutdoor) {
            return null;
        }

        return [
            'is_outdoor_event' => true,
            'considerations' => [
                'Check weather forecast for your event time',
                'Have a backup indoor location ready if needed',
                'Strong winds can affect balloon arrangements',
                'Rain may require additional protection for decorations',
            ],
            'contact_advice' => 'Contact us if weather conditions look unfavorable - we can discuss alternatives or rescheduling.',
        ];
    }

    private function canReschedule(): bool
    {
        return $this->hoursUntilBooking >= 24 &&
            in_array($this->booking->status, ['pending', 'confirmed']);
    }

    private function canCancel(): bool
    {
        return $this->hoursUntilBooking >= 2 &&
            in_array($this->booking->status, ['pending', 'confirmed']);
    }

    private function getRescheduleDeadline(): string
    {
        return '24 hours before your booking time';
    }

    private function getCancelDeadline(): string
    {
        return '24 hours before your booking time for full refund';
    }
}

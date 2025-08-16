<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class BookingConfirmationMail extends BaseSystemMail
{
    use Queueable, SerializesModels;

    protected string $templateName = 'booking.confirmation';

    private Booking $booking;

    public function __construct(Booking $booking)
    {
        $this->booking = $booking;

        // Prepare email data using the booking
        parent::__construct($this->prepareEmailData());
    }

    protected function getSubject(): string
    {
        return "Booking Confirmation - {$this->booking->service->name} (#{$this->booking->booking_reference})";
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
                'base_price' => $this->formatPrice($this->booking->base_price),
                'payment_status' => ucfirst($this->booking->payment_status),
                'requires_consultation' => $this->booking->requires_consultation,
                'notes' => $this->booking->notes,
                'special_requirements' => $this->booking->special_requirements,
                'cancellation_policy' => $this->booking->service->cancellation_policy,
            ],
            'service' => [
                'name' => $this->booking->service->name,
                'description' => $this->booking->service->description,
                'short_description' => $this->booking->service->short_description,
                'duration_display' => $this->formatDuration($this->booking->service->duration_minutes),
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
                    'unit_price' => $this->formatPrice($addOn->unit_price),
                    'total_price' => $this->formatPrice($addOn->total_price),
                ];
            })->toArray(),
            'consultation' => $this->booking->requires_consultation ? [
                'duration' => $this->booking->service->consultation_duration_minutes,
                'duration_display' => $this->formatDuration($this->booking->service->consultation_duration_minutes ?? 30),
                'notes' => 'Please arrive 15 minutes early for your consultation.',
            ] : null,
            'next_steps' => $this->getNextSteps(),
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

    private function getNextSteps(): array
    {
        $steps = [];

        if ($this->booking->status === 'pending') {
            $steps[] = 'We will review your booking and send a confirmation within 24 hours.';
        }

        if ($this->booking->payment_status === 'pending') {
            $steps[] = 'Please complete your payment to secure your booking.';
        }

        if ($this->booking->requires_consultation) {
            $steps[] = 'We will contact you to schedule a consultation before your service.';
        }

        $steps[] = 'You will receive a reminder 24 hours before your appointment.';

        if ($this->booking->serviceLocation) {
            $steps[] = 'Please arrive 10 minutes early to allow time for setup.';
        }

        return $steps;
    }
}

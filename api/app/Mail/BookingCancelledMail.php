<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;

class BookingCancelledMail extends BaseSystemMail
{
    use Queueable, SerializesModels;

    protected string $templateName = 'booking.cancelled';

    private Booking $booking;
    private ?string $cancellationReason;
    private ?string $cancelledBy;

    public function __construct(Booking $booking, ?string $cancellationReason = null, ?string $cancelledBy = null)
    {
        $this->booking = $booking;
        $this->cancellationReason = $cancellationReason;
        $this->cancelledBy = $cancelledBy;

        // Prepare email data using the booking
        parent::__construct($this->prepareEmailData());
    }

    protected function getSubject(): string
    {
        return "Booking Cancelled - {$this->booking->service->name} (#{$this->booking->booking_reference})";
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
                'cancelled_at' => $this->booking->cancelled_at?->format('l, F j, Y \a\t g:i A'),
                'cancellation_reason' => $this->cancellationReason ?: $this->booking->cancellation_reason,
                'notes' => $this->booking->notes,
                'special_requirements' => $this->booking->special_requirements,
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
            'cancellation' => [
                'reason' => $this->cancellationReason ?: $this->booking->cancellation_reason ?: 'No reason provided',
                'cancelled_by' => $this->cancelledBy ?: $this->determineCancelledBy(),
                'cancelled_at' => $this->booking->cancelled_at?->format('l, F j, Y \a\t g:i A') ?: now()->format('l, F j, Y \a\t g:i A'),
                'refund_info' => $this->getRefundInfo(),
                'policy_applies' => $this->checkCancellationPolicy(),
            ],
            'refund' => $this->getRefundDetails(),
            'rebooking' => [
                'encouraged' => true,
                'discount_offered' => $this->shouldOfferDiscount(),
                'contact_info' => [
                    'email' => config('mail.from.address'),
                    'phone' => config('app.phone', ''),
                ],
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

    private function determineCancelledBy(): string
    {
        // Logic to determine who cancelled the booking
        if ($this->booking->cancelled_at && $this->booking->updated_at) {
            // Check if cancelled within business hours (more likely admin)
            $cancelTime = $this->booking->cancelled_at;
            $isBusinessHours = $cancelTime->hour >= 9 && $cancelTime->hour <= 17;

            if ($isBusinessHours) {
                return 'Our team';
            }
        }

        return 'Customer request';
    }

    private function getRefundInfo(): string
    {
        if ($this->booking->payment_status === 'pending') {
            return 'No payment was processed, so no refund is required.';
        }

        if ($this->booking->payment_status === 'paid') {
            $hoursUntilService = $this->booking->scheduled_at->diffInHours(now());

            if ($hoursUntilService >= 48) {
                return 'You are eligible for a full refund, which will be processed within 5-7 business days.';
            } elseif ($hoursUntilService >= 24) {
                return 'You are eligible for a partial refund (50%), which will be processed within 5-7 business days.';
            } else {
                return 'Due to our cancellation policy, no refund is available for cancellations within 24 hours.';
            }
        }

        return 'Refund eligibility will be reviewed based on our cancellation policy.';
    }

    private function checkCancellationPolicy(): bool
    {
        $hoursUntilService = $this->booking->scheduled_at->diffInHours(now());
        return $hoursUntilService < 24; // Policy applies if cancelled within 24 hours
    }

    private function getRefundDetails(): array
    {
        $refundAmount = 0;
        $refundPercentage = 0;
        $refundStatus = 'not_applicable';
        $processingTime = '5-7 business days';

        if ($this->booking->payment_status === 'paid') {
            $hoursUntilService = $this->booking->scheduled_at->diffInHours(now());

            if ($hoursUntilService >= 48) {
                $refundPercentage = 100;
                $refundAmount = $this->booking->total_amount;
                $refundStatus = 'full_refund';
            } elseif ($hoursUntilService >= 24) {
                $refundPercentage = 50;
                $refundAmount = $this->booking->total_amount / 2;
                $refundStatus = 'partial_refund';
            } else {
                $refundPercentage = 0;
                $refundAmount = 0;
                $refundStatus = 'no_refund';
                $processingTime = 'N/A';
            }
        }

        return [
            'status' => $refundStatus,
            'percentage' => $refundPercentage,
            'amount' => $this->formatPrice($refundAmount),
            'original_amount' => $this->formatPrice($this->booking->total_amount),
            'processing_time' => $processingTime,
            'payment_status' => $this->booking->payment_status,
        ];
    }

    private function shouldOfferDiscount(): bool
    {
        // Offer discount if cancelled by business or due to circumstances beyond customer control
        $businessCancellation = str_contains(strtolower($this->cancellationReason ?: ''), 'unavailable') ||
            str_contains(strtolower($this->cancellationReason ?: ''), 'emergency') ||
            str_contains(strtolower($this->cancellationReason ?: ''), 'weather');

        return $businessCancellation;
    }
}

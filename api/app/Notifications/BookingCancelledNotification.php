<?php

namespace App\Notifications;

use App\Mail\BookingCancelledMail;
use App\Models\Booking;
use App\Services\V1\Notifications\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Carbon\Carbon;

class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public string $reason;
    public array $cancellationDetails;
    public array $variables;
    public array $options;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Booking $booking,
        string $reason = '',
        array $cancellationDetails = [],
        array $variables = [],
        array $options = []
    ) {
        $this->booking = $booking;
        $this->reason = $reason;
        $this->cancellationDetails = $cancellationDetails;
        $this->variables = $variables;
        $this->options = $options;

        // Set high priority queue for cancellations
        $this->onQueue(config('notifications.queues.high', 'notifications-high'));

        // Set delay if specified (usually sent immediately)
        if (isset($options['delay'])) {
            $this->delay($options['delay']);
        }
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = [];

        // Get user's preferred channels for booking updates
        $userPreferences = $notifiable->notification_preferences ?? [];
        $updatePreferences = $userPreferences['booking_updates'] ?? ['mail', 'database'];

        // Always include database for in-app notifications
        $channels[] = 'database';

        // Always include email for cancellations (critical information)
        $channels[] = 'mail';

        // Add SMS for urgent cancellations or if user has opted in
        if (in_array('sms', $updatePreferences) || $this->shouldSendSms()) {
            $channels[] = \App\Notifications\Channels\SmsChannel::class;
        }

        // Add push notifications if user has opted in
        if (in_array('push', $updatePreferences)) {
            $channels[] = \App\Notifications\Channels\PushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): BookingCancelledMail
    {
        // Ensure variables are populated
        if (empty($this->variables)) {
            $templateService = app(NotificationTemplateService::class);
            $this->variables = $templateService->getBookingVariables($this->booking);
        }

        // Add cancellation-specific variables
        $this->variables['cancellation_reason'] = $this->reason;
        $this->variables['cancelled_at'] = $this->booking->cancelled_at?->format('F j, Y \a\t g:i A') ?? now()->format('F j, Y \a\t g:i A');
        $this->variables['cancelled_by'] = $this->getCancelledByText();
        $this->variables['refund_information'] = $this->getRefundInformation();
        $this->variables['next_steps'] = $this->getNextSteps();

        return new BookingCancelledMail($this->booking, $this->variables);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): array
    {
        $serviceName = $this->booking->service->name;
        $bookingRef = $this->booking->booking_reference;
        $scheduledDate = $this->booking->scheduled_at->format('M j');

        $message = "Your {$serviceName} booking #{$bookingRef} for {$scheduledDate} has been cancelled.";

        // Add reason if provided and not too long
        if ($this->reason && strlen($this->reason) < 50) {
            $message .= " Reason: {$this->reason}.";
        }

        // Add refund information
        $refundInfo = $this->getRefundInformation();
        if ($refundInfo['eligible']) {
            if ($refundInfo['amount'] > 0) {
                $refundAmount = number_format($refundInfo['amount'] / 100, 2);
                $message .= " Refund of £{$refundAmount} will be processed within {$refundInfo['processing_days']} business days.";
            } else {
                $message .= " No refund applicable.";
            }
        }

        $message .= " Contact us if you need assistance. Reply STOP to opt out.";

        return [
            'message' => $message,
            'booking_id' => $this->booking->id,
            'variables' => $this->variables,
            'options' => [
                'urgent' => $this->isUrgentCancellation(),
                'high_priority' => true,
            ],
        ];
    }

    /**
     * Get the push notification representation.
     */
    public function toPush($notifiable): array
    {
        $serviceName = $this->booking->service->name;
        $scheduledDate = $this->booking->scheduled_at->format('M j');

        $title = '❌ Booking Cancelled';
        $body = "Your {$serviceName} service for {$scheduledDate} has been cancelled.";

        // Add refund information if applicable
        $refundInfo = $this->getRefundInformation();
        if ($refundInfo['eligible'] && $refundInfo['amount'] > 0) {
            $refundAmount = number_format($refundInfo['amount'] / 100, 2);
            $body .= " Refund of £{$refundAmount} will be processed soon.";
        }

        return [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/booking-cancelled-icon.png',
            'click_action' => url("/bookings/{$this->booking->id}"),
            'booking_id' => $this->booking->id,
            'data' => [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'service_name' => $serviceName,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
                'cancelled_at' => $this->booking->cancelled_at?->toISOString() ?? now()->toISOString(),
                'cancellation_reason' => $this->reason,
                'refund_eligible' => $refundInfo['eligible'],
                'refund_amount' => $refundInfo['amount'],
            ],
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable): DatabaseMessage
    {
        $serviceName = $this->booking->service->name;
        $scheduledDateTime = $this->booking->scheduled_at->format('l, F j, Y \a\t g:i A');

        $title = 'Booking Cancelled';
        $message = "Your {$serviceName} service scheduled for {$scheduledDateTime} has been cancelled.";

        // Add reason if provided
        if ($this->reason) {
            $message .= " Reason: {$this->reason}.";
        }

        // Add refund information
        $refundInfo = $this->getRefundInformation();
        if ($refundInfo['eligible'] && $refundInfo['amount'] > 0) {
            $refundAmount = number_format($refundInfo['amount'] / 100, 2);
            $message .= " A refund of £{$refundAmount} will be processed within {$refundInfo['processing_days']} business days.";
        }

        return new DatabaseMessage([
            'type' => 'booking_cancelled',
            'title' => $title,
            'message' => $message,
            'action_text' => 'View Details',
            'action_url' => url("/bookings/{$this->booking->id}"),
            'data' => [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'service_name' => $serviceName,
                'service_id' => $this->booking->service_id,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
                'cancelled_at' => $this->booking->cancelled_at?->toISOString() ?? now()->toISOString(),
                'cancellation_reason' => $this->reason,
                'cancelled_by' => $this->getCancelledByText(),
                'refund_information' => $refundInfo,
                'next_steps' => $this->getNextSteps(),
                'cancellation_details' => $this->cancellationDetails,
            ],
            'importance' => 'high',
        ]);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'service_name' => $this->booking->service->name,
            'service_id' => $this->booking->service_id,
            'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            'cancelled_at' => $this->booking->cancelled_at?->toISOString() ?? now()->toISOString(),
            'cancellation_reason' => $this->reason,
            'cancelled_by' => $this->getCancelledByText(),
            'refund_information' => $this->getRefundInformation(),
            'next_steps' => $this->getNextSteps(),
            'cancellation_details' => $this->cancellationDetails,
            'variables' => $this->variables,
        ];
    }

    /**
     * Determine if SMS should be sent
     */
    private function shouldSendSms(): bool
    {
        // Send SMS for urgent cancellations (within 24 hours)
        if ($this->isUrgentCancellation()) {
            return true;
        }

        // Send SMS for high-value bookings
        if ($this->booking->total_amount > 50000) { // £500+
            return true;
        }

        // Send SMS if explicitly requested
        if ($this->options['send_sms'] ?? false) {
            return true;
        }

        return false;
    }

    /**
     * Check if this is an urgent cancellation
     */
    private function isUrgentCancellation(): bool
    {
        // Urgent if booking is within 24 hours
        return $this->booking->scheduled_at->diffInHours(now()) <= 24;
    }

    /**
     * Get who cancelled the booking
     */
    private function getCancelledByText(): string
    {
        $cancelledBy = $this->cancellationDetails['cancelled_by'] ?? 'system';

        return match($cancelledBy) {
            'customer' => 'You cancelled this booking',
            'admin' => 'We cancelled this booking',
            'system' => 'This booking was cancelled automatically',
            default => 'This booking was cancelled'
        };
    }

    /**
     * Get refund information
     */
    private function getRefundInformation(): array
    {
        $refundInfo = [
            'eligible' => false,
            'amount' => 0,
            'processing_days' => 5,
            'method' => 'original_payment_method',
            'policy' => $this->getCancellationPolicy(),
        ];

        // Check if payments were made
        $totalPaid = $this->booking->total_amount - ($this->booking->remaining_amount ?? 0);

        if ($totalPaid <= 0) {
            return $refundInfo; // No payment made, no refund needed
        }

        $refundInfo['eligible'] = true;

        // Calculate refund amount based on cancellation timing and policy
        $hoursUntilBooking = $this->booking->scheduled_at->diffInHours(now());

        if ($hoursUntilBooking >= 48) {
            // Full refund if cancelled 48+ hours in advance
            $refundInfo['amount'] = $totalPaid;
        } elseif ($hoursUntilBooking >= 24) {
            // 50% refund if cancelled 24-48 hours in advance
            $refundInfo['amount'] = intval($totalPaid * 0.5);
        } elseif ($hoursUntilBooking >= 2) {
            // 25% refund if cancelled 2-24 hours in advance
            $refundInfo['amount'] = intval($totalPaid * 0.25);
        } else {
            // No refund if cancelled less than 2 hours before
            $refundInfo['amount'] = 0;
        }

        // Admin cancellations typically get full refund
        if (($this->cancellationDetails['cancelled_by'] ?? '') === 'admin') {
            $refundInfo['amount'] = $totalPaid;
            $refundInfo['processing_days'] = 3;
        }

        // System cancellations (e.g., due to issues) get full refund
        if (($this->cancellationDetails['cancelled_by'] ?? '') === 'system') {
            $refundInfo['amount'] = $totalPaid;
            $refundInfo['processing_days'] = 3;
        }

        return $refundInfo;
    }

    /**
     * Get cancellation policy text
     */
    private function getCancellationPolicy(): string
    {
        return "Free cancellation up to 48 hours before your service. " .
            "Cancellations within 24-48 hours: 50% refund. " .
            "Cancellations within 2-24 hours: 25% refund. " .
            "Cancellations within 2 hours: No refund.";
    }

    /**
     * Get next steps for the customer
     */
    private function getNextSteps(): array
    {
        $steps = [];

        // Refund steps
        $refundInfo = $this->getRefundInformation();
        if ($refundInfo['eligible'] && $refundInfo['amount'] > 0) {
            $refundAmount = number_format($refundInfo['amount'] / 100, 2);
            $steps[] = [
                'title' => 'Refund Processing',
                'description' => "Your refund of £{$refundAmount} will be processed within {$refundInfo['processing_days']} business days to your original payment method",
                'priority' => 'high',
                'timeline' => "{$refundInfo['processing_days']} business days",
            ];
        }

        // Rebooking steps
        $steps[] = [
            'title' => 'Reschedule Your Service',
            'description' => 'Book a new appointment at a time that works for you',
            'action_text' => 'Book Again',
            'action_url' => url('/services/' . $this->booking->service_id),
            'priority' => 'medium',
        ];

        // Contact steps if issues
        if ($this->reason && str_contains(strtolower($this->reason), ['issue', 'problem', 'unavailable'])) {
            $steps[] = [
                'title' => 'Get Support',
                'description' => 'Contact our team if you need assistance or have questions about the cancellation',
                'action_text' => 'Contact Support',
                'action_url' => url('/contact'),
                'priority' => 'medium',
            ];
        }

        // Alternative services
        if ($this->booking->service) {
            $steps[] = [
                'title' => 'Explore Alternative Services',
                'description' => 'Browse our other services that might meet your needs',
                'action_text' => 'View Services',
                'action_url' => url('/services'),
                'priority' => 'low',
            ];
        }

        return $steps;
    }

    /**
     * Get notification priority
     */
    public function getPriority(): string
    {
        return 'high'; // Cancellations are always high priority
    }

    /**
     * Check if notification should be sent immediately
     */
    public function shouldSendImmediately(): bool
    {
        return $this->options['immediate'] ?? true; // Cancellations should be immediate
    }

    /**
     * Get delay for SMS sending
     */
    public function getSmsDelay(): ?Carbon
    {
        // Small delay to ensure email is sent first
        return now()->addMinutes(3);
    }

    /**
     * Get delay for push notification
     */
    public function getPushDelay(): ?Carbon
    {
        // Small delay to ensure email is sent first
        return now()->addMinutes(1);
    }

    /**
     * Get unique identifier for this notification
     */
    public function getUniqueId(): string
    {
        return "booking_cancelled_{$this->booking->id}";
    }

    /**
     * Get notification tags for filtering
     */
    public function getTags(): array
    {
        return [
            'booking_cancellation',
            "booking:{$this->booking->id}",
            "user:{$this->booking->user_id}",
            "service:{$this->booking->service_id}",
            "cancelled_by:" . ($this->cancellationDetails['cancelled_by'] ?? 'unknown'),
            "urgent:" . ($this->isUrgentCancellation() ? 'yes' : 'no'),
            "refund:" . ($this->getRefundInformation()['eligible'] ? 'eligible' : 'not_eligible'),
        ];
    }

    /**
     * Handle notification failure
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Booking cancellation notification failed', [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'cancellation_reason' => $this->reason,
            'error' => $exception->getMessage(),
        ]);

        // Cancellation failures are critical - escalate immediately
        \Log::critical('Booking cancellation notification failed - manual intervention required', [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'client_name' => $this->booking->client_name,
            'client_email' => $this->booking->client_email,
            'client_phone' => $this->booking->client_phone,
            'service_name' => $this->booking->service->name,
            'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            'cancellation_reason' => $this->reason,
            'refund_amount' => $this->getRefundInformation()['amount'],
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Determine if notification should be unique
     */
    public function shouldBeUnique(): bool
    {
        return true;
    }

    /**
     * Get the unique identifier for preventing duplicate notifications
     */
    public function uniqueId(): string
    {
        return $this->getUniqueId();
    }

    /**
     * Check if notification is still relevant
     */
    public function isRelevant(): bool
    {
        // Check if booking still exists
        if (!$this->booking) {
            return false;
        }

        // Check if booking is actually cancelled
        if ($this->booking->status !== 'cancelled') {
            return false;
        }

        return true;
    }

    /**
     * Get customer support information
     */
    private function getSupportInformation(): array
    {
        return [
            'email' => config('mail.from.address'),
            'phone' => config('app.support_phone', '+44 20 3890 2370'),
            'hours' => 'Monday to Friday, 9am to 6pm',
            'response_time' => '24 hours',
            'emergency_contact' => config('app.emergency_contact'),
        ];
    }

    /**
     * Get notification metadata for tracking
     */
    public function getMetadata(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'service_id' => $this->booking->service_id,
            'user_id' => $this->booking->user_id,
            'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            'cancelled_at' => $this->booking->cancelled_at?->toISOString() ?? now()->toISOString(),
            'cancellation_reason' => $this->reason,
            'cancelled_by' => $this->cancellationDetails['cancelled_by'] ?? 'unknown',
            'total_amount' => $this->booking->total_amount,
            'refund_information' => $this->getRefundInformation(),
            'urgent_cancellation' => $this->isUrgentCancellation(),
            'hours_before_service' => $this->booking->scheduled_at->diffInHours(now()),
        ];
    }
}

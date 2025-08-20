<?php

namespace App\Notifications;

use App\Mail\BookingConfirmationMail;
use App\Models\Booking;
use App\Services\V1\Notifications\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Carbon\Carbon;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public array $variables;
    public array $options;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Booking $booking,
        array $variables = [],
        array $options = []
    ) {
        $this->booking = $booking;
        $this->variables = $variables;
        $this->options = $options;

        // Set high priority queue for confirmations
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

        // Get user's preferred channels for booking confirmations
        $userPreferences = $notifiable->notification_preferences ?? [];
        $confirmationPreferences = $userPreferences['booking_confirmations'] ?? ['mail', 'database'];

        // Always include database for in-app notifications
        $channels[] = 'database';

        // Always include email for confirmations (high priority)
        $channels[] = 'mail';

        // Add SMS if user has opted in or for high-value bookings
        if (in_array('sms', $confirmationPreferences) || $this->shouldSendSms()) {
            $channels[] = \App\Notifications\Channels\SmsChannel::class;
        }

        // Add push notifications if user has opted in
        if (in_array('push', $confirmationPreferences)) {
            $channels[] = \App\Notifications\Channels\PushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): BookingConfirmationMail
    {
        // Ensure variables are populated
        if (empty($this->variables)) {
            $templateService = app(NotificationTemplateService::class);
            $this->variables = $templateService->getBookingVariables($this->booking);
        }

        // Add confirmation-specific variables
        $this->variables['confirmation_sent_at'] = now()->format('F j, Y \a\t g:i A');
        $this->variables['booking_confirmed'] = true;
        $this->variables['next_steps'] = $this->getNextSteps();

        return new BookingConfirmationMail($this->booking, $this->variables);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): array
    {
        $serviceName = $this->booking->service->name;
        $bookingRef = $this->booking->booking_reference;
        $scheduledDate = $this->booking->scheduled_at->format('M j');
        $scheduledTime = $this->booking->scheduled_at->format('g:i A');

        $message = "Booking confirmed! Your {$serviceName} service #{$bookingRef} is scheduled for {$scheduledDate} at {$scheduledTime}.";

        // Add payment information if pending
        if ($this->booking->payment_status === 'pending' && $this->booking->remaining_amount > 0) {
            $remainingAmount = number_format($this->booking->remaining_amount / 100, 2);
            $message .= " Outstanding payment: £{$remainingAmount}.";
        }

        // Add consultation information if required
        if ($this->booking->requires_consultation) {
            $message .= " A consultation will be scheduled separately.";
        }

        $message .= " We'll send you a reminder closer to the date. Reply STOP to opt out.";

        return [
            'message' => $message,
            'booking_id' => $this->booking->id,
            'variables' => $this->variables,
            'options' => [
                'urgent' => false,
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

        $title = '🎉 Booking Confirmed!';
        $body = "Your {$serviceName} service is confirmed for {$scheduledDate}. We'll be in touch with more details soon.";

        return [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/booking-confirmed-icon.png',
            'click_action' => url("/bookings/{$this->booking->id}"),
            'booking_id' => $this->booking->id,
            'data' => [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'service_name' => $serviceName,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
                'status' => $this->booking->status,
                'payment_status' => $this->booking->payment_status,
                'total_amount' => $this->booking->total_amount,
                'requires_consultation' => $this->booking->requires_consultation,
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

        $title = 'Booking Confirmed';
        $message = "Your {$serviceName} service has been confirmed for {$scheduledDateTime}.";

        // Add payment reminder if needed
        if ($this->booking->payment_status === 'pending' && $this->booking->remaining_amount > 0) {
            $remainingAmount = number_format($this->booking->remaining_amount / 100, 2);
            $message .= " Outstanding payment: £{$remainingAmount}.";
        }

        // Add consultation information if required
        if ($this->booking->requires_consultation) {
            $message .= " A consultation will be scheduled to discuss your requirements.";
        }

        return new DatabaseMessage([
            'type' => 'booking_confirmed',
            'title' => $title,
            'message' => $message,
            'action_text' => 'View Booking',
            'action_url' => url("/bookings/{$this->booking->id}"),
            'data' => [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'service_name' => $serviceName,
                'service_id' => $this->booking->service_id,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
                'duration_minutes' => $this->booking->duration_minutes,
                'status' => $this->booking->status,
                'payment_status' => $this->booking->payment_status,
                'total_amount' => $this->booking->total_amount,
                'deposit_amount' => $this->booking->deposit_amount,
                'remaining_amount' => $this->booking->remaining_amount,
                'requires_consultation' => $this->booking->requires_consultation,
                'location_name' => $this->booking->serviceLocation?->name,
                'confirmed_at' => now()->toISOString(),
                'next_steps' => $this->getNextSteps(),
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
            'status' => $this->booking->status,
            'payment_status' => $this->booking->payment_status,
            'total_amount' => $this->booking->total_amount,
            'remaining_amount' => $this->booking->remaining_amount,
            'requires_consultation' => $this->booking->requires_consultation,
            'confirmed_at' => now()->toISOString(),
            'next_steps' => $this->getNextSteps(),
            'variables' => $this->variables,
        ];
    }

    /**
     * Determine if SMS should be sent
     */
    private function shouldSendSms(): bool
    {
        // Send SMS for high-value bookings
        if ($this->booking->total_amount > 50000) { // £500+
            return true;
        }

        // Send SMS for bookings requiring consultation
        if ($this->booking->requires_consultation) {
            return true;
        }

        // Send SMS for bookings within 7 days
        if ($this->booking->scheduled_at->diffInDays(now()) <= 7) {
            return true;
        }

        // Send SMS if explicitly requested
        if ($this->options['send_sms'] ?? false) {
            return true;
        }

        return false;
    }

    /**
     * Get next steps for the customer
     */
    private function getNextSteps(): array
    {
        $steps = [];

        // Payment steps
        if ($this->booking->payment_status === 'pending' && $this->booking->remaining_amount > 0) {
            if ($this->booking->deposit_amount > 0 && $this->booking->remaining_amount < $this->booking->total_amount) {
                $steps[] = [
                    'title' => 'Complete Final Payment',
                    'description' => 'Pay the remaining balance before your service date',
                    'amount' => $this->booking->remaining_amount,
                    'due_date' => $this->booking->scheduled_at->subDay()->format('F j, Y'),
                    'priority' => 'high',
                ];
            } else {
                $steps[] = [
                    'title' => 'Complete Payment',
                    'description' => 'Secure your booking by completing payment',
                    'amount' => $this->booking->remaining_amount,
                    'due_date' => $this->booking->scheduled_at->subDay()->format('F j, Y'),
                    'priority' => 'high',
                ];
            }
        }

        // Consultation steps
        if ($this->booking->requires_consultation && !$this->booking->consultation_completed_at) {
            $steps[] = [
                'title' => 'Schedule Consultation',
                'description' => 'We\'ll contact you to schedule a consultation to discuss your requirements',
                'priority' => 'medium',
            ];
        }

        // Preparation steps
        $daysUntil = $this->booking->scheduled_at->diffInDays(now());
        if ($daysUntil <= 7) {
            $steps[] = [
                'title' => 'Prepare for Your Service',
                'description' => 'We\'ll send you detailed preparation instructions closer to your service date',
                'priority' => 'low',
            ];
        }

        // Location access steps
        if ($this->booking->serviceLocation && $this->booking->serviceLocation->requires_access_code) {
            $steps[] = [
                'title' => 'Venue Access Information',
                'description' => 'We\'ll provide venue access details 24 hours before your service',
                'priority' => 'medium',
            ];
        }

        // Default step if no specific steps
        if (empty($steps)) {
            $steps[] = [
                'title' => 'Await Service Date',
                'description' => 'We\'ll send you a reminder closer to your service date with any final details',
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
        return 'high'; // Confirmations are always high priority
    }

    /**
     * Check if notification should be sent immediately
     */
    public function shouldSendImmediately(): bool
    {
        return $this->options['immediate'] ?? true; // Confirmations should be immediate
    }

    /**
     * Get delay for SMS sending
     */
    public function getSmsDelay(): ?Carbon
    {
        // Small delay to ensure email is sent first
        return now()->addMinutes(2);
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
        return "booking_confirmed_{$this->booking->id}";
    }

    /**
     * Get notification tags for filtering
     */
    public function getTags(): array
    {
        return [
            'booking_confirmation',
            "booking:{$this->booking->id}",
            "user:{$this->booking->user_id}",
            "service:{$this->booking->service_id}",
            "status:{$this->booking->status}",
            "payment:{$this->booking->payment_status}",
            "value:" . ($this->booking->total_amount > 50000 ? 'high' : 'standard'),
        ];
    }

    /**
     * Handle notification failure
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Booking confirmation notification failed', [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'client_email' => $this->booking->client_email,
            'error' => $exception->getMessage(),
        ]);

        // Confirmation failures are critical - escalate immediately
        \Log::critical('Booking confirmation failed - manual intervention required', [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'client_name' => $this->booking->client_name,
            'client_email' => $this->booking->client_email,
            'client_phone' => $this->booking->client_phone,
            'service_name' => $this->booking->service->name,
            'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            'total_amount' => $this->booking->total_amount,
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

        // Check if booking is still in confirmed status
        if (!in_array($this->booking->status, ['pending', 'confirmed'])) {
            return false;
        }

        // Don't send confirmations for past bookings
        if ($this->booking->scheduled_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get booking summary for notification
     */
    private function getBookingSummary(): array
    {
        return [
            'service' => [
                'name' => $this->booking->service->name,
                'category' => $this->booking->service->category,
                'duration' => $this->booking->duration_minutes . ' minutes',
            ],
            'timing' => [
                'date' => $this->booking->scheduled_at->format('l, F j, Y'),
                'time' => $this->booking->scheduled_at->format('g:i A'),
                'timezone' => $this->booking->scheduled_at->timezone->getName(),
                'days_until' => $this->booking->scheduled_at->diffInDays(now()),
            ],
            'location' => [
                'name' => $this->booking->serviceLocation?->name ?? 'To be confirmed',
                'type' => $this->booking->serviceLocation?->type ?? 'standard',
                'address' => $this->booking->serviceLocation ? [
                    $this->booking->serviceLocation->address_line_1,
                    $this->booking->serviceLocation->city,
                    $this->booking->serviceLocation->postcode,
                ] : null,
            ],
            'pricing' => [
                'total_amount' => $this->booking->total_amount,
                'deposit_amount' => $this->booking->deposit_amount,
                'remaining_amount' => $this->booking->remaining_amount,
                'payment_status' => $this->booking->payment_status,
                'currency' => 'GBP',
            ],
            'add_ons' => $this->booking->bookingAddOns->map(function ($addon) {
                return [
                    'name' => $addon->serviceAddOn->name,
                    'price' => $addon->price,
                ];
            })->toArray(),
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
            'total_amount' => $this->booking->total_amount,
            'payment_status' => $this->booking->payment_status,
            'requires_consultation' => $this->booking->requires_consultation,
            'confirmation_type' => 'automatic',
            'sent_at' => now()->toISOString(),
            'booking_summary' => $this->getBookingSummary(),
        ];
    }
}

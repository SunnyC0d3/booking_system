<?php

namespace App\Notifications;

use App\Mail\BookingReminderMail;
use App\Models\Booking;
use App\Services\V1\Notifications\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Carbon\Carbon;

class BookingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public Booking $booking;
    public int $hoursUntil;
    public string $reminderType;
    public array $variables;
    public array $options;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        Booking $booking,
        int $hoursUntil,
        string $reminderType = 'standard',
        array $variables = [],
        array $options = []
    ) {
        $this->booking = $booking;
        $this->hoursUntil = $hoursUntil;
        $this->reminderType = $reminderType;
        $this->variables = $variables;
        $this->options = $options;

        // Set queue based on urgency
        $this->onQueue($this->determineQueue());

        // Set delay if specified
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

        // Get user's preferred channels for booking reminders
        $userPreferences = $notifiable->notification_preferences ?? [];
        $reminderPreferences = $userPreferences['booking_reminders'] ?? ['mail', 'database'];

        // Always include database for in-app notifications
        $channels[] = 'database';

        // Add email if enabled
        if (in_array('mail', $reminderPreferences)) {
            $channels[] = 'mail';
        }

        // Add SMS for urgent reminders or if user has opted in
        if (in_array('sms', $reminderPreferences) && $this->shouldSendSms()) {
            $channels[] = \App\Notifications\Channels\SmsChannel::class;
        }

        // Add push notifications if user has opted in
        if (in_array('push', $reminderPreferences)) {
            $channels[] = \App\Notifications\Channels\PushChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): BookingReminderMail
    {
        // Ensure variables are populated
        if (empty($this->variables)) {
            $templateService = app(NotificationTemplateService::class);
            $this->variables = $templateService->getBookingVariables($this->booking);
        }

        // Add reminder-specific variables
        $this->variables['hours_until'] = $this->hoursUntil;
        $this->variables['reminder_type'] = $this->reminderType;
        $this->variables['urgency_level'] = $this->determineUrgencyLevel();

        return new BookingReminderMail($this->booking, $this->variables);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): array
    {
        $timePhrase = $this->getTimePhrase();
        $serviceName = $this->booking->service->name;
        $bookingRef = $this->booking->booking_reference;
        $scheduledTime = $this->booking->scheduled_at->format('g:i A');
        $locationName = $this->booking->serviceLocation?->name ?? 'Location TBC';

        $message = "Reminder: Your {$serviceName} appointment #{$bookingRef} is {$timePhrase} at {$scheduledTime}. {$locationName}. Reply STOP to opt out.";

        return [
            'message' => $message,
            'booking_id' => $this->booking->id,
            'variables' => $this->variables,
            'options' => [
                'urgent' => $this->isUrgent(),
            ],
        ];
    }

    /**
     * Get the push notification representation.
     */
    public function toPush($notifiable): array
    {
        $title = $this->isUrgent() ? '⏰ Booking Starting Soon!' : '📅 Booking Reminder';
        $timePhrase = $this->getTimePhrase();
        $serviceName = $this->booking->service->name;

        $body = "Your {$serviceName} appointment is {$timePhrase} at {$this->booking->scheduled_at->format('g:i A')}.";

        return [
            'title' => $title,
            'body' => $body,
            'icon' => $this->isUrgent() ? '/images/urgent-reminder-icon.png' : '/images/reminder-icon.png',
            'click_action' => url("/bookings/{$this->booking->id}"),
            'booking_id' => $this->booking->id,
            'data' => [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'hours_until' => $this->hoursUntil,
                'reminder_type' => $this->reminderType,
                'urgent' => $this->isUrgent(),
            ],
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable): DatabaseMessage
    {
        $timePhrase = $this->getTimePhrase();
        $serviceName = $this->booking->service->name;

        return new DatabaseMessage([
            'type' => 'booking_reminder',
            'title' => $this->isUrgent() ? 'Booking Starting Soon!' : 'Booking Reminder',
            'message' => "Your {$serviceName} appointment is {$timePhrase}.",
            'action_text' => 'View Booking',
            'action_url' => url("/bookings/{$this->booking->id}"),
            'data' => [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'service_name' => $serviceName,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
                'hours_until' => $this->hoursUntil,
                'reminder_type' => $this->reminderType,
                'urgency_level' => $this->determineUrgencyLevel(),
                'location_name' => $this->booking->serviceLocation?->name,
            ],
            'importance' => $this->isUrgent() ? 'high' : 'normal',
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
            'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            'hours_until' => $this->hoursUntil,
            'reminder_type' => $this->reminderType,
            'urgency_level' => $this->determineUrgencyLevel(),
            'time_phrase' => $this->getTimePhrase(),
            'variables' => $this->variables,
        ];
    }

    /**
     * Determine if SMS should be sent
     */
    private function shouldSendSms(): bool
    {
        // Send SMS for urgent reminders (within 4 hours)
        if ($this->hoursUntil <= 4) {
            return true;
        }

        // Send SMS if explicitly requested
        if ($this->reminderType === 'urgent' || $this->reminderType === 'sms') {
            return true;
        }

        // Send SMS if booking is high value
        if ($this->booking->total_amount > 50000) { // £500+
            return true;
        }

        return false;
    }

    /**
     * Check if this is an urgent reminder
     */
    private function isUrgent(): bool
    {
        return $this->hoursUntil <= 2 || $this->reminderType === 'urgent';
    }

    /**
     * Determine urgency level
     */
    private function determineUrgencyLevel(): string
    {
        if ($this->hoursUntil <= 1) {
            return 'critical';
        }

        if ($this->hoursUntil <= 4) {
            return 'high';
        }

        if ($this->hoursUntil <= 24) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get appropriate time phrase for messaging
     */
    private function getTimePhrase(): string
    {
        if ($this->hoursUntil < 1) {
            $minutes = max(1, $this->booking->scheduled_at->diffInMinutes(now()));
            return "in {$minutes} minute" . ($minutes !== 1 ? 's' : '');
        }

        if ($this->hoursUntil <= 2) {
            return "in {$this->hoursUntil} hour" . ($this->hoursUntil !== 1 ? 's' : '');
        }

        if ($this->booking->scheduled_at->isToday()) {
            return 'today';
        }

        if ($this->booking->scheduled_at->isTomorrow()) {
            return 'tomorrow';
        }

        $days = $this->booking->scheduled_at->diffInDays(now());
        return "in {$days} day" . ($days !== 1 ? 's' : '');
    }

    /**
     * Determine appropriate queue for the notification
     */
    private function determineQueue(): string
    {
        if ($this->isUrgent()) {
            return config('notifications.queues.urgent', 'notifications-urgent');
        }

        if ($this->hoursUntil <= 24) {
            return config('notifications.queues.high', 'notifications-high');
        }

        return config('notifications.queues.normal', 'notifications');
    }

    /**
     * Get notification priority
     */
    public function getPriority(): string
    {
        return $this->isUrgent() ? 'urgent' : 'normal';
    }

    /**
     * Check if notification should be sent immediately
     */
    public function shouldSendImmediately(): bool
    {
        return $this->isUrgent() || $this->options['immediate'] ?? false;
    }

    /**
     * Get delay for SMS sending
     */
    public function getSmsDelay(): ?Carbon
    {
        // Don't delay urgent SMS
        if ($this->isUrgent()) {
            return null;
        }

        // Add small delay for non-urgent SMS to prevent overwhelming
        return now()->addMinutes(2);
    }

    /**
     * Get delay for push notification
     */
    public function getPushDelay(): ?Carbon
    {
        // Don't delay urgent push notifications
        if ($this->isUrgent()) {
            return null;
        }

        // Add small delay for non-urgent push notifications
        return now()->addMinutes(1);
    }

    /**
     * Get unique identifier for this notification
     */
    public function getUniqueId(): string
    {
        return "booking_reminder_{$this->booking->id}_{$this->hoursUntil}h";
    }

    /**
     * Get notification tags for filtering
     */
    public function getTags(): array
    {
        return [
            'booking_reminder',
            "booking:{$this->booking->id}",
            "user:{$this->booking->user_id}",
            "hours_until:{$this->hoursUntil}",
            "urgency:{$this->determineUrgencyLevel()}",
            "type:{$this->reminderType}",
        ];
    }

    /**
     * Handle notification failure
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Booking reminder notification failed', [
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->booking_reference,
            'hours_until' => $this->hoursUntil,
            'reminder_type' => $this->reminderType,
            'error' => $exception->getMessage(),
        ]);

        // If this is an urgent reminder, escalate the failure
        if ($this->isUrgent()) {
            \Log::critical('Urgent booking reminder failed - manual intervention required', [
                'booking_id' => $this->booking->id,
                'booking_reference' => $this->booking->booking_reference,
                'client_name' => $this->booking->client_name,
                'client_email' => $this->booking->client_email,
                'client_phone' => $this->booking->client_phone,
                'scheduled_at' => $this->booking->scheduled_at->toISOString(),
                'error' => $exception->getMessage(),
            ]);
        }
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
        // Check if booking still exists and is active
        if (!$this->booking || in_array($this->booking->status, ['cancelled', 'completed', 'no_show'])) {
            return false;
        }

        // Check if timing is still relevant
        $actualHoursUntil = now()->diffInHours($this->booking->scheduled_at, false);
        $tolerance = 1; // 1 hour tolerance

        if (abs($actualHoursUntil - $this->hoursUntil) > $tolerance) {
            return false;
        }

        // Don't send reminders for past events
        if ($this->booking->scheduled_at->isPast()) {
            return false;
        }

        return true;
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
            'hours_until' => $this->hoursUntil,
            'reminder_type' => $this->reminderType,
            'urgency_level' => $this->determineUrgencyLevel(),
            'scheduled_at' => $this->booking->scheduled_at->toISOString(),
            'total_amount' => $this->booking->total_amount,
            'requires_consultation' => $this->booking->requires_consultation,
        ];
    }
}

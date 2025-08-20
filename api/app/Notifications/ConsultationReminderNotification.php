<?php

namespace App\Notifications;

use App\Mail\ConsultationReminderMail;
use App\Models\ConsultationBooking;
use App\Services\V1\Notifications\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Carbon\Carbon;

class ConsultationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public ConsultationBooking $consultation;
    public int $hoursUntil;
    public string $reminderType;
    public array $variables;
    public array $options;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        ConsultationBooking $consultation,
        int $hoursUntil,
        string $reminderType = 'standard',
        array $variables = [],
        array $options = []
    ) {
        $this->consultation = $consultation;
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

        // Get user's preferred channels for consultation reminders
        $userPreferences = $notifiable->notification_preferences ?? [];
        $reminderPreferences = $userPreferences['consultation_reminders'] ?? ['mail', 'database', 'sms'];

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
    public function toMail($notifiable): ConsultationReminderMail
    {
        // Ensure variables are populated
        if (empty($this->variables)) {
            $templateService = app(NotificationTemplateService::class);
            $this->variables = $templateService->getConsultationVariables($this->consultation);
        }

        // Add reminder-specific variables
        $this->variables['hours_until'] = $this->hoursUntil;
        $this->variables['reminder_type'] = $this->reminderType;
        $this->variables['urgency_level'] = $this->determineUrgencyLevel();

        return new ConsultationReminderMail($this->consultation, $this->variables);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): array
    {
        $timePhrase = $this->getTimePhrase();
        $consultationRef = $this->consultation->consultation_reference;
        $scheduledTime = $this->consultation->scheduled_at->format('g:i A');

        $message = "Reminder: Your consultation #{$consultationRef} is {$timePhrase} at {$scheduledTime}.";

        // Add meeting link for video consultations
        if ($this->consultation->format === 'video' && $this->consultation->meeting_link) {
            $message .= " Join: {$this->consultation->meeting_link}";
        }

        // Add phone number for phone consultations
        if ($this->consultation->format === 'phone' && $this->consultation->dial_in_number) {
            $message .= " Call: {$this->consultation->dial_in_number}";
        }

        // Add location for in-person consultations
        if (in_array($this->consultation->format, ['in_person', 'site_visit']) && $this->consultation->meeting_location) {
            $message .= " Location: {$this->consultation->meeting_location}";
        }

        $message .= " Reply STOP to opt out.";

        return [
            'message' => $message,
            'consultation_id' => $this->consultation->id,
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
        $title = $this->isUrgent() ? '🎥 Consultation Starting Soon!' : '📞 Consultation Reminder';
        $timePhrase = $this->getTimePhrase();

        $body = "Your consultation is {$timePhrase} at {$this->consultation->scheduled_at->format('g:i A')}.";

        // Add format-specific information
        if ($this->consultation->format === 'video') {
            $body .= " Tap to join the video call.";
        } elseif ($this->consultation->format === 'phone') {
            $body .= " We'll call you at the scheduled time.";
        } elseif (in_array($this->consultation->format, ['in_person', 'site_visit'])) {
            $body .= " Please arrive on time.";
        }

        $clickAction = $this->consultation->meeting_link ?? url("/consultations/{$this->consultation->id}");

        return [
            'title' => $title,
            'body' => $body,
            'icon' => $this->getIconForFormat(),
            'click_action' => $clickAction,
            'consultation_id' => $this->consultation->id,
            'data' => [
                'consultation_id' => $this->consultation->id,
                'consultation_reference' => $this->consultation->consultation_reference,
                'format' => $this->consultation->format,
                'meeting_link' => $this->consultation->meeting_link,
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
        $title = $this->isUrgent() ? 'Consultation Starting Soon!' : 'Consultation Reminder';

        $message = "Your consultation is {$timePhrase}.";

        // Add format-specific details
        switch ($this->consultation->format) {
            case 'video':
                $message .= " Join the video call when ready.";
                break;
            case 'phone':
                $message .= " We'll call you at the scheduled time.";
                break;
            case 'in_person':
            case 'site_visit':
                $message .= " Please arrive on time at the specified location.";
                break;
        }

        $actionText = match ($this->consultation->format) {
            'video' => 'Join Video Call',
            'phone' => 'View Details',
            'in_person', 'site_visit' => 'View Location',
            default => 'View Consultation'
        };

        $actionUrl = $this->consultation->meeting_link ?? url("/consultations/{$this->consultation->id}");

        return new DatabaseMessage([
            'type' => 'consultation_reminder',
            'title' => $title,
            'message' => $message,
            'action_text' => $actionText,
            'action_url' => $actionUrl,
            'data' => [
                'consultation_id' => $this->consultation->id,
                'consultation_reference' => $this->consultation->consultation_reference,
                'format' => $this->consultation->format,
                'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
                'hours_until' => $this->hoursUntil,
                'reminder_type' => $this->reminderType,
                'urgency_level' => $this->determineUrgencyLevel(),
                'meeting_link' => $this->consultation->meeting_link,
                'meeting_location' => $this->consultation->meeting_location,
                'dial_in_number' => $this->consultation->dial_in_number,
                'meeting_instructions' => $this->consultation->meeting_instructions,
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
            'consultation_id' => $this->consultation->id,
            'consultation_reference' => $this->consultation->consultation_reference,
            'format' => $this->consultation->format,
            'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
            'hours_until' => $this->hoursUntil,
            'reminder_type' => $this->reminderType,
            'urgency_level' => $this->determineUrgencyLevel(),
            'time_phrase' => $this->getTimePhrase(),
            'meeting_details' => $this->getMeetingDetails(),
            'variables' => $this->variables,
        ];
    }

    /**
     * Determine if SMS should be sent
     */
    private function shouldSendSms(): bool
    {
        // Always send SMS for urgent reminders (within 2 hours)
        if ($this->hoursUntil <= 2) {
            return true;
        }

        // Send SMS for video consultations to ensure they have the link
        if ($this->consultation->format === 'video') {
            return true;
        }

        // Send SMS if explicitly requested
        if ($this->reminderType === 'urgent' || $this->reminderType === 'sms') {
            return true;
        }

        // Send SMS for paid consultations
        if ($this->consultation->consultation_fee > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if this is an urgent reminder
     */
    private function isUrgent(): bool
    {
        return $this->hoursUntil <= 1 || $this->reminderType === 'urgent';
    }

    /**
     * Determine urgency level
     */
    private function determineUrgencyLevel(): string
    {
        if ($this->hoursUntil <= 0.5) { // 30 minutes
            return 'critical';
        }

        if ($this->hoursUntil <= 2) {
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
            $minutes = max(1, $this->consultation->scheduled_at->diffInMinutes(now()));
            return "in {$minutes} minute" . ($minutes !== 1 ? 's' : '');
        }

        if ($this->hoursUntil <= 2) {
            return "in {$this->hoursUntil} hour" . ($this->hoursUntil !== 1 ? 's' : '');
        }

        if ($this->consultation->scheduled_at->isToday()) {
            return 'today';
        }

        if ($this->consultation->scheduled_at->isTomorrow()) {
            return 'tomorrow';
        }

        $days = $this->consultation->scheduled_at->diffInDays(now());
        return "in {$days} day" . ($days !== 1 ? 's' : '');
    }

    /**
     * Get meeting details based on format
     */
    private function getMeetingDetails(): array
    {
        $details = [
            'format' => $this->consultation->format,
            'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
            'duration_minutes' => $this->consultation->duration_minutes,
        ];

        switch ($this->consultation->format) {
            case 'video':
                $details['meeting_link'] = $this->consultation->meeting_link;
                $details['meeting_id'] = $this->consultation->meeting_id;
                $details['access_code'] = $this->consultation->meeting_access_code;
                $details['platform'] = $this->consultation->meeting_platform ?? 'Video Call';
                break;

            case 'phone':
                $details['dial_in_number'] = $this->consultation->dial_in_number;
                $details['access_code'] = $this->consultation->meeting_access_code;
                $details['phone_number'] = $this->consultation->client_phone;
                break;

            case 'in_person':
            case 'site_visit':
                $details['location'] = $this->consultation->meeting_location;
                $details['instructions'] = $this->consultation->meeting_instructions;
                $details['is_site_visit'] = $this->consultation->format === 'site_visit';
                break;
        }

        return $details;
    }

    /**
     * Get appropriate icon for push notification based on format
     */
    private function getIconForFormat(): string
    {
        return match ($this->consultation->format) {
            'video' => $this->isUrgent() ? '/images/video-urgent-icon.png' : '/images/video-consultation-icon.png',
            'phone' => $this->isUrgent() ? '/images/phone-urgent-icon.png' : '/images/phone-consultation-icon.png',
            'in_person', 'site_visit' => $this->isUrgent() ? '/images/meeting-urgent-icon.png' : '/images/in-person-consultation-icon.png',
            default => '/images/consultation-reminder-icon.png'
        };
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

        // Add small delay for non-urgent SMS
        return now()->addMinutes(1);
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
        return now()->addSeconds(30);
    }

    /**
     * Get unique identifier for this notification
     */
    public function getUniqueId(): string
    {
        return "consultation_reminder_{$this->consultation->id}_{$this->hoursUntil}h";
    }

    /**
     * Get notification tags for filtering
     */
    public function getTags(): array
    {
        return [
            'consultation_reminder',
            "consultation:{$this->consultation->id}",
            "user:{$this->consultation->user_id}",
            "format:{$this->consultation->format}",
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
        \Log::error('Consultation reminder notification failed', [
            'consultation_id' => $this->consultation->id,
            'consultation_reference' => $this->consultation->consultation_reference,
            'format' => $this->consultation->format,
            'hours_until' => $this->hoursUntil,
            'reminder_type' => $this->reminderType,
            'error' => $exception->getMessage(),
        ]);

        // If this is an urgent reminder, escalate the failure
        if ($this->isUrgent()) {
            \Log::critical('Urgent consultation reminder failed - manual intervention required', [
                'consultation_id' => $this->consultation->id,
                'consultation_reference' => $this->consultation->consultation_reference,
                'client_name' => $this->consultation->client_name,
                'client_email' => $this->consultation->client_email,
                'client_phone' => $this->consultation->client_phone,
                'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
                'format' => $this->consultation->format,
                'meeting_link' => $this->consultation->meeting_link,
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
        // Check if consultation still exists and is active
        if (!$this->consultation || in_array($this->consultation->status, ['cancelled', 'completed', 'no_show'])) {
            return false;
        }

        // Check if timing is still relevant
        $actualHoursUntil = now()->diffInHours($this->consultation->scheduled_at, false);
        $tolerance = 0.5; // 30 minutes tolerance

        if (abs($actualHoursUntil - $this->hoursUntil) > $tolerance) {
            return false;
        }

        // Don't send reminders for past events
        if ($this->consultation->scheduled_at->isPast()) {
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
            'consultation_id' => $this->consultation->id,
            'consultation_reference' => $this->consultation->consultation_reference,
            'main_booking_id' => $this->consultation->main_booking_id,
            'service_id' => $this->consultation->service_id,
            'user_id' => $this->consultation->user_id,
            'format' => $this->consultation->format,
            'hours_until' => $this->hoursUntil,
            'reminder_type' => $this->reminderType,
            'urgency_level' => $this->determineUrgencyLevel(),
            'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
            'consultation_fee' => $this->consultation->consultation_fee,
            'has_meeting_link' => !empty($this->consultation->meeting_link),
        ];
    }
}

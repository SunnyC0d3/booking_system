<?php

namespace App\Notifications;

use App\Mail\ConsultationStartingSoonMail;
use App\Models\ConsultationBooking;
use App\Services\V1\Notifications\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Carbon\Carbon;

class ConsultationStartingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public ConsultationBooking $consultation;
    public int $minutesUntil;
    public array $variables;
    public array $options;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        ConsultationBooking $consultation,
        int $minutesUntil = 15,
        array $variables = [],
        array $options = []
    ) {
        $this->consultation = $consultation;
        $this->minutesUntil = $minutesUntil;
        $this->variables = $variables;
        $this->options = $options;

        // Set urgent priority queue
        $this->onQueue(config('notifications.queues.urgent', 'notifications-urgent'));

        // These should be sent immediately
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

        // Always include push for urgent notifications
        $channels[] = \App\Notifications\Channels\PushChannel::class;

        // Always include SMS for urgent consultation notifications
        $channels[] = \App\Notifications\Channels\SmsChannel::class;

        // Include email if enabled
        if (in_array('mail', $reminderPreferences)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): ConsultationStartingSoonMail
    {
        // Ensure variables are populated
        if (empty($this->variables)) {
            $templateService = app(NotificationTemplateService::class);
            $this->variables = $templateService->getConsultationVariables($this->consultation);
        }

        // Add urgency-specific variables
        $this->variables['minutes_until'] = $this->minutesUntil;
        $this->variables['urgency_level'] = 'critical';
        $this->variables['is_starting_soon'] = true;
        $this->variables['join_instructions'] = $this->getJoinInstructions();

        return new ConsultationStartingSoonMail($this->consultation, $this->variables);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms($notifiable): array
    {
        $consultationRef = $this->consultation->consultation_reference;
        $timePhrase = $this->getTimePhrase();

        $message = "🚀 Your consultation #{$consultationRef} is starting {$timePhrase}!";

        // Add format-specific join instructions
        switch ($this->consultation->format) {
            case 'video':
                if ($this->consultation->meeting_link) {
                    $message .= " Join now: {$this->consultation->meeting_link}";
                }
                if ($this->consultation->meeting_access_code) {
                    $message .= " Access code: {$this->consultation->meeting_access_code}";
                }
                break;

            case 'phone':
                if ($this->consultation->dial_in_number) {
                    $message .= " We'll call you at {$this->consultation->client_phone}";
                } else {
                    $message .= " Please be available at {$this->consultation->client_phone}";
                }
                break;

            case 'in_person':
            case 'site_visit':
                $message .= " Please be at the scheduled location";
                break;
        }

        $message .= " Reply STOP to opt out.";

        return [
            'message' => $message,
            'consultation_id' => $this->consultation->id,
            'variables' => $this->variables,
            'options' => [
                'urgent' => true,
                'high_priority' => true,
            ],
        ];
    }

    /**
     * Get the push notification representation.
     */
    public function toPush($notifiable): array
    {
        $timePhrase = $this->getTimePhrase();

        $title = '🚀 Consultation Starting Soon!';
        $body = "Your consultation is starting {$timePhrase}.";

        // Add format-specific call to action
        switch ($this->consultation->format) {
            case 'video':
                $body .= " Tap to join the video call now.";
                break;
            case 'phone':
                $body .= " Please be available at your phone.";
                break;
            case 'in_person':
            case 'site_visit':
                $body .= " Please arrive at the location.";
                break;
        }

        $clickAction = $this->consultation->meeting_link ?? url("/consultations/{$this->consultation->id}");

        return [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/consultation-urgent-icon.png',
            'click_action' => $clickAction,
            'consultation_id' => $this->consultation->id,
            'sound' => 'urgent_notification.wav',
            'vibrate' => [200, 100, 200],
            'data' => [
                'consultation_id' => $this->consultation->id,
                'consultation_reference' => $this->consultation->consultation_reference,
                'format' => $this->consultation->format,
                'meeting_link' => $this->consultation->meeting_link,
                'minutes_until' => $this->minutesUntil,
                'urgent' => true,
                'action' => 'join_consultation',
            ],
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase($notifiable): DatabaseMessage
    {
        $timePhrase = $this->getTimePhrase();

        $title = 'Consultation Starting Soon!';
        $message = "Your consultation is starting {$timePhrase}.";

        // Add format-specific instructions
        switch ($this->consultation->format) {
            case 'video':
                $message .= " Click to join the video call now.";
                break;
            case 'phone':
                $message .= " Please ensure you're available at your registered phone number.";
                break;
            case 'in_person':
            case 'site_visit':
                $message .= " Please be at the scheduled location.";
                break;
        }

        $actionText = match ($this->consultation->format) {
            'video' => 'Join Video Call',
            'phone' => 'View Details',
            'in_person', 'site_visit' => 'View Location',
            default => 'Join Now'
        };

        $actionUrl = $this->consultation->meeting_link ?? url("/consultations/{$this->consultation->id}");

        return new DatabaseMessage([
            'type' => 'consultation_starting_soon',
            'title' => $title,
            'message' => $message,
            'action_text' => $actionText,
            'action_url' => $actionUrl,
            'data' => [
                'consultation_id' => $this->consultation->id,
                'consultation_reference' => $this->consultation->consultation_reference,
                'format' => $this->consultation->format,
                'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
                'minutes_until' => $this->minutesUntil,
                'meeting_link' => $this->consultation->meeting_link,
                'meeting_location' => $this->consultation->meeting_location,
                'dial_in_number' => $this->consultation->dial_in_number,
                'access_code' => $this->consultation->meeting_access_code,
                'join_instructions' => $this->getJoinInstructions(),
                'urgency_level' => 'critical',
            ],
            'importance' => 'urgent',
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
            'minutes_until' => $this->minutesUntil,
            'time_phrase' => $this->getTimePhrase(),
            'meeting_details' => $this->getMeetingDetails(),
            'join_instructions' => $this->getJoinInstructions(),
            'urgency_level' => 'critical',
            'variables' => $this->variables,
        ];
    }

    /**
     * Get appropriate time phrase for messaging
     */
    private function getTimePhrase(): string
    {
        if ($this->minutesUntil <= 0) {
            return 'now';
        }

        if ($this->minutesUntil <= 5) {
            return "in {$this->minutesUntil} minute" . ($this->minutesUntil !== 1 ? 's' : '');
        }

        if ($this->minutesUntil <= 15) {
            return "in {$this->minutesUntil} minutes";
        }

        return 'soon';
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
            'minutes_until' => $this->minutesUntil,
        ];

        switch ($this->consultation->format) {
            case 'video':
                $details['meeting_link'] = $this->consultation->meeting_link;
                $details['meeting_id'] = $this->consultation->meeting_id;
                $details['access_code'] = $this->consultation->meeting_access_code;
                $details['platform'] = $this->consultation->meeting_platform ?? 'Video Call';
                $details['host_key'] = $this->consultation->host_key;
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
     * Get format-specific join instructions
     */
    private function getJoinInstructions(): array
    {
        $instructions = [];

        switch ($this->consultation->format) {
            case 'video':
                $instructions[] = "1. Click the meeting link to join";
                if ($this->consultation->meeting_access_code) {
                    $instructions[] = "2. Enter access code: {$this->consultation->meeting_access_code}";
                }
                $instructions[] = "3. Test your camera and microphone";
                $instructions[] = "4. Join the call when ready";
                break;

            case 'phone':
                if ($this->consultation->dial_in_number) {
                    $instructions[] = "1. Call {$this->consultation->dial_in_number}";
                    if ($this->consultation->meeting_access_code) {
                        $instructions[] = "2. Enter access code: {$this->consultation->meeting_access_code}";
                    }
                } else {
                    $instructions[] = "1. Please be available at {$this->consultation->client_phone}";
                    $instructions[] = "2. We will call you at the scheduled time";
                }
                $instructions[] = "3. Have a pen and paper ready for notes";
                break;

            case 'in_person':
                $instructions[] = "1. Arrive at: {$this->consultation->meeting_location}";
                $instructions[] = "2. Bring any relevant documents or materials";
                if ($this->consultation->meeting_instructions) {
                    $instructions[] = "3. Additional instructions: {$this->consultation->meeting_instructions}";
                }
                break;

            case 'site_visit':
                $instructions[] = "1. Be at the site location: {$this->consultation->meeting_location}";
                $instructions[] = "2. Ensure access to the site is available";
                if ($this->consultation->meeting_instructions) {
                    $instructions[] = "3. Site-specific instructions: {$this->consultation->meeting_instructions}";
                }
                break;
        }

        return $instructions;
    }

    /**
     * Get notification priority
     */
    public function getPriority(): string
    {
        return 'urgent'; // Starting soon notifications are always urgent
    }

    /**
     * Check if notification should be sent immediately
     */
    public function shouldSendImmediately(): bool
    {
        return true; // These should always be immediate
    }

    /**
     * No delay for urgent notifications
     */
    public function getSmsDelay(): ?Carbon
    {
        return null; // Send immediately
    }

    /**
     * No delay for urgent notifications
     */
    public function getPushDelay(): ?Carbon
    {
        return null; // Send immediately
    }

    /**
     * Get unique identifier for this notification
     */
    public function getUniqueId(): string
    {
        return "consultation_starting_soon_{$this->consultation->id}_{$this->minutesUntil}m";
    }

    /**
     * Get notification tags for filtering
     */
    public function getTags(): array
    {
        return [
            'consultation_starting_soon',
            "consultation:{$this->consultation->id}",
            "user:{$this->consultation->user_id}",
            "format:{$this->consultation->format}",
            "minutes_until:{$this->minutesUntil}",
            'urgency:critical',
            'priority:urgent',
        ];
    }

    /**
     * Handle notification failure
     */
    public function failed(\Throwable $exception): void
    {
        \Log::error('Consultation starting soon notification failed', [
            'consultation_id' => $this->consultation->id,
            'consultation_reference' => $this->consultation->consultation_reference,
            'format' => $this->consultation->format,
            'minutes_until' => $this->minutesUntil,
            'error' => $exception->getMessage(),
        ]);

        // This is critical - escalate immediately
        \Log::critical('URGENT: Consultation starting soon notification failed - immediate intervention required', [
            'consultation_id' => $this->consultation->id,
            'consultation_reference' => $this->consultation->consultation_reference,
            'client_name' => $this->consultation->client_name,
            'client_email' => $this->consultation->client_email,
            'client_phone' => $this->consultation->client_phone,
            'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
            'format' => $this->consultation->format,
            'meeting_link' => $this->consultation->meeting_link,
            'minutes_until' => $this->minutesUntil,
            'error' => $exception->getMessage(),
        ]);

        // TODO: Trigger immediate admin alert via SMS/phone call
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

        // Check if consultation hasn't already started
        if ($this->consultation->started_at) {
            return false;
        }

        // Check if we're not too early or too late
        $minutesUntilNow = $this->consultation->scheduled_at->diffInMinutes(now(), false);

        // Don't send if consultation has already passed
        if ($minutesUntilNow < -30) { // 30 minutes grace period
            return false;
        }

        // Don't send if consultation is too far in the future
        if ($minutesUntilNow > 30) {
            return false;
        }

        return true;
    }

    /**
     * Get technical support information for video calls
     */
    private function getTechnicalSupport(): array
    {
        return [
            'phone' => config('app.tech_support_phone', '+44 20 3890 2370'),
            'email' => config('app.tech_support_email', 'tech@company.com'),
            'chat_url' => url('/support/chat'),
            'troubleshooting_url' => url('/support/video-troubleshooting'),
        ];
    }

    /**
     * Get backup contact methods if primary fails
     */
    private function getBackupContactMethods(): array
    {
        $methods = [];

        // If video call, provide phone backup
        if ($this->consultation->format === 'video' && $this->consultation->dial_in_number) {
            $methods[] = [
                'type' => 'phone',
                'details' => $this->consultation->dial_in_number,
                'instructions' => 'Call this number if video link fails',
            ];
        }

        // If phone call, provide email backup
        if ($this->consultation->format === 'phone') {
            $methods[] = [
                'type' => 'email',
                'details' => config('mail.from.address'),
                'instructions' => 'Email us immediately if we cannot reach you',
            ];
        }

        // Always provide support contact
        $methods[] = [
            'type' => 'support',
            'details' => config('app.support_phone', '+44 20 3890 2370'),
            'instructions' => 'Call support for immediate assistance',
        ];

        return $methods;
    }

    /**
     * Get pre-meeting checklist
     */
    private function getPreMeetingChecklist(): array
    {
        $checklist = [];

        switch ($this->consultation->format) {
            case 'video':
                $checklist = [
                    'Test your camera and microphone',
                    'Ensure stable internet connection',
                    'Find a quiet, well-lit location',
                    'Have the meeting link ready',
                    'Close unnecessary applications',
                ];
                break;

            case 'phone':
                $checklist = [
                    'Ensure your phone is charged',
                    'Find a quiet location',
                    'Have a pen and paper ready',
                    'Check signal strength',
                ];
                break;

            case 'in_person':
            case 'site_visit':
                $checklist = [
                    'Confirm the meeting location',
                    'Allow extra time for travel',
                    'Bring required documents',
                    'Ensure site access is available',
                ];
                break;
        }

        return $checklist;
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
            'scheduled_at' => $this->consultation->scheduled_at->toISOString(),
            'minutes_until' => $this->minutesUntil,
            'urgency_level' => 'critical',
            'has_meeting_link' => !empty($this->consultation->meeting_link),
            'consultation_fee' => $this->consultation->consultation_fee,
            'notification_type' => 'starting_soon',
            'sent_at' => now()->toISOString(),
        ];
    }
}

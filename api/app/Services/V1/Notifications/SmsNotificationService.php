<?php

namespace App\Services\V1\Notifications;

use App\Models\Booking;
use App\Models\ConsultationBooking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class SmsNotificationService
{
    private NotificationTemplateService $templateService;
    private string $provider;
    private array $config;

    public function __construct(NotificationTemplateService $templateService)
    {
        $this->templateService = $templateService;
        $this->provider = config('notifications.channels.sms.provider', 'twilio');
        $this->config = config("notifications.sms_providers.{$this->provider}", []);
    }

    /**
     * Send booking confirmation SMS
     */
    public function sendBookingConfirmation(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true; // Consider disabled as successful
        }

        try {
            $phone = $this->getPhoneNumber($booking);
            if (!$phone) {
                Log::info('No phone number available for booking confirmation SMS', [
                    'booking_id' => $booking->id,
                ]);
                return true; // Not an error if no phone number
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $message = $this->formatBookingConfirmationMessage($variables);

            return $this->sendSms($phone, $message, [
                'type' => 'booking_confirmation',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation SMS', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking reminder SMS
     */
    public function sendBookingReminder(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $phone = $this->getPhoneNumber($booking);
            if (!$phone) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $message = $this->formatBookingReminderMessage($variables);

            return $this->sendSms($phone, $message, [
                'type' => 'booking_reminder',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'hours_until' => $variables['hours_until'] ?? 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking reminder SMS', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking cancellation SMS
     */
    public function sendBookingCancellation(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $phone = $this->getPhoneNumber($booking);
            if (!$phone) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $message = $this->formatBookingCancellationMessage($variables);

            return $this->sendSms($phone, $message, [
                'type' => 'booking_cancelled',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking cancellation SMS', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking rescheduled SMS
     */
    public function sendBookingRescheduled(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $phone = $this->getPhoneNumber($booking);
            if (!$phone) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $message = $this->formatBookingRescheduledMessage($variables);

            return $this->sendSms($phone, $message, [
                'type' => 'booking_rescheduled',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking rescheduled SMS', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send payment reminder SMS
     */
    public function sendPaymentReminder(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $phone = $this->getPhoneNumber($booking);
            if (!$phone) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getPaymentVariables($booking);
            }

            $message = $this->formatPaymentReminderMessage($variables);

            return $this->sendSms($phone, $message, [
                'type' => 'payment_reminder',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount_due' => $variables['amount_due'] ?? 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send payment reminder SMS', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send consultation reminder SMS
     */
    public function sendConsultationReminder(ConsultationBooking $consultation, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $phone = $this->getConsultationPhoneNumber($consultation);
            if (!$phone) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getConsultationVariables($consultation);
            }

            $message = $this->formatConsultationReminderMessage($variables);

            return $this->sendSms($phone, $message, [
                'type' => 'consultation_reminder',
                'consultation_id' => $consultation->id,
                'user_id' => $consultation->user_id,
                'meeting_link' => $consultation->meeting_link,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send consultation reminder SMS', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send consultation starting soon SMS
     */
    public function sendConsultationStartingSoon(ConsultationBooking $consultation, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $phone = $this->getConsultationPhoneNumber($consultation);
            if (!$phone) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getConsultationVariables($consultation);
            }

            $message = $this->formatConsultationStartingSoonMessage($variables);

            return $this->sendSms($phone, $message, [
                'type' => 'consultation_starting_soon',
                'consultation_id' => $consultation->id,
                'user_id' => $consultation->user_id,
                'meeting_link' => $consultation->meeting_link,
                'urgent' => true,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send consultation starting soon SMS', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send SMS using configured provider
     */
    private function sendSms(string $phone, string $message, array $metadata = []): bool
    {
        try {
            // Check rate limiting
            if (!$this->checkRateLimit($metadata['user_id'] ?? null)) {
                Log::warning('SMS rate limit exceeded', $metadata);
                return false;
            }

            // Validate phone number
            $phone = $this->normalizePhoneNumber($phone);
            if (!$this->isValidPhoneNumber($phone)) {
                Log::warning('Invalid phone number for SMS', [
                    'phone' => $phone,
                    'metadata' => $metadata,
                ]);
                return false;
            }

            // Check if testing mode
            if (config('notifications.testing.fake_notifications', false)) {
                return $this->handleTestingSms($phone, $message, $metadata);
            }

            // Send SMS via provider
            $success = match ($this->provider) {
                'twilio' => $this->sendViaTwilio($phone, $message, $metadata),
                'vonage' => $this->sendViaVonage($phone, $message, $metadata),
                'aws' => $this->sendViaAws($phone, $message, $metadata),
                default => throw new Exception("Unsupported SMS provider: {$this->provider}")
            };

            // Log SMS activity
            $this->logSmsActivity($phone, $message, $success, $metadata);

            return $success;

        } catch (Exception $e) {
            Log::error('SMS sending failed', [
                'phone' => $phone,
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Send SMS via Twilio
     */
    private function sendViaTwilio(string $phone, string $message, array $metadata): bool
    {
        try {
            $response = Http::withBasicAuth($this->config['sid'], $this->config['token'])
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->config['sid']}/Messages.json", [
                    'From' => $this->config['from'],
                    'To' => $phone,
                    'Body' => $message,
                    'StatusCallback' => url('/api/v1/webhooks/twilio/sms-status'),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Twilio SMS sent successfully', [
                    'message_sid' => $data['sid'] ?? null,
                    'status' => $data['status'] ?? null,
                    'metadata' => $metadata,
                ]);
                return true;
            }

            Log::error('Twilio SMS failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'metadata' => $metadata,
            ]);
            return false;

        } catch (Exception $e) {
            Log::error('Twilio SMS exception', [
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Send SMS via Vonage (Nexmo)
     */
    private function sendViaVonage(string $phone, string $message, array $metadata): bool
    {
        try {
            $response = Http::post('https://rest.nexmo.com/sms/json', [
                'api_key' => $this->config['key'],
                'api_secret' => $this->config['secret'],
                'from' => $this->config['from'],
                'to' => $phone,
                'text' => $message,
                'callback' => url('/api/v1/webhooks/vonage/sms-status'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $messages = $data['messages'] ?? [];

                if (!empty($messages) && ($messages[0]['status'] ?? null) === '0') {
                    Log::info('Vonage SMS sent successfully', [
                        'message_id' => $messages[0]['message-id'] ?? null,
                        'metadata' => $metadata,
                    ]);
                    return true;
                }
            }

            Log::error('Vonage SMS failed', [
                'response' => $response->json(),
                'metadata' => $metadata,
            ]);
            return false;

        } catch (Exception $e) {
            Log::error('Vonage SMS exception', [
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Send SMS via AWS SNS
     */
    private function sendViaAws(string $phone, string $message, array $metadata): bool
    {
        try {
            // This would require AWS SDK integration
            // For now, return false to indicate not implemented
            Log::warning('AWS SMS provider not fully implemented', $metadata);
            return false;

        } catch (Exception $e) {
            Log::error('AWS SMS exception', [
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Handle SMS in testing mode
     */
    private function handleTestingSms(string $phone, string $message, array $metadata): bool
    {
        if (config('notifications.testing.log_fake_notifications', true)) {
            Log::info('FAKE SMS SENT (Testing Mode)', [
                'phone' => $phone,
                'message' => $message,
                'metadata' => $metadata,
            ]);
        }
        return true;
    }

    /**
     * Get phone number from booking
     */
    private function getPhoneNumber(Booking $booking): ?string
    {
        return $booking->client_phone ?: $booking->user->phone;
    }

    /**
     * Get phone number from consultation
     */
    private function getConsultationPhoneNumber(ConsultationBooking $consultation): ?string
    {
        return $consultation->client_phone ?: $consultation->user->phone;
    }

    /**
     * Normalize phone number format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add UK country code if not present and appears to be UK number
        if (strlen($phone) === 11 && str_starts_with($phone, '07')) {
            $phone = '44' . substr($phone, 1);
        } elseif (strlen($phone) === 10 && str_starts_with($phone, '7')) {
            $phone = '447' . substr($phone, 1);
        }

        // Add + prefix for international format
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Validate phone number format
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // Basic validation for international format
        return preg_match('/^\+[1-9]\d{10,14}$/', $phone);
    }

    /**
     * Check SMS rate limiting
     */
    private function checkRateLimit(?int $userId): bool
    {
        if (!config('notifications.rate_limiting.enabled', true)) {
            return true;
        }

        // Implement rate limiting logic here
        // For now, return true
        return true;
    }

    /**
     * Check if SMS is enabled
     */
    private function isEnabled(): bool
    {
        return config('notifications.channels.sms.enabled', false) && !empty($this->config);
    }

    /**
     * Format booking confirmation message
     */
    private function formatBookingConfirmationMessage(array $variables): string
    {
        return "Hi {$variables['client_name']}! Your booking #{$variables['reference']} for {$variables['service_name']} on {$variables['scheduled_datetime']} has been confirmed. We'll send you a reminder closer to the date. Reply STOP to opt out.";
    }

    /**
     * Format booking reminder message
     */
    private function formatBookingReminderMessage(array $variables): string
    {
        $timePhrase = $variables['hours_until'] <= 2 ? 'in ' . $variables['time_until_display'] : 'tomorrow';
        return "Reminder: Your {$variables['service_name']} appointment #{$variables['reference']} is {$timePhrase} at {$variables['scheduled_time']}. {$variables['location_name']}. Reply STOP to opt out.";
    }

    /**
     * Format booking cancellation message
     */
    private function formatBookingCancellationMessage(array $variables): string
    {
        return "Your booking #{$variables['reference']} for {$variables['service_name']} on {$variables['scheduled_date']} has been cancelled. If you have any questions, please contact us. Reply STOP to opt out.";
    }

    /**
     * Format booking rescheduled message
     */
    private function formatBookingRescheduledMessage(array $variables): string
    {
        return "Your booking #{$variables['reference']} has been rescheduled from {$variables['old_scheduled_datetime']} to {$variables['scheduled_datetime']}. Reply STOP to opt out.";
    }

    /**
     * Format payment reminder message
     */
    private function formatPaymentReminderMessage(array $variables): string
    {
        return "Payment reminder: {$variables['amount_due']} is due for booking #{$variables['reference']}. Please complete payment to secure your {$variables['scheduled_date']} appointment. Reply STOP to opt out.";
    }

    /**
     * Format consultation reminder message
     */
    private function formatConsultationReminderMessage(array $variables): string
    {
        $meetingInfo = $variables['meeting_link'] ? " Join: {$variables['meeting_link']}" : '';
        return "Reminder: Your consultation #{$variables['reference']} is tomorrow at {$variables['scheduled_time']}.{$meetingInfo} Reply STOP to opt out.";
    }

    /**
     * Format consultation starting soon message
     */
    private function formatConsultationStartingSoonMessage(array $variables): string
    {
        $meetingInfo = $variables['meeting_link'] ? " Join now: {$variables['meeting_link']}" : '';
        return "Your consultation #{$variables['reference']} is starting in 15 minutes.{$meetingInfo} Reply STOP to opt out.";
    }

    /**
     * Log SMS activity
     */
    private function logSmsActivity(string $phone, string $message, bool $success, array $metadata): void
    {
        Log::info('SMS notification processed', [
            'phone' => substr($phone, 0, -4) . '****', // Mask phone number for privacy
            'message_length' => strlen($message),
            'provider' => $this->provider,
            'success' => $success,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]);
    }
}

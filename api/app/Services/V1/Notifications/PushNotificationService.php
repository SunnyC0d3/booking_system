<?php

namespace App\Services\V1\Notifications;

use App\Constants\NotificationStatuses;
use App\Models\Booking;
use App\Models\ConsultationBooking;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class PushNotificationService
{
    private NotificationTemplateService $templateService;
    private string $provider;
    private array $config;

    public function __construct(NotificationTemplateService $templateService)
    {
        $this->templateService = $templateService;
        $this->provider = config('notifications.channels.push.provider', 'fcm');
        $this->config = config("notifications.push_providers.{$this->provider}", []);
    }

    /**
     * Send booking confirmation push notification
     */
    public function sendBookingConfirmation(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true; // Consider disabled as successful
        }

        try {
            $deviceTokens = $this->getUserDeviceTokens($booking->user);
            if (empty($deviceTokens)) {
                Log::info('No device tokens available for booking confirmation push', [
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                ]);
                return true; // Not an error if no device tokens
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $pushData = $this->formatBookingConfirmationPush($variables);

            return $this->sendPushNotification($deviceTokens, $pushData, [
                'type' => 'booking_confirmation',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation push', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking reminder push notification
     */
    public function sendBookingReminder(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $deviceTokens = $this->getUserDeviceTokens($booking->user);
            if (empty($deviceTokens)) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $pushData = $this->formatBookingReminderPush($variables);

            return $this->sendPushNotification($deviceTokens, $pushData, [
                'type' => 'booking_reminder',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'hours_until' => $variables['hours_until'] ?? 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking reminder push', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking cancellation push notification
     */
    public function sendBookingCancellation(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $deviceTokens = $this->getUserDeviceTokens($booking->user);
            if (empty($deviceTokens)) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $pushData = $this->formatBookingCancellationPush($variables);

            return $this->sendPushNotification($deviceTokens, $pushData, [
                'type' => 'booking_cancelled',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking cancellation push', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking rescheduled push notification
     */
    public function sendBookingRescheduled(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $deviceTokens = $this->getUserDeviceTokens($booking->user);
            if (empty($deviceTokens)) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getBookingVariables($booking);
            }

            $pushData = $this->formatBookingRescheduledPush($variables);

            return $this->sendPushNotification($deviceTokens, $pushData, [
                'type' => 'booking_rescheduled',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send booking rescheduled push', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send payment reminder push notification
     */
    public function sendPaymentReminder(Booking $booking, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $deviceTokens = $this->getUserDeviceTokens($booking->user);
            if (empty($deviceTokens)) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getPaymentVariables($booking);
            }

            $pushData = $this->formatPaymentReminderPush($variables);

            return $this->sendPushNotification($deviceTokens, $pushData, [
                'type' => 'payment_reminder',
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount_due' => $variables['amount_due'] ?? 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send payment reminder push', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send consultation reminder push notification
     */
    public function sendConsultationReminder(ConsultationBooking $consultation, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $deviceTokens = $this->getUserDeviceTokens($consultation->user);
            if (empty($deviceTokens)) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getConsultationVariables($consultation);
            }

            $pushData = $this->formatConsultationReminderPush($variables);

            return $this->sendPushNotification($deviceTokens, $pushData, [
                'type' => 'consultation_reminder',
                'consultation_id' => $consultation->id,
                'user_id' => $consultation->user_id,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send consultation reminder push', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send consultation starting soon push notification
     */
    public function sendConsultationStartingSoon(ConsultationBooking $consultation, array $variables = []): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        try {
            $deviceTokens = $this->getUserDeviceTokens($consultation->user);
            if (empty($deviceTokens)) {
                return true;
            }

            if (empty($variables)) {
                $variables = $this->templateService->getConsultationVariables($consultation);
            }

            $pushData = $this->formatConsultationStartingSoonPush($variables);

            return $this->sendPushNotification($deviceTokens, $pushData, [
                'type' => 'consultation_starting_soon',
                'consultation_id' => $consultation->id,
                'user_id' => $consultation->user_id,
                'urgent' => true,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send consultation starting soon push', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send push notification using configured provider
     */
    private function sendPushNotification(array $deviceTokens, array $pushData, array $metadata = []): bool
    {
        try {
            // Check rate limiting
            if (!$this->checkRateLimit($metadata['user_id'] ?? null)) {
                Log::warning('Push notification rate limit exceeded', $metadata);
                return false;
            }

            // Check if testing mode
            if (config('notifications.testing.fake_notifications', false)) {
                return $this->handleTestingPush($deviceTokens, $pushData, $metadata);
            }

            // Send push via provider
            $success = match ($this->provider) {
                'fcm' => $this->sendViaFcm($deviceTokens, $pushData, $metadata),
                'apn' => $this->sendViaApn($deviceTokens, $pushData, $metadata),
                'onesignal' => $this->sendViaOneSignal($deviceTokens, $pushData, $metadata),
                default => throw new Exception("Unsupported push provider: {$this->provider}")
            };

            // Log push activity
            $this->logPushActivity($deviceTokens, $pushData, $success, $metadata);

            return $success;

        } catch (Exception $e) {
            Log::error('Push notification sending failed', [
                'provider' => $this->provider,
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Send push notification via Firebase Cloud Messaging (FCM)
     */
    private function sendViaFcm(array $deviceTokens, array $pushData, array $metadata): bool
    {
        try {
            $payload = [
                'registration_ids' => $deviceTokens,
                'notification' => [
                    'title' => $pushData['title'],
                    'body' => $pushData['body'],
                    'icon' => $pushData['icon'] ?? '/images/notification-icon.png',
                    'click_action' => $pushData['click_action'] ?? null,
                    'sound' => 'default',
                ],
                'data' => [
                    'type' => $metadata['type'] ?? 'general',
                    'booking_id' => $metadata['booking_id'] ?? null,
                    'consultation_id' => $metadata['consultation_id'] ?? null,
                    'click_action' => $pushData['click_action'] ?? null,
                    'timestamp' => now()->toISOString(),
                ],
                'android' => [
                    'notification' => [
                        'channel_id' => $this->getAndroidChannelId($metadata['type'] ?? 'general'),
                        'priority' => $metadata['urgent'] ?? false ? 'high' : 'normal',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $pushData['title'],
                                'body' => $pushData['body'],
                            ],
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->config['server_key'],
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $successCount = $data['success'] ?? 0;
                $failureCount = $data['failure'] ?? 0;

                Log::info('FCM push notification sent', [
                    'success_count' => $successCount,
                    'failure_count' => $failureCount,
                    'message_id' => $data['multicast_id'] ?? null,
                    'metadata' => $metadata,
                ]);

                return $successCount > 0;
            }

            Log::error('FCM push notification failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'metadata' => $metadata,
            ]);
            return false;

        } catch (Exception $e) {
            Log::error('FCM push notification exception', [
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Send push notification via Apple Push Notifications (APN)
     */
    private function sendViaApn(array $deviceTokens, array $pushData, array $metadata): bool
    {
        try {
            // APN implementation would require proper certificate handling
            // For now, return false to indicate not implemented
            Log::warning('APN push provider not fully implemented', $metadata);
            return false;

        } catch (Exception $e) {
            Log::error('APN push notification exception', [
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Send push notification via OneSignal
     */
    private function sendViaOneSignal(array $deviceTokens, array $pushData, array $metadata): bool
    {
        try {
            $payload = [
                'app_id' => $this->config['app_id'],
                'include_player_ids' => $deviceTokens,
                'headings' => ['en' => $pushData['title']],
                'contents' => ['en' => $pushData['body']],
                'data' => [
                    'type' => $metadata['type'] ?? 'general',
                    'booking_id' => $metadata['booking_id'] ?? null,
                    'consultation_id' => $metadata['consultation_id'] ?? null,
                ],
                'url' => $pushData['click_action'] ?? null,
                'priority' => $metadata['urgent'] ?? false ? 10 : 5,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $this->config['rest_api_key'],
                'Content-Type' => 'application/json',
            ])->post('https://onesignal.com/api/v1/notifications', $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('OneSignal push notification sent', [
                    'notification_id' => $data['id'] ?? null,
                    'recipients' => $data['recipients'] ?? 0,
                    'metadata' => $metadata,
                ]);
                return true;
            }

            Log::error('OneSignal push notification failed', [
                'status' => $response->status(),
                'response' => $response->json(),
                'metadata' => $metadata,
            ]);
            return false;

        } catch (Exception $e) {
            Log::error('OneSignal push notification exception', [
                'error' => $e->getMessage(),
                'metadata' => $metadata,
            ]);
            return false;
        }
    }

    /**
     * Handle push notification in testing mode
     */
    private function handleTestingPush(array $deviceTokens, array $pushData, array $metadata): bool
    {
        if (config('notifications.testing.log_fake_notifications', true)) {
            Log::info('FAKE PUSH NOTIFICATION SENT (Testing Mode)', [
                'device_count' => count($deviceTokens),
                'title' => $pushData['title'],
                'body' => $pushData['body'],
                'metadata' => $metadata,
            ]);
        }
        return true;
    }

    /**
     * Get user's device tokens
     */
    private function getUserDeviceTokens(User $user): array
    {
        // This would typically come from a user_device_tokens table
        // For now, return empty array as the table doesn't exist yet
        return [];
    }

    /**
     * Check push notification rate limiting
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
     * Check if push notifications are enabled
     */
    private function isEnabled(): bool
    {
        return config('notifications.channels.push.enabled', false) && !empty($this->config);
    }

    /**
     * Get Android notification channel ID
     */
    private function getAndroidChannelId(string $type): string
    {
        return match ($type) {
            'booking_confirmation', 'booking_cancelled', 'booking_rescheduled' => 'booking_updates',
            'booking_reminder' => 'booking_reminders',
            'consultation_reminder', 'consultation_starting_soon' => 'consultation_reminders',
            'payment_reminder' => 'payment_reminders',
            default => 'general_notifications'
        };
    }

    /**
     * Format booking confirmation push notification
     */
    private function formatBookingConfirmationPush(array $variables): array
    {
        return [
            'title' => 'Booking Confirmed! 🎉',
            'body' => "Your {$variables['service_name']} booking for {$variables['scheduled_date']} has been confirmed.",
            'icon' => '/images/booking-confirmed-icon.png',
            'click_action' => url("/bookings/{$variables['booking_id']}"),
        ];
    }

    /**
     * Format booking reminder push notification
     */
    private function formatBookingReminderPush(array $variables): array
    {
        $timePhrase = $variables['hours_until'] <= 2 ? 'soon' : 'tomorrow';
        return [
            'title' => '⏰ Booking Reminder',
            'body' => "Your {$variables['service_name']} appointment is coming up {$timePhrase} at {$variables['scheduled_time']}.",
            'icon' => '/images/reminder-icon.png',
            'click_action' => url("/bookings/{$variables['booking_id']}"),
        ];
    }

    /**
     * Format booking cancellation push notification
     */
    private function formatBookingCancellationPush(array $variables): array
    {
        return [
            'title' => 'Booking Cancelled ❌',
            'body' => "Your {$variables['service_name']} booking for {$variables['scheduled_date']} has been cancelled.",
            'icon' => '/images/booking-cancelled-icon.png',
            'click_action' => url("/bookings/{$variables['booking_id']}"),
        ];
    }

    /**
     * Format booking rescheduled push notification
     */
    private function formatBookingRescheduledPush(array $variables): array
    {
        return [
            'title' => 'Booking Rescheduled 📅',
            'body' => "Your {$variables['service_name']} booking has been moved to {$variables['scheduled_datetime']}.",
            'icon' => '/images/booking-rescheduled-icon.png',
            'click_action' => url("/bookings/{$variables['booking_id']}"),
        ];
    }

    /**
     * Format payment reminder push notification
     */
    private function formatPaymentReminderPush(array $variables): array
    {
        return [
            'title' => '💳 Payment Reminder',
            'body' => "Payment of {$variables['amount_due']} is due for your upcoming {$variables['service_name']} appointment.",
            'icon' => '/images/payment-reminder-icon.png',
            'click_action' => url("/payments/{$variables['booking_id']}"),
        ];
    }

    /**
     * Format consultation reminder push notification
     */
    private function formatConsultationReminderPush(array $variables): array
    {
        return [
            'title' => '🎥 Consultation Reminder',
            'body' => "Your consultation is scheduled for {$variables['scheduled_datetime']}.",
            'icon' => '/images/consultation-reminder-icon.png',
            'click_action' => $variables['meeting_link'] ?: url("/consultations/{$variables['consultation_id']}"),
        ];
    }

    /**
     * Format consultation starting soon push notification
     */
    private function formatConsultationStartingSoonPush(array $variables): array
    {
        return [
            'title' => '🚀 Consultation Starting Soon!',
            'body' => 'Your consultation is starting in 15 minutes. Tap to join now.',
            'icon' => '/images/consultation-urgent-icon.png',
            'click_action' => $variables['meeting_link'] ?: url("/consultations/{$variables['consultation_id']}"),
        ];
    }

    /**
     * Log push notification activity
     */
    private function logPushActivity(array $deviceTokens, array $pushData, bool $success, array $metadata): void
    {
        Log::info('Push notification processed', [
            'device_count' => count($deviceTokens),
            'title' => $pushData['title'],
            'provider' => $this->provider,
            'success' => $success,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]);
    }
}

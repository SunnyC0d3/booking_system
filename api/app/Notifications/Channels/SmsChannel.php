<?php

namespace App\Notifications\Channels;

use App\Jobs\Notifications\SendSmsNotification;
use App\Services\V1\Notifications\SmsNotificationService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Exception;

class SmsChannel
{
    private SmsNotificationService $smsService;

    public function __construct(SmsNotificationService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        try {
            // Check if SMS is enabled
            if (!config('notifications.channels.sms.enabled', false)) {
                Log::info('SMS channel disabled, skipping notification', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'notification_class' => get_class($notification),
                ]);
                return;
            }

            // Get phone number from notifiable
            $phoneNumber = $this->getPhoneNumber($notifiable);
            if (!$phoneNumber) {
                Log::warning('No phone number available for SMS notification', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'notification_class' => get_class($notification),
                ]);
                return;
            }

            // Get SMS content from notification
            $smsData = $this->getSmsData($notification, $notifiable);
            if (!$smsData) {
                Log::warning('No SMS data available from notification', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'notification_class' => get_class($notification),
                ]);
                return;
            }

            // Check if we should queue the SMS or send immediately
            if ($this->shouldQueue($notification)) {
                $this->queueSms($phoneNumber, $smsData, $notifiable, $notification);
            } else {
                $this->sendSmsImmediately($phoneNumber, $smsData, $notifiable, $notification);
            }

        } catch (Exception $e) {
            Log::error('SMS channel failed to send notification', [
                'notifiable_id' => $notifiable->id ?? null,
                'notification_class' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            // Don't throw exception to prevent blocking other channels
        }
    }

    /**
     * Get phone number from notifiable
     */
    private function getPhoneNumber($notifiable): ?string
    {
        // Try multiple methods to get phone number
        $phoneNumber = null;

        // Method 1: Check if notifiable has routeNotificationForSms method
        if (method_exists($notifiable, 'routeNotificationForSms')) {
            $phoneNumber = $notifiable->routeNotificationForSms();
        }

        // Method 2: Check common phone number attributes
        if (!$phoneNumber && isset($notifiable->phone)) {
            $phoneNumber = $notifiable->phone;
        }

        if (!$phoneNumber && isset($notifiable->mobile)) {
            $phoneNumber = $notifiable->mobile;
        }

        if (!$phoneNumber && isset($notifiable->phone_number)) {
            $phoneNumber = $notifiable->phone_number;
        }

        // Method 3: Check for booking-specific phone number
        if (!$phoneNumber && method_exists($notifiable, 'bookings')) {
            $latestBooking = $notifiable->bookings()->latest()->first();
            if ($latestBooking && $latestBooking->client_phone) {
                $phoneNumber = $latestBooking->client_phone;
            }
        }

        return $phoneNumber ? $this->normalizePhoneNumber($phoneNumber) : null;
    }

    /**
     * Get SMS data from notification
     */
    private function getSmsData(Notification $notification, $notifiable): ?array
    {
        // Check if notification has toSms method
        if (method_exists($notification, 'toSms')) {
            $smsData = $notification->toSms($notifiable);

            if (is_string($smsData)) {
                return [
                    'message' => $smsData,
                    'type' => $this->getNotificationType($notification),
                ];
            }

            if (is_array($smsData)) {
                return array_merge($smsData, [
                    'type' => $this->getNotificationType($notification),
                ]);
            }
        }

        // Fallback: try to get content from other methods
        if (method_exists($notification, 'toArray')) {
            $arrayData = $notification->toArray($notifiable);

            if (isset($arrayData['sms'])) {
                return array_merge($arrayData['sms'], [
                    'type' => $this->getNotificationType($notification),
                ]);
            }

            // Try to create SMS from general data
            if (isset($arrayData['message']) || isset($arrayData['title'])) {
                return [
                    'message' => $arrayData['message'] ?? $arrayData['title'] ?? 'Notification',
                    'type' => $this->getNotificationType($notification),
                ];
            }
        }

        return null;
    }

    /**
     * Determine notification type from class name
     */
    private function getNotificationType(Notification $notification): string
    {
        $className = class_basename($notification);

        // Convert class name to snake_case
        $type = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));

        // Remove common suffixes
        $type = str_replace(['_notification', '_mail'], '', $type);

        return $type;
    }

    /**
     * Check if SMS should be queued
     */
    private function shouldQueue(Notification $notification): bool
    {
        // Check if notification implements ShouldQueue
        if ($notification instanceof \Illuminate\Contracts\Queue\ShouldQueue) {
            return true;
        }

        // Check for immediate sending flag
        if (method_exists($notification, 'shouldSendSmsImmediately')) {
            return !$notification->shouldSendSmsImmediately();
        }

        // Default to queuing for better performance
        return true;
    }

    /**
     * Queue SMS for later sending
     */
    private function queueSms(string $phoneNumber, array $smsData, $notifiable, Notification $notification): void
    {
        try {
            $metadata = [
                'notification_id' => $notification->id ?? null,
                'notifiable_id' => $notifiable->id ?? null,
                'notifiable_type' => get_class($notifiable),
                'notification_class' => get_class($notification),
            ];

            // Add booking or consultation IDs if available
            if (isset($smsData['booking_id'])) {
                $metadata['booking_id'] = $smsData['booking_id'];
            }

            if (isset($smsData['consultation_id'])) {
                $metadata['consultation_id'] = $smsData['consultation_id'];
            }

            // Get delay if specified
            $delay = $this->getSmsDelay($notification);

            $job = new SendSmsNotification(
                $phoneNumber,
                $smsData['message'],
                $smsData['type'],
                $metadata,
                $smsData['options'] ?? []
            );

            if ($delay) {
                $job->delay($delay);
            }

            dispatch($job);

            Log::info('SMS notification queued successfully', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'type' => $smsData['type'],
                'notifiable_id' => $notifiable->id ?? null,
                'delay' => $delay ? $delay->toISOString() : null,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to queue SMS notification', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send SMS immediately (synchronously)
     */
    private function sendSmsImmediately(string $phoneNumber, array $smsData, $notifiable, Notification $notification): void
    {
        try {
            $metadata = [
                'notification_id' => $notification->id ?? null,
                'notifiable_id' => $notifiable->id ?? null,
                'notifiable_type' => get_class($notifiable),
                'notification_class' => get_class($notification),
                'immediate' => true,
            ];

            $success = $this->smsService->sendRawSms(
                $phoneNumber,
                $smsData['message'],
                $metadata
            );

            if ($success) {
                Log::info('SMS notification sent immediately', [
                    'phone' => $this->maskPhoneNumber($phoneNumber),
                    'type' => $smsData['type'],
                    'notifiable_id' => $notifiable->id ?? null,
                ]);
            } else {
                Log::warning('Immediate SMS notification failed', [
                    'phone' => $this->maskPhoneNumber($phoneNumber),
                    'type' => $smsData['type'],
                    'notifiable_id' => $notifiable->id ?? null,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to send immediate SMS notification', [
                'phone' => $this->maskPhoneNumber($phoneNumber),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get SMS delay from notification
     */
    private function getSmsDelay(Notification $notification): ?\Carbon\Carbon
    {
        if (method_exists($notification, 'getSmsDelay')) {
            return $notification->getSmsDelay();
        }

        if (method_exists($notification, 'delay') && $notification->delay) {
            return now()->add($notification->delay);
        }

        return null;
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
     * Mask phone number for logging
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, -4) . '****';
    }

    /**
     * Check if phone number is valid
     */
    private function isValidPhoneNumber(string $phone): bool
    {
        // Basic validation for international format
        return preg_match('/^\+[1-9]\d{10,14}$/', $phone);
    }

    /**
     * Get user's SMS preferences
     */
    private function getUserSmsPreferences($notifiable): array
    {
        $preferences = [];

        if (method_exists($notifiable, 'getSmsPreferences')) {
            $preferences = $notifiable->getSmsPreferences();
        } elseif (isset($notifiable->notification_preferences)) {
            $preferences = $notifiable->notification_preferences['sms'] ?? [];
        }

        return $preferences;
    }

    /**
     * Check if user has opted out of SMS notifications
     */
    private function hasOptedOut($notifiable, string $notificationType = null): bool
    {
        $preferences = $this->getUserSmsPreferences($notifiable);

        // Check global SMS opt-out
        if (isset($preferences['enabled']) && !$preferences['enabled']) {
            return true;
        }

        // Check specific notification type opt-out
        if ($notificationType && isset($preferences['types'][$notificationType])) {
            return !$preferences['types'][$notificationType];
        }

        return false;
    }

    /**
     * Log SMS channel activity
     */
    private function logActivity(string $action, array $data): void
    {
        Log::info('SMS channel activity', array_merge([
            'action' => $action,
            'channel' => 'sms',
            'timestamp' => now()->toISOString(),
        ], $data));
    }

    /**
     * Handle SMS delivery confirmation (webhook)
     */
    public function handleDeliveryConfirmation(array $webhookData): void
    {
        try {
            Log::info('SMS delivery confirmation received', [
                'webhook_data' => $webhookData,
            ]);

            // TODO: Update notification status based on webhook data
            // This would typically update a notification record status

        } catch (Exception $e) {
            Log::error('Failed to handle SMS delivery confirmation', [
                'webhook_data' => $webhookData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get SMS statistics
     */
    public function getStatistics(): array
    {
        // This would return SMS-specific statistics
        return [
            'enabled' => config('notifications.channels.sms.enabled', false),
            'provider' => config('notifications.channels.sms.provider', 'twilio'),
            'queue' => config('notifications.channels.sms.queue', 'sms'),
            'rate_limiting_enabled' => config('notifications.rate_limiting.enabled', true),
        ];
    }
}

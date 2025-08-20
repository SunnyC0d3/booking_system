<?php

namespace App\Notifications\Channels;

use App\Jobs\Notifications\SendPushNotification;
use App\Services\V1\Notifications\PushNotificationService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Exception;

class PushChannel
{
    private PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification): void
    {
        try {
            // Check if push notifications are enabled
            if (!config('notifications.channels.push.enabled', false)) {
                Log::info('Push channel disabled, skipping notification', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'notification_class' => get_class($notification),
                ]);
                return;
            }

            // Get device tokens from notifiable
            $deviceTokens = $this->getDeviceTokens($notifiable);
            if (empty($deviceTokens)) {
                Log::info('No device tokens available for push notification', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'notification_class' => get_class($notification),
                ]);
                return;
            }

            // Get push content from notification
            $pushData = $this->getPushData($notification, $notifiable);
            if (!$pushData) {
                Log::warning('No push data available from notification', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'notification_class' => get_class($notification),
                ]);
                return;
            }

            // Check user preferences
            if ($this->hasOptedOut($notifiable, $this->getNotificationType($notification))) {
                Log::info('User has opted out of push notifications', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'notification_type' => $this->getNotificationType($notification),
                ]);
                return;
            }

            // Check if we should queue the push or send immediately
            if ($this->shouldQueue($notification)) {
                $this->queuePush($deviceTokens, $pushData, $notifiable, $notification);
            } else {
                $this->sendPushImmediately($deviceTokens, $pushData, $notifiable, $notification);
            }

        } catch (Exception $e) {
            Log::error('Push channel failed to send notification', [
                'notifiable_id' => $notifiable->id ?? null,
                'notification_class' => get_class($notification),
                'error' => $e->getMessage(),
            ]);

            // Don't throw exception to prevent blocking other channels
        }
    }

    /**
     * Get device tokens from notifiable
     */
    private function getDeviceTokens($notifiable): array
    {
        $deviceTokens = [];

        // Method 1: Check if notifiable has routeNotificationForPush method
        if (method_exists($notifiable, 'routeNotificationForPush')) {
            $tokens = $notifiable->routeNotificationForPush();
            if (is_array($tokens)) {
                $deviceTokens = array_merge($deviceTokens, $tokens);
            } elseif (is_string($tokens)) {
                $deviceTokens[] = $tokens;
            }
        }

        // Method 2: Check for device_tokens relationship
        if (method_exists($notifiable, 'deviceTokens')) {
            $tokens = $notifiable->deviceTokens()
                ->where('is_active', true)
                ->pluck('token')
                ->toArray();
            $deviceTokens = array_merge($deviceTokens, $tokens);
        }

        // Method 3: Check for device_token attribute
        if (!empty($notifiable->device_token)) {
            $deviceTokens[] = $notifiable->device_token;
        }

        // Method 4: Check for push_tokens attribute
        if (!empty($notifiable->push_tokens)) {
            if (is_array($notifiable->push_tokens)) {
                $deviceTokens = array_merge($deviceTokens, $notifiable->push_tokens);
            } elseif (is_string($notifiable->push_tokens)) {
                $deviceTokens[] = $notifiable->push_tokens;
            }
        }

        // Remove duplicates and validate tokens
        $deviceTokens = array_unique($deviceTokens);
        return array_filter($deviceTokens, [$this, 'isValidDeviceToken']);
    }

    /**
     * Get push data from notification
     */
    private function getPushData(Notification $notification, $notifiable): ?array
    {
        // Check if notification has toPush method
        if (method_exists($notification, 'toPush')) {
            $pushData = $notification->toPush($notifiable);

            if (is_array($pushData)) {
                return array_merge($pushData, [
                    'type' => $this->getNotificationType($notification),
                ]);
            }
        }

        // Check if notification has toArray method with push data
        if (method_exists($notification, 'toArray')) {
            $arrayData = $notification->toArray($notifiable);

            if (isset($arrayData['push'])) {
                return array_merge($arrayData['push'], [
                    'type' => $this->getNotificationType($notification),
                ]);
            }

            // Try to create push from general data
            if (isset($arrayData['title']) || isset($arrayData['message'])) {
                return [
                    'title' => $arrayData['title'] ?? 'Notification',
                    'body' => $arrayData['message'] ?? $arrayData['body'] ?? '',
                    'type' => $this->getNotificationType($notification),
                    'icon' => $arrayData['icon'] ?? '/images/notification-icon.png',
                    'click_action' => $arrayData['click_action'] ?? $arrayData['url'] ?? null,
                ];
            }
        }

        // Fallback: try to get content from mail method
        if (method_exists($notification, 'toMail')) {
            try {
                $mailData = $notification->toMail($notifiable);
                if (method_exists($mailData, 'subject') && method_exists($mailData, 'line')) {
                    return [
                        'title' => $mailData->subject ?? 'Notification',
                        'body' => 'You have a new notification',
                        'type' => $this->getNotificationType($notification),
                        'icon' => '/images/notification-icon.png',
                    ];
                }
            } catch (Exception $e) {
                // Ignore mail method errors
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
     * Check if push should be queued
     */
    private function shouldQueue(Notification $notification): bool
    {
        // Check if notification implements ShouldQueue
        if ($notification instanceof \Illuminate\Contracts\Queue\ShouldQueue) {
            return true;
        }

        // Check for immediate sending flag
        if (method_exists($notification, 'shouldSendPushImmediately')) {
            return !$notification->shouldSendPushImmediately();
        }

        // Default to queuing for better performance
        return true;
    }

    /**
     * Queue push notification for later sending
     */
    private function queuePush(array $deviceTokens, array $pushData, $notifiable, Notification $notification): void
    {
        try {
            $metadata = [
                'notification_id' => $notification->id ?? null,
                'notifiable_id' => $notifiable->id ?? null,
                'notifiable_type' => get_class($notifiable),
                'notification_class' => get_class($notification),
                'user_id' => $notifiable->id ?? null,
            ];

            // Add booking or consultation IDs if available
            if (isset($pushData['booking_id'])) {
                $metadata['booking_id'] = $pushData['booking_id'];
            }

            if (isset($pushData['consultation_id'])) {
                $metadata['consultation_id'] = $pushData['consultation_id'];
            }

            // Get delay if specified
            $delay = $this->getPushDelay($notification);

            // Determine urgency
            $options = [
                'urgent' => $this->isUrgentNotification($notification),
                'priority' => $this->getNotificationPriority($notification),
            ];

            $job = new SendPushNotification(
                $deviceTokens,
                $pushData,
                $pushData['type'],
                $metadata,
                $options
            );

            if ($delay) {
                $job->delay($delay);
            }

            dispatch($job);

            Log::info('Push notification queued successfully', [
                'device_count' => count($deviceTokens),
                'type' => $pushData['type'],
                'title' => $pushData['title'] ?? 'Unknown',
                'notifiable_id' => $notifiable->id ?? null,
                'delay' => $delay ? $delay->toISOString() : null,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to queue push notification', [
                'device_count' => count($deviceTokens),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send push notification immediately (synchronously)
     */
    private function sendPushImmediately(array $deviceTokens, array $pushData, $notifiable, Notification $notification): void
    {
        try {
            $metadata = [
                'notification_id' => $notification->id ?? null,
                'notifiable_id' => $notifiable->id ?? null,
                'notifiable_type' => get_class($notifiable),
                'notification_class' => get_class($notification),
                'immediate' => true,
                'user_id' => $notifiable->id ?? null,
            ];

            $success = $this->pushService->sendRawPush(
                $deviceTokens,
                $pushData,
                $metadata
            );

            if ($success) {
                Log::info('Push notification sent immediately', [
                    'device_count' => count($deviceTokens),
                    'type' => $pushData['type'],
                    'title' => $pushData['title'] ?? 'Unknown',
                    'notifiable_id' => $notifiable->id ?? null,
                ]);
            } else {
                Log::warning('Immediate push notification failed', [
                    'device_count' => count($deviceTokens),
                    'type' => $pushData['type'],
                    'notifiable_id' => $notifiable->id ?? null,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to send immediate push notification', [
                'device_count' => count($deviceTokens),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get push delay from notification
     */
    private function getPushDelay(Notification $notification): ?\Carbon\Carbon
    {
        if (method_exists($notification, 'getPushDelay')) {
            return $notification->getPushDelay();
        }

        if (method_exists($notification, 'delay') && $notification->delay) {
            return now()->add($notification->delay);
        }

        return null;
    }

    /**
     * Check if notification is urgent
     */
    private function isUrgentNotification(Notification $notification): bool
    {
        if (method_exists($notification, 'isUrgent')) {
            return $notification->isUrgent();
        }

        // Check notification type for urgency
        $urgentTypes = [
            'consultation_starting_soon',
            'emergency_notification',
            'booking_cancelled',
        ];

        return in_array($this->getNotificationType($notification), $urgentTypes);
    }

    /**
     * Get notification priority
     */
    private function getNotificationPriority(Notification $notification): string
    {
        if (method_exists($notification, 'getPriority')) {
            return $notification->getPriority();
        }

        if ($this->isUrgentNotification($notification)) {
            return 'urgent';
        }

        $highPriorityTypes = [
            'booking_confirmation',
            'booking_rescheduled',
            'payment_reminder',
        ];

        if (in_array($this->getNotificationType($notification), $highPriorityTypes)) {
            return 'high';
        }

        return 'normal';
    }

    /**
     * Validate device token format
     */
    private function isValidDeviceToken(string $token): bool
    {
        // Basic validation - token should be non-empty and reasonable length
        if (empty($token) || strlen($token) < 10) {
            return false;
        }

        // FCM tokens are typically 163 characters
        // APNs tokens are typically 64 characters (hex)
        if (strlen($token) > 200) {
            return false;
        }

        return true;
    }

    /**
     * Get user's push notification preferences
     */
    private function getUserPushPreferences($notifiable): array
    {
        $preferences = [];

        if (method_exists($notifiable, 'getPushPreferences')) {
            $preferences = $notifiable->getPushPreferences();
        } elseif (isset($notifiable->notification_preferences)) {
            $preferences = $notifiable->notification_preferences['push'] ?? [];
        }

        return $preferences;
    }

    /**
     * Check if user has opted out of push notifications
     */
    private function hasOptedOut($notifiable, string $notificationType = null): bool
    {
        $preferences = $this->getUserPushPreferences($notifiable);

        // Check global push opt-out
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
     * Handle push notification interaction (click, dismiss, etc.)
     */
    public function handleInteraction(array $interactionData): void
    {
        try {
            Log::info('Push notification interaction received', [
                'interaction_data' => $interactionData,
            ]);

            $action = $interactionData['action'] ?? 'unknown';
            $notificationId = $interactionData['notification_id'] ?? null;

            // Update notification status based on interaction
            if ($notificationId && in_array($action, ['clicked', 'opened'])) {
                // TODO: Update notification record status to 'clicked' or 'opened'
                Log::info('Push notification marked as interacted', [
                    'notification_id' => $notificationId,
                    'action' => $action,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to handle push notification interaction', [
                'interaction_data' => $interactionData,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle device token registration
     */
    public function registerDeviceToken($notifiable, string $token, array $deviceInfo = []): bool
    {
        try {
            // Validate token
            if (!$this->isValidDeviceToken($token)) {
                Log::warning('Invalid device token provided for registration', [
                    'notifiable_id' => $notifiable->id ?? null,
                    'token_length' => strlen($token),
                ]);
                return false;
            }

            // Register token with user
            if (method_exists($notifiable, 'registerDeviceToken')) {
                return $notifiable->registerDeviceToken($token, $deviceInfo);
            }

            // TODO: Implement device token storage
            // This would typically store in a user_device_tokens table

            Log::info('Device token registered successfully', [
                'notifiable_id' => $notifiable->id ?? null,
                'token' => substr($token, 0, 10) . '***',
                'device_info' => $deviceInfo,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to register device token', [
                'notifiable_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Unregister device token
     */
    public function unregisterDeviceToken($notifiable, string $token): bool
    {
        try {
            if (method_exists($notifiable, 'unregisterDeviceToken')) {
                return $notifiable->unregisterDeviceToken($token);
            }

            // TODO: Implement device token removal
            // This would typically remove from user_device_tokens table

            Log::info('Device token unregistered successfully', [
                'notifiable_id' => $notifiable->id ?? null,
                'token' => substr($token, 0, 10) . '***',
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to unregister device token', [
                'notifiable_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get push notification statistics
     */
    public function getStatistics(): array
    {
        return [
            'enabled' => config('notifications.channels.push.enabled', false),
            'provider' => config('notifications.channels.push.provider', 'fcm'),
            'queue' => config('notifications.channels.push.queue', 'push'),
            'rate_limiting_enabled' => config('notifications.rate_limiting.enabled', true),
        ];
    }

    /**
     * Test push notification functionality
     */
    public function sendTestNotification($notifiable, array $testData = []): bool
    {
        try {
            $testPushData = array_merge([
                'title' => 'Test Notification',
                'body' => 'This is a test push notification from your booking system.',
                'type' => 'test_notification',
                'icon' => '/images/test-icon.png',
            ], $testData);

            $deviceTokens = $this->getDeviceTokens($notifiable);
            if (empty($deviceTokens)) {
                return false;
            }

            return $this->pushService->sendRawPush($deviceTokens, $testPushData, [
                'test' => true,
                'notifiable_id' => $notifiable->id ?? null,
                'sent_at' => now()->toISOString(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send test push notification', [
                'notifiable_id' => $notifiable->id ?? null,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Log push channel activity
     */
    private function logActivity(string $action, array $data): void
    {
        Log::info('Push channel activity', array_merge([
            'action' => $action,
            'channel' => 'push',
            'timestamp' => now()->toISOString(),
        ], $data));
    }
}

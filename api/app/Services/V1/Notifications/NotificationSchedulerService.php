<?php

namespace App\Services\V1\Notifications;

use App\Constants\NotificationStatuses;
use App\Models\Booking;
use App\Models\BookingNotification;
use App\Models\ConsultationBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationSchedulerService
{
    private NotificationTemplateService $templateService;

    public function __construct(NotificationTemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * Schedule all notifications for a booking
     */
    public function scheduleBookingNotifications(Booking $booking): array
    {
        $scheduledNotifications = [];

        try {
            // Schedule confirmation notification (immediate)
            $confirmation = $this->scheduleBookingConfirmation($booking);
            if ($confirmation) {
                $scheduledNotifications[] = $confirmation;
            }

            // Schedule reminder notifications
            $reminders = $this->scheduleBookingReminders($booking);
            $scheduledNotifications = array_merge($scheduledNotifications, $reminders);

            // Schedule payment reminders if needed
            if ($booking->remaining_amount > 0) {
                $paymentReminders = $this->schedulePaymentReminders($booking);
                $scheduledNotifications = array_merge($scheduledNotifications, $paymentReminders);
            }

            Log::info('Scheduled booking notifications', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'notifications_scheduled' => count($scheduledNotifications),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to schedule booking notifications', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $scheduledNotifications;
    }

    /**
     * Schedule confirmation notification (immediate)
     */
    public function scheduleBookingConfirmation(Booking $booking): ?BookingNotification
    {
        try {
            return $this->createNotification([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'type' => 'booking_confirmation',
                'channels' => $this->getUserChannels($booking->user, 'booking_confirmations'),
                'scheduled_at' => now(),
                'priority' => 'high',
                'data' => [
                    'booking_reference' => $booking->booking_reference,
                    'service_name' => $booking->service->name,
                    'scheduled_at' => $booking->scheduled_at->toISOString(),
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to schedule booking confirmation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Schedule booking reminder notifications
     */
    public function scheduleBookingReminders(Booking $booking): array
    {
        $reminders = [];
        $reminderConfig = config('notifications.reminder_schedules.booking', []);

        if (!($reminderConfig['enabled'] ?? true)) {
            return $reminders;
        }

        $reminderTimes = $reminderConfig['times'] ?? [24, 2]; // Default: 24h and 2h before

        foreach ($reminderTimes as $hoursBeforeEvent) {
            $scheduledAt = $booking->scheduled_at->subHours($hoursBeforeEvent);

            // Only schedule if the reminder time is in the future
            if ($scheduledAt->isFuture()) {
                try {
                    $reminder = $this->createNotification([
                        'booking_id' => $booking->id,
                        'user_id' => $booking->user_id,
                        'type' => 'booking_reminder',
                        'channels' => $this->getUserChannels($booking->user, 'booking_reminders'),
                        'scheduled_at' => $scheduledAt,
                        'priority' => $hoursBeforeEvent <= 2 ? 'high' : 'normal',
                        'data' => [
                            'booking_reference' => $booking->booking_reference,
                            'service_name' => $booking->service->name,
                            'scheduled_at' => $booking->scheduled_at->toISOString(),
                            'hours_before' => $hoursBeforeEvent,
                            'reminder_type' => $hoursBeforeEvent <= 2 ? 'urgent' : 'advance',
                        ],
                    ]);

                    if ($reminder) {
                        $reminders[] = $reminder;
                    }

                } catch (Exception $e) {
                    Log::error('Failed to schedule booking reminder', [
                        'booking_id' => $booking->id,
                        'hours_before' => $hoursBeforeEvent,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $reminders;
    }

    /**
     * Schedule payment reminder notifications
     */
    public function schedulePaymentReminders(Booking $booking): array
    {
        $reminders = [];
        $paymentConfig = config('notifications.reminder_schedules.payment', []);

        if (!($paymentConfig['enabled'] ?? true)) {
            return $reminders;
        }

        // Calculate payment due date (typically 1 day before service)
        $dueDate = $booking->scheduled_at->subDay();
        $reminderTimes = $paymentConfig['times'] ?? [72, 24, 0]; // 3 days, 1 day, day of

        foreach ($reminderTimes as $hoursBeforeDue) {
            $scheduledAt = $dueDate->subHours($hoursBeforeDue);

            if ($scheduledAt->isFuture()) {
                try {
                    $reminder = $this->createNotification([
                        'booking_id' => $booking->id,
                        'user_id' => $booking->user_id,
                        'type' => 'payment_reminder',
                        'channels' => $this->getUserChannels($booking->user, 'payment_reminders'),
                        'scheduled_at' => $scheduledAt,
                        'priority' => $hoursBeforeDue === 0 ? 'high' : 'normal',
                        'data' => [
                            'booking_reference' => $booking->booking_reference,
                            'amount_due' => $booking->remaining_amount,
                            'due_date' => $dueDate->toISOString(),
                            'days_before_due' => intval($hoursBeforeDue / 24),
                            'is_final_notice' => $hoursBeforeDue === 0,
                        ],
                    ]);

                    if ($reminder) {
                        $reminders[] = $reminder;
                    }

                } catch (Exception $e) {
                    Log::error('Failed to schedule payment reminder', [
                        'booking_id' => $booking->id,
                        'hours_before_due' => $hoursBeforeDue,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $reminders;
    }

    /**
     * Schedule consultation notifications
     */
    public function scheduleConsultationNotifications(ConsultationBooking $consultation): array
    {
        $scheduledNotifications = [];

        try {
            // Schedule confirmation notification (immediate)
            $confirmation = $this->scheduleConsultationConfirmation($consultation);
            if ($confirmation) {
                $scheduledNotifications[] = $confirmation;
            }

            // Schedule reminder notifications
            $reminders = $this->scheduleConsultationReminders($consultation);
            $scheduledNotifications = array_merge($scheduledNotifications, $reminders);

            Log::info('Scheduled consultation notifications', [
                'consultation_id' => $consultation->id,
                'consultation_reference' => $consultation->consultation_reference,
                'notifications_scheduled' => count($scheduledNotifications),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to schedule consultation notifications', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $scheduledNotifications;
    }

    /**
     * Schedule consultation confirmation
     */
    private function scheduleConsultationConfirmation(ConsultationBooking $consultation): ?BookingNotification
    {
        try {
            return $this->createNotification([
                'booking_id' => $consultation->main_booking_id,
                'user_id' => $consultation->user_id,
                'type' => 'consultation_confirmation',
                'channels' => $this->getUserChannels($consultation->user, 'consultation_reminders'),
                'scheduled_at' => now(),
                'priority' => 'high',
                'data' => [
                    'consultation_id' => $consultation->id,
                    'consultation_reference' => $consultation->consultation_reference,
                    'scheduled_at' => $consultation->scheduled_at->toISOString(),
                    'format' => $consultation->format,
                    'meeting_link' => $consultation->meeting_link,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to schedule consultation confirmation', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Schedule consultation reminders
     */
    private function scheduleConsultationReminders(ConsultationBooking $consultation): array
    {
        $reminders = [];
        $reminderConfig = config('notifications.reminder_schedules.consultation', []);

        if (!($reminderConfig['enabled'] ?? true)) {
            return $reminders;
        }

        $reminderTimes = $reminderConfig['times'] ?? [24, 1]; // 24h and 1h before

        foreach ($reminderTimes as $hoursBeforeEvent) {
            $scheduledAt = $consultation->scheduled_at->subHours($hoursBeforeEvent);

            if ($scheduledAt->isFuture()) {
                try {
                    $reminder = $this->createNotification([
                        'booking_id' => $consultation->main_booking_id,
                        'user_id' => $consultation->user_id,
                        'type' => 'consultation_reminder',
                        'channels' => $this->getUserChannels($consultation->user, 'consultation_reminders'),
                        'scheduled_at' => $scheduledAt,
                        'priority' => $hoursBeforeEvent <= 1 ? 'urgent' : 'normal',
                        'data' => [
                            'consultation_id' => $consultation->id,
                            'consultation_reference' => $consultation->consultation_reference,
                            'scheduled_at' => $consultation->scheduled_at->toISOString(),
                            'hours_before' => $hoursBeforeEvent,
                            'format' => $consultation->format,
                            'meeting_link' => $consultation->meeting_link,
                        ],
                    ]);

                    if ($reminder) {
                        $reminders[] = $reminder;
                    }

                } catch (Exception $e) {
                    Log::error('Failed to schedule consultation reminder', [
                        'consultation_id' => $consultation->id,
                        'hours_before' => $hoursBeforeEvent,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Schedule "starting soon" notification (15 minutes before)
        $startingSoonAt = $consultation->scheduled_at->subMinutes(15);
        if ($startingSoonAt->isFuture()) {
            try {
                $startingSoon = $this->createNotification([
                    'booking_id' => $consultation->main_booking_id,
                    'user_id' => $consultation->user_id,
                    'type' => 'consultation_starting_soon',
                    'channels' => ['database', 'sms'], // High priority channels
                    'scheduled_at' => $startingSoonAt,
                    'priority' => 'urgent',
                    'data' => [
                        'consultation_id' => $consultation->id,
                        'consultation_reference' => $consultation->consultation_reference,
                        'scheduled_at' => $consultation->scheduled_at->toISOString(),
                        'meeting_link' => $consultation->meeting_link,
                        'format' => $consultation->format,
                    ],
                ]);

                if ($startingSoon) {
                    $reminders[] = $startingSoon;
                }

            } catch (Exception $e) {
                Log::error('Failed to schedule consultation starting soon notification', [
                    'consultation_id' => $consultation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $reminders;
    }

    /**
     * Cancel notifications for a booking
     */
    public function cancelBookingNotifications(Booking $booking, string $reason = 'Booking cancelled'): int
    {
        try {
            $cancelledCount = BookingNotification::where('booking_id', $booking->id)
                ->whereIn('status', [NotificationStatuses::PENDING, NotificationStatuses::QUEUED])
                ->update([
                    'status' => NotificationStatuses::CANCELLED,
                    'failure_reason' => $reason,
                    'cancelled_at' => now(),
                ]);

            Log::info('Cancelled booking notifications', [
                'booking_id' => $booking->id,
                'cancelled_count' => $cancelledCount,
                'reason' => $reason,
            ]);

            return $cancelledCount;

        } catch (Exception $e) {
            Log::error('Failed to cancel booking notifications', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Reschedule notifications for a booking
     */
    public function rescheduleBookingNotifications(Booking $booking, Carbon $oldDateTime): array
    {
        try {
            // Cancel existing pending notifications
            $this->cancelBookingNotifications($booking, 'Booking rescheduled');

            // Schedule new notifications with updated timing
            $newNotifications = $this->scheduleBookingNotifications($booking);

            Log::info('Rescheduled booking notifications', [
                'booking_id' => $booking->id,
                'old_datetime' => $oldDateTime->toISOString(),
                'new_datetime' => $booking->scheduled_at->toISOString(),
                'new_notifications_count' => count($newNotifications),
            ]);

            return $newNotifications;

        } catch (Exception $e) {
            Log::error('Failed to reschedule booking notifications', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Process overdue payment notifications
     */
    public function processOverduePayments(): int
    {
        try {
            $overdueBookings = Booking::where('payment_status', 'pending')
                ->where('scheduled_at', '>', now())
                ->where('remaining_amount', '>', 0)
                ->whereHas('payments', function ($query) {
                    $query->where('created_at', '<', now()->subDays(1));
                })
                ->get();

            $processedCount = 0;

            foreach ($overdueBookings as $booking) {
                $this->scheduleOverduePaymentNotification($booking);
                $processedCount++;
            }

            Log::info('Processed overdue payment notifications', [
                'processed_count' => $processedCount,
            ]);

            return $processedCount;

        } catch (Exception $e) {
            Log::error('Failed to process overdue payments', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Schedule overdue payment notification
     */
    private function scheduleOverduePaymentNotification(Booking $booking): ?BookingNotification
    {
        try {
            return $this->createNotification([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'type' => 'payment_overdue',
                'channels' => ['mail', 'database'],
                'scheduled_at' => now(),
                'priority' => 'high',
                'data' => [
                    'booking_reference' => $booking->booking_reference,
                    'amount_due' => $booking->remaining_amount,
                    'days_overdue' => now()->diffInDays($booking->created_at),
                    'is_overdue' => true,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Failed to schedule overdue payment notification', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get user's preferred notification channels for a type
     */
    private function getUserChannels(User $user, string $notificationType): array
    {
        $userPreferences = $user->notification_preferences ?? [];
        $defaultChannels = config("notifications.user_preferences.defaults.{$notificationType}", ['mail', 'database']);

        return $userPreferences[$notificationType] ?? $defaultChannels;
    }

    /**
     * Create a notification record
     */
    private function createNotification(array $data): ?BookingNotification
    {
        try {
            return BookingNotification::create([
                'booking_id' => $data['booking_id'],
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'channels' => json_encode($data['channels']),
                'scheduled_at' => $data['scheduled_at'],
                'status' => NotificationStatuses::PENDING,
                'priority' => $data['priority'] ?? 'normal',
                'data' => json_encode($data['data'] ?? []),
                'attempts' => 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create notification record', [
                'data' => $data,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get queue name based on priority
     */
    private function getQueueForPriority(string $priority): string
    {
        $queues = config('notifications.queues', []);

        return $queues[$priority] ?? $queues['normal'] ?? 'notifications';
    }

    /**
     * Check if rate limit allows sending notification
     */
    private function checkRateLimit(int $userId, string $notificationType): bool
    {
        $rateLimits = config('notifications.rate_limiting.limits', []);

        if (!config('notifications.rate_limiting.enabled', true)) {
            return true;
        }

        $limits = $rateLimits[$notificationType] ?? null;
        if (!$limits) {
            return true;
        }

        // Check hourly limit
        if (isset($limits['max_per_hour'])) {
            $recentCount = BookingNotification::where('user_id', $userId)
                ->where('type', $notificationType)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentCount >= $limits['max_per_hour']) {
                return false;
            }
        }

        // Check daily limit
        if (isset($limits['max_per_day'])) {
            $dailyCount = BookingNotification::where('user_id', $userId)
                ->where('type', $notificationType)
                ->where('created_at', '>=', now()->subDay())
                ->count();

            if ($dailyCount >= $limits['max_per_day']) {
                return false;
            }
        }

        return true;
    }
}

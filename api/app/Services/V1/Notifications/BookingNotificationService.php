<?php

namespace App\Services\V1\Notifications;

use App\Constants\NotificationStatuses;
use App\Jobs\Notifications\SendBookingReminder;
use App\Jobs\Notifications\ProcessNotificationBatch;
use App\Mail\BookingConfirmationMail;
use App\Mail\BookingReminderMail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingRescheduledMail;
use App\Mail\PaymentReminderMail;
use App\Models\Booking;
use App\Models\BookingNotification;
use App\Models\ConsultationBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;
use Exception;

class BookingNotificationService
{
    private NotificationSchedulerService $schedulerService;
    private NotificationTemplateService $templateService;
    private SmsNotificationService $smsService;
    private PushNotificationService $pushService;

    public function __construct(
        NotificationSchedulerService $schedulerService,
        NotificationTemplateService $templateService,
        SmsNotificationService $smsService,
        PushNotificationService $pushService
    ) {
        $this->schedulerService = $schedulerService;
        $this->templateService = $templateService;
        $this->smsService = $smsService;
        $this->pushService = $pushService;
    }

    /**
     * Send booking confirmation notification
     */
    public function sendBookingConfirmation(Booking $booking, array $options = []): bool
    {
        try {
            $booking->load(['user', 'service', 'serviceLocation', 'bookingAddOns.serviceAddOn']);

            // Get template variables
            $variables = $this->templateService->getBookingVariables($booking);

            // Get user's preferred channels
            $channels = $this->getUserChannels($booking->user, 'booking_confirmations');

            $success = true;

            // Send email notification
            if (in_array('mail', $channels)) {
                $emailSent = $this->sendEmailNotification(
                    $booking->user,
                    new BookingConfirmationMail($booking, $variables),
                    'booking_confirmation'
                );
                $success = $success && $emailSent;
            }

            // Send SMS notification
            if (in_array('sms', $channels)) {
                $smsSent = $this->smsService->sendBookingConfirmation($booking, $variables);
                $success = $success && $smsSent;
            }

            // Send push notification
            if (in_array('push', $channels)) {
                $pushSent = $this->pushService->sendBookingConfirmation($booking, $variables);
                $success = $success && $pushSent;
            }

            // Create database notification
            if (in_array('database', $channels)) {
                $this->createDatabaseNotification($booking, 'booking_confirmation', $variables);
            }

            // Log notification
            $this->logNotification('booking_confirmation', $booking->id, $channels, $success);

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking reminder notification
     */
    public function sendBookingReminder(Booking $booking, int $hoursUntil, string $reminderType = 'standard'): bool
    {
        try {
            $booking->load(['user', 'service', 'serviceLocation', 'bookingAddOns.serviceAddOn']);

            // Get template variables with reminder-specific data
            $variables = $this->templateService->getBookingVariables($booking);
            $variables['hours_until'] = $hoursUntil;
            $variables['reminder_type'] = $reminderType;
            $variables['urgency_level'] = $this->determineUrgencyLevel($hoursUntil);

            // Get user's preferred channels
            $channels = $this->getUserChannels($booking->user, 'booking_reminders');

            $success = true;

            // Send email notification
            if (in_array('mail', $channels)) {
                $emailSent = $this->sendEmailNotification(
                    $booking->user,
                    new BookingReminderMail($booking, $variables),
                    'booking_reminder'
                );
                $success = $success && $emailSent;
            }

            // Send SMS for urgent reminders
            if (in_array('sms', $channels) && $hoursUntil <= 4) {
                $smsSent = $this->smsService->sendBookingReminder($booking, $variables);
                $success = $success && $smsSent;
            }

            // Send push notification
            if (in_array('push', $channels)) {
                $pushSent = $this->pushService->sendBookingReminder($booking, $variables);
                $success = $success && $pushSent;
            }

            // Create database notification
            if (in_array('database', $channels)) {
                $this->createDatabaseNotification($booking, 'booking_reminder', $variables);
            }

            // Log notification
            $this->logNotification('booking_reminder', $booking->id, $channels, $success, [
                'hours_until' => $hoursUntil,
                'reminder_type' => $reminderType,
            ]);

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to send booking reminder', [
                'booking_id' => $booking->id,
                'hours_until' => $hoursUntil,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking cancellation notification
     */
    public function sendBookingCancellation(Booking $booking, string $reason = ''): bool
    {
        try {
            $booking->load(['user', 'service', 'serviceLocation']);

            // Get template variables
            $variables = $this->templateService->getBookingVariables($booking);
            $variables['cancellation_reason'] = $reason;
            $variables['cancelled_at'] = $booking->cancelled_at?->format('F j, Y \a\t g:i A');

            // Get user's preferred channels
            $channels = $this->getUserChannels($booking->user, 'booking_updates');

            $success = true;

            // Send email notification
            if (in_array('mail', $channels)) {
                $emailSent = $this->sendEmailNotification(
                    $booking->user,
                    new BookingCancelledMail($booking, $variables),
                    'booking_cancelled'
                );
                $success = $success && $emailSent;
            }

            // Send SMS notification for urgent cancellations
            if (in_array('sms', $channels) && $booking->scheduled_at->diffInHours(now()) <= 24) {
                $smsSent = $this->smsService->sendBookingCancellation($booking, $variables);
                $success = $success && $smsSent;
            }

            // Send push notification
            if (in_array('push', $channels)) {
                $pushSent = $this->pushService->sendBookingCancellation($booking, $variables);
                $success = $success && $pushSent;
            }

            // Create database notification
            if (in_array('database', $channels)) {
                $this->createDatabaseNotification($booking, 'booking_cancelled', $variables);
            }

            // Cancel any pending notifications for this booking
            $this->schedulerService->cancelBookingNotifications($booking, 'Booking cancelled');

            // Log notification
            $this->logNotification('booking_cancelled', $booking->id, $channels, $success, [
                'cancellation_reason' => $reason,
            ]);

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to send booking cancellation', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send booking rescheduled notification
     */
    public function sendBookingRescheduled(Booking $booking, Carbon $oldDateTime): bool
    {
        try {
            $booking->load(['user', 'service', 'serviceLocation']);

            // Get template variables
            $variables = $this->templateService->getBookingVariables($booking);
            $variables['old_scheduled_date'] = $oldDateTime->format('l, F j, Y');
            $variables['old_scheduled_time'] = $oldDateTime->format('g:i A');
            $variables['old_scheduled_datetime'] = $oldDateTime->format('l, F j, Y \a\t g:i A');

            // Get user's preferred channels
            $channels = $this->getUserChannels($booking->user, 'booking_updates');

            $success = true;

            // Send email notification
            if (in_array('mail', $channels)) {
                $emailSent = $this->sendEmailNotification(
                    $booking->user,
                    new BookingRescheduledMail($booking, $variables),
                    'booking_rescheduled'
                );
                $success = $success && $emailSent;
            }

            // Send SMS notification
            if (in_array('sms', $channels)) {
                $smsSent = $this->smsService->sendBookingRescheduled($booking, $variables);
                $success = $success && $smsSent;
            }

            // Send push notification
            if (in_array('push', $channels)) {
                $pushSent = $this->pushService->sendBookingRescheduled($booking, $variables);
                $success = $success && $pushSent;
            }

            // Create database notification
            if (in_array('database', $channels)) {
                $this->createDatabaseNotification($booking, 'booking_rescheduled', $variables);
            }

            // Reschedule notifications with new timing
            $this->schedulerService->rescheduleBookingNotifications($booking, $oldDateTime);

            // Log notification
            $this->logNotification('booking_rescheduled', $booking->id, $channels, $success, [
                'old_datetime' => $oldDateTime->toISOString(),
                'new_datetime' => $booking->scheduled_at->toISOString(),
            ]);

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to send booking rescheduled notification', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send payment reminder notification
     */
    public function sendPaymentReminder(Booking $booking, array $paymentData = []): bool
    {
        try {
            $booking->load(['user', 'service']);

            // Get template variables with payment data
            $variables = $this->templateService->getPaymentVariables($booking, $paymentData);

            // Get user's preferred channels
            $channels = $this->getUserChannels($booking->user, 'payment_reminders');

            $success = true;

            // Send email notification
            if (in_array('mail', $channels)) {
                $emailSent = $this->sendEmailNotification(
                    $booking->user,
                    new PaymentReminderMail($booking, $variables),
                    'payment_reminder'
                );
                $success = $success && $emailSent;
            }

            // Send SMS for overdue payments
            if (in_array('sms', $channels) && ($paymentData['is_overdue'] ?? false)) {
                $smsSent = $this->smsService->sendPaymentReminder($booking, $variables);
                $success = $success && $smsSent;
            }

            // Create database notification
            if (in_array('database', $channels)) {
                $this->createDatabaseNotification($booking, 'payment_reminder', $variables);
            }

            // Log notification
            $this->logNotification('payment_reminder', $booking->id, $channels, $success, $paymentData);

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to send payment reminder', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Process pending notifications
     */
    public function processPendingNotifications(): int
    {
        try {
            $pendingNotifications = BookingNotification::where('status', NotificationStatuses::PENDING)
                ->where('scheduled_at', '<=', now())
                ->where('attempts', '<', config('notifications.retry.max_attempts', 3))
                ->orderBy('priority')
                ->orderBy('scheduled_at')
                ->limit(100) // Process in batches
                ->get();

            $processed = 0;

            foreach ($pendingNotifications as $notification) {
                $success = $this->processNotification($notification);
                if ($success) {
                    $processed++;
                }
            }

            if ($processed > 0) {
                Log::info('Processed pending notifications', [
                    'processed_count' => $processed,
                    'total_pending' => $pendingNotifications->count(),
                ]);
            }

            return $processed;

        } catch (Exception $e) {
            Log::error('Failed to process pending notifications', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Process a single notification
     */
    private function processNotification(BookingNotification $notification): bool
    {
        try {
            $notification->update([
                'status' => NotificationStatuses::SENDING,
                'attempts' => $notification->attempts + 1,
                'last_attempted_at' => now(),
            ]);

            $booking = $notification->booking;
            if (!$booking) {
                $notification->update([
                    'status' => NotificationStatuses::FAILED,
                    'failure_reason' => 'Booking not found',
                ]);
                return false;
            }

            $data = json_decode($notification->data, true) ?? [];
            $success = false;

            switch ($notification->type) {
                case 'booking_confirmation':
                    $success = $this->sendBookingConfirmation($booking);
                    break;

                case 'booking_reminder':
                    $hoursUntil = $data['hours_before'] ?? 0;
                    $reminderType = $data['reminder_type'] ?? 'standard';
                    $success = $this->sendBookingReminder($booking, $hoursUntil, $reminderType);
                    break;

                case 'payment_reminder':
                    $success = $this->sendPaymentReminder($booking, $data);
                    break;

                default:
                    Log::warning('Unknown notification type', [
                        'notification_id' => $notification->id,
                        'type' => $notification->type,
                    ]);
                    break;
            }

            $notification->update([
                'status' => $success ? NotificationStatuses::SENT : NotificationStatuses::FAILED,
                'sent_at' => $success ? now() : null,
                'failure_reason' => $success ? null : 'Processing failed',
            ]);

            return $success;

        } catch (Exception $e) {
            $notification->update([
                'status' => NotificationStatuses::FAILED,
                'failure_reason' => $e->getMessage(),
            ]);

            Log::error('Failed to process notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get notification statistics for a booking
     */
    public function getBookingNotificationStats(Booking $booking): array
    {
        $notifications = BookingNotification::where('booking_id', $booking->id)->get();

        return [
            'total' => $notifications->count(),
            'sent' => $notifications->where('status', NotificationStatuses::SENT)->count(),
            'pending' => $notifications->where('status', NotificationStatuses::PENDING)->count(),
            'failed' => $notifications->where('status', NotificationStatuses::FAILED)->count(),
            'cancelled' => $notifications->where('status', NotificationStatuses::CANCELLED)->count(),
            'by_type' => $notifications->groupBy('type')->map->count(),
            'by_channel' => $this->groupNotificationsByChannel($notifications),
            'last_sent' => $notifications->whereNotNull('sent_at')->max('sent_at'),
            'next_scheduled' => $notifications->where('status', NotificationStatuses::PENDING)->min('scheduled_at'),
        ];
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(User $user, $mailable, string $type): bool
    {
        try {
            Mail::to($user->email)->send($mailable);

            $this->templateService->logTemplateUsage($type, 'email', $user->id);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create database notification
     */
    private function createDatabaseNotification(Booking $booking, string $type, array $data): void
    {
        try {
            $booking->user->notify(new \App\Notifications\BookingNotification([
                'type' => $type,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'data' => $data,
            ]));
        } catch (Exception $e) {
            Log::error('Failed to create database notification', [
                'booking_id' => $booking->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user's preferred notification channels
     */
    private function getUserChannels(User $user, string $notificationType): array
    {
        $userPreferences = $user->notification_preferences ?? [];
        $defaultChannels = config("notifications.user_preferences.defaults.{$notificationType}", ['mail', 'database']);

        return $userPreferences[$notificationType] ?? $defaultChannels;
    }

    /**
     * Determine urgency level based on hours until event
     */
    private function determineUrgencyLevel(int $hoursUntil): string
    {
        if ($hoursUntil <= 2) return 'high';
        if ($hoursUntil <= 24) return 'medium';
        return 'low';
    }

    /**
     * Group notifications by channel
     */
    private function groupNotificationsByChannel($notifications): array
    {
        $channelStats = [];

        foreach ($notifications as $notification) {
            $channels = json_decode($notification->channels, true) ?? [];
            foreach ($channels as $channel) {
                $channelStats[$channel] = ($channelStats[$channel] ?? 0) + 1;
            }
        }

        return $channelStats;
    }

    /**
     * Log notification activity
     */
    private function logNotification(string $type, int $bookingId, array $channels, bool $success, array $metadata = []): void
    {
        Log::info('Booking notification processed', [
            'type' => $type,
            'booking_id' => $bookingId,
            'channels' => $channels,
            'success' => $success,
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ]);
    }
}

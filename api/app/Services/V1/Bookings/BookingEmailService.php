<?php

namespace App\Services\V1\Bookings;

use App\Mail\BookingConfirmationMail;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingReminderMail;
use App\Models\Booking;
use App\Models\BookingNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class BookingEmailService
{
    /**
     * Send booking confirmation email
     */
    public function sendBookingConfirmation(Booking $booking): bool
    {
        try {
            // Load necessary relationships
            $booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn', 'user']);

            // Send the email
            Mail::to($booking->client_email)->send(new BookingConfirmationMail($booking));

            // Create notification record
            BookingNotification::createImmediateNotification(
                $booking->id,
                'booking_created',
                'email',
                $booking->client_email,
                'Your booking confirmation has been sent.',
                ['email_type' => 'booking_confirmation']
            );

            Log::info('Booking confirmation email sent', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'service_name' => $booking->service->name,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation email', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send booking cancellation email
     */
    public function sendBookingCancellation(
        Booking $booking,
        ?string $cancellationReason = null,
        ?string $cancelledBy = null
    ): bool
    {
        try {
            // Load necessary relationships
            $booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn', 'user']);

            // Send the email
            Mail::to($booking->client_email)->send(new BookingCancelledMail(
                $booking,
                $cancellationReason,
                $cancelledBy
            ));

            // Create notification record
            BookingNotification::createImmediateNotification(
                $booking->id,
                'booking_cancelled',
                'email',
                $booking->client_email,
                'Your booking cancellation notice has been sent.',
                [
                    'email_type' => 'booking_cancelled',
                    'cancellation_reason' => $cancellationReason,
                    'cancelled_by' => $cancelledBy,
                ]
            );

            Log::info('Booking cancellation email sent', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'cancellation_reason' => $cancellationReason,
                'cancelled_by' => $cancelledBy,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send booking cancellation email', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send booking reminder email
     */
    public function sendBookingReminder(
        Booking $booking,
        int     $hoursUntilBooking = 24,
        string  $reminderType = 'standard'
    ): bool
    {
        try {
            // Load necessary relationships
            $booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn', 'user']);

            // Send the email
            Mail::to($booking->client_email)->send(new BookingReminderMail(
                $booking,
                $hoursUntilBooking,
                $reminderType
            ));

            // Create notification record
            BookingNotification::createImmediateNotification(
                $booking->id,
                'booking_reminder',
                'email',
                $booking->client_email,
                "Booking reminder sent ({$hoursUntilBooking} hours before service).",
                [
                    'email_type' => 'booking_reminder',
                    'hours_until_booking' => $hoursUntilBooking,
                    'reminder_type' => $reminderType,
                ]
            );

            Log::info('Booking reminder email sent', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'hours_until_booking' => $hoursUntilBooking,
                'reminder_type' => $reminderType,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send booking reminder email', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'hours_until_booking' => $hoursUntilBooking,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Send booking confirmed email (when admin confirms a pending booking)
     */
    public function sendBookingConfirmed(Booking $booking): bool
    {
        try {
            // Load necessary relationships
            $booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn', 'user']);

            // For now, we'll use the confirmation email template
            // Later you can create a specific BookingConfirmedMail class
            Mail::to($booking->client_email)->send(new BookingConfirmationMail($booking));

            // Create notification record
            BookingNotification::createImmediateNotification(
                $booking->id,
                'booking_confirmed',
                'email',
                $booking->client_email,
                'Your booking has been confirmed by our team.',
                ['email_type' => 'booking_confirmed']
            );

            Log::info('Booking confirmed email sent', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'confirmed_at' => now(),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to send booking confirmed email', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'client_email' => $booking->client_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Queue all scheduled notifications for a booking
     */
    public function scheduleAllNotifications(Booking $booking): array
    {
        $scheduled = [];

        try {
            // Schedule booking reminder (24 hours before)
            if ($booking->scheduled_at->isFuture()) {
                $reminderNotification = BookingNotification::scheduleBookingReminder($booking->id, 60); // 1 hour before
                $scheduled[] = $reminderNotification;
            }

            // Schedule consultation reminder if needed
            if ($booking->requires_consultation && $booking->scheduled_at->isFuture()) {
                $consultationNotification = BookingNotification::scheduleConsultationReminder($booking->id, 24 * 60); // 24 hours before
                $scheduled[] = $consultationNotification;
            }

            // Schedule payment reminder if payment is pending
            if ($booking->payment_status === 'pending') {
                $paymentNotification = BookingNotification::schedulePaymentReminder($booking->id, 24); // 24 hours after booking
                $scheduled[] = $paymentNotification;
            }

            // Schedule follow-up (24 hours after service)
            $followUpNotification = BookingNotification::scheduleFollowUp($booking->id, 24);
            $scheduled[] = $followUpNotification;

            Log::info('All notifications scheduled for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'notifications_scheduled' => count($scheduled),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to schedule notifications for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'error' => $e->getMessage(),
            ]);
        }

        return $scheduled;
    }

    /**
     * Cancel all pending notifications for a booking
     */
    public function cancelAllNotifications(Booking $booking): int
    {
        try {
            $cancelledCount = BookingNotification::where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->delete();

            Log::info('Cancelled pending notifications for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'cancelled_count' => $cancelledCount,
            ]);

            return $cancelledCount;

        } catch (Exception $e) {
            Log::error('Failed to cancel notifications for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Reschedule all notifications when booking time changes
     */
    public function rescheduleNotifications(Booking $booking, $oldScheduledAt): array
    {
        $rescheduled = [];

        try {
            // Get all pending notifications for this booking
            $notifications = BookingNotification::where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->get();

            foreach ($notifications as $notification) {
                $newScheduledAt = null;

                switch ($notification->type) {
                    case 'booking_reminder':
                        $minutesBefore = $notification->metadata['minutes_before'] ?? 60;
                        $newScheduledAt = $booking->scheduled_at->clone()->subMinutes($minutesBefore);
                        break;

                    case 'consultation_reminder':
                        $minutesBefore = $notification->metadata['minutes_before'] ?? (24 * 60);
                        $newScheduledAt = $booking->scheduled_at->clone()->subMinutes($minutesBefore);
                        break;

                    case 'follow_up':
                        $hoursAfter = $notification->metadata['hours_after_service'] ?? 24;
                        $newScheduledAt = $booking->ends_at->clone()->addHours($hoursAfter);
                        break;
                }

                if ($newScheduledAt) {
                    $notification->reschedule($newScheduledAt);
                    $rescheduled[] = $notification;
                }
            }

            Log::info('Rescheduled notifications for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'old_scheduled_at' => $oldScheduledAt,
                'new_scheduled_at' => $booking->scheduled_at,
                'rescheduled_count' => count($rescheduled),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to reschedule notifications for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'error' => $e->getMessage(),
            ]);
        }

        return $rescheduled;
    }

    /**
     * Process pending notifications (called by scheduler/queue)
     */
    public function processPendingNotifications(): int
    {
        $processed = 0;

        try {
            $notifications = BookingNotification::where('status', 'pending')
                ->where('scheduled_at', '<=', now())
                ->with(['booking.service', 'booking.serviceLocation'])
                ->get();

            foreach ($notifications as $notification) {
                $success = $this->processNotification($notification);

                if ($success) {
                    $notification->markAsSent();
                    $processed++;
                } else {
                    $notification->markAsFailed('Failed to send email');
                }
            }

            if ($processed > 0) {
                Log::info('Processed pending booking notifications', [
                    'processed_count' => $processed,
                    'total_notifications' => $notifications->count(),
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to process pending notifications', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $processed;
    }

    /**
     * Process a single notification
     */
    private function processNotification(BookingNotification $notification): bool
    {
        try {
            $booking = $notification->booking;

            switch ($notification->type) {
                case 'booking_reminder':
                    $hoursUntil = $booking->scheduled_at->diffInHours(now());
                    return $this->sendBookingReminder($booking, $hoursUntil, 'scheduled');

                case 'consultation_reminder':
                    // TODO: Create ConsultationReminderMail and implement
                    Log::info('Consultation reminder would be sent', [
                        'booking_id' => $booking->id,
                        'notification_id' => $notification->id,
                    ]);
                    return true;

                case 'payment_reminder':
                    // TODO: Create PaymentReminderMail and implement
                    Log::info('Payment reminder would be sent', [
                        'booking_id' => $booking->id,
                        'notification_id' => $notification->id,
                    ]);
                    return true;

                case 'follow_up':
                    // TODO: Create FollowUpMail and implement
                    Log::info('Follow-up would be sent', [
                        'booking_id' => $booking->id,
                        'notification_id' => $notification->id,
                    ]);
                    return true;

                default:
                    Log::warning('Unknown notification type', [
                        'type' => $notification->type,
                        'notification_id' => $notification->id,
                    ]);
                    return false;
            }

        } catch (Exception $e) {
            Log::error('Failed to process notification', [
                'notification_id' => $notification->id,
                'notification_type' => $notification->type,
                'booking_id' => $notification->booking_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Retry failed notifications
     */
    public function retryFailedNotifications(): int
    {
        $retried = 0;

        try {
            $failedNotifications = BookingNotification::retryable()->get();

            foreach ($failedNotifications as $notification) {
                $notification->retry();
                $retried++;
            }

            if ($retried > 0) {
                Log::info('Retried failed notifications', [
                    'retried_count' => $retried,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to retry notifications', [
                'error' => $e->getMessage(),
            ]);
        }

        return $retried;
    }

    /**
     * Get notification statistics for a booking
     */
    public function getBookingNotificationStats(Booking $booking): array
    {
        try {
            return BookingNotification::getStatisticsForBooking($booking->id);
        } catch (Exception $e) {
            Log::error('Failed to get notification stats', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'total' => 0,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'delivery_rate' => 0,
                'failure_rate' => 0,
            ];
        }
    }

    /**
     * Queue a notification for processing
     */
    public function queueNotification(BookingNotification $notification, ?int $delaySeconds = null): void
    {
        try {
            $job = new \App\Jobs\ProcessBookingNotificationJob($notification);

            if ($delaySeconds) {
                $job->delay($delaySeconds);
            } else {
                // Calculate delay based on scheduled time
                $delay = $notification->scheduled_at->isFuture()
                    ? $notification->scheduled_at->diffInSeconds(now())
                    : 0;

                if ($delay > 0) {
                    $job->delay($delay);
                }
            }

            dispatch($job);

            Log::info('Notification queued for processing', [
                'notification_id' => $notification->id,
                'booking_id' => $notification->booking_id,
                'type' => $notification->type,
                'scheduled_at' => $notification->scheduled_at,
                'delay_seconds' => $delay ?? $delaySeconds ?? 0,
            ]);

        } catch (Exception $e) {
            Log::error('Failed to queue notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to immediate processing
            $this->processNotification($notification);
        }
    }

    /**
     * Queue all scheduled notifications for a booking
     */
    public function queueAllNotifications(Booking $booking): array
    {
        $queued = [];

        try {
            // Get all pending notifications for this booking
            $notifications = BookingNotification::where('booking_id', $booking->id)
                ->where('status', 'pending')
                ->where('scheduled_at', '>', now())
                ->get();

            foreach ($notifications as $notification) {
                $this->queueNotification($notification);
                $queued[] = $notification;
            }

            Log::info('All notifications queued for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'queued_count' => count($queued),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to queue notifications for booking', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $queued;
    }

    /**
     * Send immediate notification (bypasses queue)
     */
    public function sendImmediateNotification(
        Booking $booking,
        string  $type,
        array   $metadata = []
    ): bool
    {
        try {
            // Create immediate notification record
            $notification = BookingNotification::createImmediateNotification(
                $booking->id,
                $type,
                'email',
                $booking->client_email,
                null,
                $metadata
            );

            // Process immediately without queue
            $success = $this->processNotification($notification);

            if ($success) {
                $notification->markAsSent();
            } else {
                $notification->markAsFailed('Immediate processing failed');
            }

            return $success;

        } catch (Exception $e) {
            Log::error('Failed to send immediate notification', [
                'booking_id' => $booking->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process overdue notifications immediately
     */
    public function processOverdueNotifications(): int
    {
        $processed = 0;

        try {
            // Get notifications that are overdue by more than 5 minutes
            $overdueNotifications = BookingNotification::where('status', 'pending')
                ->where('scheduled_at', '<', now()->subMinutes(5))
                ->with(['booking.service', 'booking.serviceLocation'])
                ->orderBy('scheduled_at')
                ->limit(50) // Process in batches
                ->get();

            foreach ($overdueNotifications as $notification) {
                try {
                    // Queue with high priority for immediate processing
                    $job = new \App\Jobs\ProcessBookingNotificationJob($notification);
                    dispatch($job->onQueue('high'));

                    $processed++;

                } catch (Exception $e) {
                    Log::error('Failed to queue overdue notification', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($processed > 0) {
                Log::info('Processed overdue notifications', [
                    'processed_count' => $processed,
                ]);
            }

        } catch (Exception $e) {
            Log::error('Failed to process overdue notifications', [
                'error' => $e->getMessage(),
            ]);
        }

        return $processed;
    }

    /**
     * Enhanced scheduling with queue integration
     */
    public function scheduleAllNotificationsWithQueue(Booking $booking): array
    {
        $scheduled = [];

        try {
            // Create all notification records first
            $notifications = $this->scheduleAllNotifications($booking);

            // Then queue each one for processing
            foreach ($notifications as $notification) {
                $this->queueNotification($notification);
                $scheduled[] = $notification;
            }

            Log::info('All notifications scheduled and queued for booking', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'scheduled_count' => count($scheduled),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to schedule and queue notifications', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $scheduled;
    }

    /**
     * Batch process notifications for multiple bookings
     */
    public function batchProcessNotifications(array $bookingIds, int $batchSize = 10): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'queued' => 0,
        ];

        try {
            $bookings = Booking::whereIn('id', $bookingIds)
                ->with(['service', 'serviceLocation'])
                ->get();

            $chunks = $bookings->chunk($batchSize);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $booking) {
                    try {
                        $notifications = $this->queueAllNotifications($booking);
                        $results['queued'] += count($notifications);
                        $results['processed']++;

                    } catch (Exception $e) {
                        $results['failed']++;
                        Log::error('Failed to process booking in batch', [
                            'booking_id' => $booking->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Small delay between chunks to prevent overwhelming the queue
                usleep(100000); // 0.1 seconds
            }

            Log::info('Batch notification processing completed', $results);

        } catch (Exception $e) {
            Log::error('Batch notification processing failed', [
                'error' => $e->getMessage(),
                'booking_ids' => $bookingIds,
            ]);
        }

        return $results;
    }

    /**
     * Get queue statistics for monitoring
     */
    public function getQueueStatistics(): array
    {
        try {
            return [
                'pending_notifications' => BookingNotification::where('status', 'pending')->count(),
                'sent_today' => BookingNotification::where('status', 'sent')
                    ->whereDate('sent_at', today())->count(),
                'failed_notifications' => BookingNotification::where('status', 'failed')->count(),
                'retryable_notifications' => BookingNotification::retryable()->count(),
                'queue_jobs' => \DB::table('jobs')->count(),
                'failed_jobs' => \DB::table('failed_jobs')->count(),
                'processed_jobs_today' => \DB::table('job_batches')
                    ->whereDate('created_at', today())
                    ->sum('total_jobs'),
            ];
        } catch (Exception $e) {
            Log::error('Failed to get queue statistics', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Health check for the notification system
     */
    public function healthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'statistics' => [],
            'recommendations' => [],
        ];

        try {
            $stats = $this->getQueueStatistics();
            $health['statistics'] = $stats;

            // Check for issues
            if ($stats['failed_jobs'] > 50) {
                $health['status'] = 'warning';
                $health['issues'][] = "High number of failed jobs: {$stats['failed_jobs']}";
                $health['recommendations'][] = 'Review failed jobs and fix underlying issues';
            }

            if ($stats['retryable_notifications'] > 20) {
                $health['status'] = 'warning';
                $health['issues'][] = "High number of retryable notifications: {$stats['retryable_notifications']}";
                $health['recommendations'][] = 'Check email service configuration';
            }

            if ($stats['queue_jobs'] > 500) {
                $health['status'] = 'warning';
                $health['issues'][] = "High number of queued jobs: {$stats['queue_jobs']}";
                $health['recommendations'][] = 'Consider scaling queue workers';
            }

            // Check for stale notifications
            $staleNotifications = BookingNotification::where('status', 'pending')
                ->where('scheduled_at', '<', now()->subHours(24))
                ->count();

            if ($staleNotifications > 0) {
                $health['status'] = 'error';
                $health['issues'][] = "Stale notifications detected: {$staleNotifications}";
                $health['recommendations'][] = 'Run notification cleanup command';
            }

            if (empty($health['issues'])) {
                $health['status'] = 'healthy';
            }

        } catch (Exception $e) {
            $health['status'] = 'error';
            $health['issues'][] = 'Health check failed: ' . $e->getMessage();

            Log::error('Notification system health check failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return $health;
    }
}

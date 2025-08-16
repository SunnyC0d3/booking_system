<?php

namespace App\Services\V1\Bookings;

use App\Constants\BookingStatuses;
use App\Constants\PaymentStatuses;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Resources\V1\BookingResource;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class BookingService
{
    use ApiResponses;

    private BookingEmailService $emailService;

    public function __construct(BookingEmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Mark booking as in progress
     */
    public function markBookingInProgress(Request $request, Booking $booking)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to update booking status.', 403);
        }

        if ($booking->status !== BookingStatuses::CONFIRMED) {
            throw new Exception('Only confirmed bookings can be marked as in progress', 422);
        }

        $booking->update([
            'status' => BookingStatuses::IN_PROGRESS,
            'started_at' => now(),
        ]);

        Log::info('Booking marked as in progress', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'admin_user_id' => $user->id,
        ]);

        return $this->ok('Booking marked as in progress', [
            'booking' => new BookingResource($booking)
        ]);
    }

    /**
     * Mark booking as completed
     */
    public function markBookingCompleted(Request $request, Booking $booking)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to update booking status.', 403);
        }

        if (!in_array($booking->status, [BookingStatuses::IN_PROGRESS, BookingStatuses::CONFIRMED])) {
            throw new Exception('Only in-progress or confirmed bookings can be marked as completed', 422);
        }

        $booking->update([
            'status' => BookingStatuses::COMPLETED,
            'completed_at' => now(),
        ]);

        // TODO: Trigger follow-up email logic here if needed
        // The follow-up is already scheduled, but you might want immediate completion notification

        Log::info('Booking marked as completed', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'admin_user_id' => $user->id,
            'completed_at' => now()->toDateTimeString(),
        ]);

        return $this->ok('Booking marked as completed', [
            'booking' => new BookingResource($booking)
        ]);
    }

    /**
     * Mark booking as no-show
     */
    public function markBookingNoShow(Request $request, Booking $booking)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to update booking status.', 403);
        }

        if (!in_array($booking->status, [BookingStatuses::CONFIRMED, BookingStatuses::IN_PROGRESS])) {
            throw new Exception('Only confirmed or in-progress bookings can be marked as no-show', 422);
        }

        return DB::transaction(function () use ($booking, $user) {
            $booking->update([
                'status' => BookingStatuses::NO_SHOW,
                'no_show_at' => now(),
            ]);

            // ✅ CANCEL REMAINING NOTIFICATIONS
            $this->emailService->cancelAllNotifications($booking);

            Log::info('Booking marked as no-show', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'admin_user_id' => $user->id,
                'no_show_at' => now()->toDateTimeString(),
            ]);

            return $this->ok('Booking marked as no-show', [
                'booking' => new BookingResource($booking)
            ]);
        });
    }

    /**
     * Helper method: Validate booking availability
     */
    private function validateBookingAvailability(Service $service, Carbon $scheduledAt, int $durationMinutes, ?ServiceLocation $location)
    {
        // TODO: Implement availability checking logic
        // This should check against:
        // - Service availability windows
        // - Existing bookings
        // - Calendar integrations
        // - Service capacity limits
    }

    /**
     * Helper method: Calculate booking price
     */
    private function calculateBookingPrice(Service $service, array $addOns = []): int
    {
        $total = $service->base_price;

        // Add add-on prices
        foreach ($addOns as $addOn) {
            $serviceAddOn = $service->addOns()->find($addOn['service_add_on_id']);
            if ($serviceAddOn) {
                $quantity = $addOn['quantity'] ?? 1;
                $total += $serviceAddOn->price * $quantity;
            }
        }

        return $total;
    }

    /**
     * Helper method: Generate unique booking reference
     */
    private function generateBookingReference(): string
    {
        do {
            $reference = 'BK' . strtoupper(substr(uniqid(), -8));
        } while (Booking::where('booking_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Helper method: Add booking add-ons
     */
    private function addBookingAddOns(Booking $booking, array $addOns)
    {
        foreach ($addOns as $addOn) {
            $serviceAddOn = $booking->service->addOns()->find($addOn['service_add_on_id']);
            if ($serviceAddOn) {
                $quantity = $addOn['quantity'] ?? 1;
                $booking->bookingAddOns()->create([
                    'service_add_on_id' => $serviceAddOn->id,
                    'quantity' => $quantity,
                    'unit_price' => $serviceAddOn->price,
                    'total_price' => $serviceAddOn->price * $quantity,
                ]);
            }
        }
    }

    /**
     * Enhanced createBooking with queue integration
     */
    public function createBooking(Request $request)
    {
        $data = $request->validated();
        $user = $request->user();

        if (!$user->hasPermission('create_own_bookings')) {
            return $this->error('You do not have permission to create bookings.', 403);
        }

        return DB::transaction(function () use ($data, $user) {
            $service = Service::findOrFail($data['service_id']);
            if (!$service->isAvailableForBooking()) {
                throw new Exception('Service is not available for booking', 422);
            }

            $location = null;
            if (!empty($data['service_location_id'])) {
                $location = ServiceLocation::where('service_id', $service->id)
                    ->where('id', $data['service_location_id'])
                    ->where('is_active', true)
                    ->firstOrFail();
            }

            // Parse and validate booking time
            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $durationMinutes = $data['duration_minutes'] ?? $service->duration_minutes;
            $endsAt = $scheduledAt->clone()->addMinutes($durationMinutes);

            // Validate availability
            $this->validateBookingAvailability($service, $scheduledAt, $durationMinutes, $location);

            // Calculate pricing
            $totalAmount = $this->calculateBookingPrice($service, $data['add_ons'] ?? []);

            // Generate unique booking reference
            $bookingReference = $this->generateBookingReference();

            // Create the booking
            $booking = Booking::create([
                'booking_reference' => $bookingReference,
                'user_id' => $user->id,
                'service_id' => $service->id,
                'service_location_id' => $location?->id,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $durationMinutes,
                'base_price' => $service->base_price,
                'total_amount' => $totalAmount,
                'status' => BookingStatuses::PENDING,
                'payment_status' => PaymentStatuses::PENDING,
                'client_name' => $data['client_name'] ?? $user->name,
                'client_email' => $data['client_email'] ?? $user->email,
                'client_phone' => $data['client_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'special_requirements' => $data['special_requirements'] ?? null,
                'requires_consultation' => $data['requires_consultation'] ?? false,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Add booking add-ons
            if (!empty($data['add_ons'])) {
                $this->addBookingAddOns($booking, $data['add_ons']);
            }

            // ✅ SEND IMMEDIATE CONFIRMATION EMAIL
            $this->emailService->sendImmediateNotification(
                $booking,
                'booking_created',
                ['email_type' => 'booking_confirmation']
            );

            // ✅ SCHEDULE ALL FUTURE NOTIFICATIONS WITH QUEUE
            $this->emailService->scheduleAllNotificationsWithQueue($booking);

            // Log booking creation
            Log::info('Booking created successfully with notifications scheduled', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'user_id' => $user->id,
                'service_id' => $service->id,
                'total_amount' => $totalAmount,
                'scheduled_at' => $scheduledAt->toDateTimeString(),
            ]);

            return $this->ok('Booking created successfully', [
                'booking' => new BookingResource($booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn']))
            ]);
        });
    }

    /**
     * Enhanced cancelBooking with queue integration
     */
    public function cancelBooking(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check permissions
        if (!$user->hasPermission('delete_own_bookings') && !$user->hasPermission('delete_all_bookings')) {
            return $this->error('You do not have permission to cancel bookings.', 403);
        }

        // Ensure user can only cancel their own bookings unless they're admin
        if ($booking->user_id !== $user->id && !$user->hasPermission('delete_all_bookings')) {
            return $this->error('You can only cancel your own bookings.', 403);
        }

        if (!$booking->canBeCancelled()) {
            throw new Exception('Booking cannot be cancelled', 422);
        }

        return DB::transaction(function () use ($booking, $request, $user) {
            $cancellationReason = $request->input('reason', 'Cancelled by ' .
                ($user->hasRole(['admin', 'super admin']) ? 'admin' : 'client'));

            $cancelledBy = $user->hasRole(['admin', 'super admin']) ? 'admin' : 'client';

            $booking->update([
                'status' => BookingStatuses::CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $cancellationReason,
            ]);

            // ✅ SEND IMMEDIATE CANCELLATION EMAIL
            $this->emailService->sendImmediateNotification(
                $booking,
                'booking_cancelled',
                [
                    'email_type' => 'booking_cancelled',
                    'cancellation_reason' => $cancellationReason,
                    'cancelled_by' => $cancelledBy,
                ]
            );

            // ✅ CANCEL ALL PENDING NOTIFICATIONS
            $cancelledNotifications = $this->emailService->cancelAllNotifications($booking);

            // Log cancellation
            Log::info('Booking cancelled with notifications cleaned up', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'cancelled_by' => $user->id,
                'reason' => $cancellationReason,
                'cancelled_notifications' => $cancelledNotifications,
            ]);

            return $this->ok('Booking cancelled successfully', [
                'booking' => new BookingResource($booking)
            ]);
        });
    }

    /**
     * Enhanced confirmBooking with queue integration
     */
    public function confirmBooking(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check admin permissions
        if (!$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to confirm bookings.', 403);
        }

        if ($booking->status !== BookingStatuses::PENDING) {
            throw new Exception('Only pending bookings can be confirmed', 422);
        }

        return DB::transaction(function () use ($booking, $user) {
            $booking->update([
                'status' => BookingStatuses::CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => $user->id,
            ]);

            // ✅ SEND IMMEDIATE CONFIRMATION EMAIL
            $this->emailService->sendImmediateNotification(
                $booking,
                'booking_confirmed',
                [
                    'email_type' => 'booking_confirmed',
                    'confirmed_by' => $user->id,
                    'confirmed_at' => now()->toDateTimeString(),
                ]
            );

            Log::info('Booking confirmed by admin with notification sent', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'admin_user_id' => $user->id,
                'confirmed_at' => now()->toDateTimeString(),
            ]);

            return $this->ok('Booking confirmed successfully', [
                'booking' => new BookingResource($booking)
            ]);
        });
    }

    /**
     * Enhanced updateBooking with queue integration
     */
    public function updateBooking(Request $request, Booking $booking)
    {
        $user = $request->user();
        $data = $request->validated();

        // Check permissions
        if (!$user->hasPermission('edit_own_bookings') && !$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to update bookings.', 403);
        }

        // Ensure user can only update their own bookings unless they're admin
        if ($booking->user_id !== $user->id && !$user->hasPermission('edit_all_bookings')) {
            return $this->error('You can only update your own bookings.', 403);
        }

        // Check if booking can be modified
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            throw new Exception('Booking cannot be modified in current status', 422);
        }

        return DB::transaction(function () use ($booking, $data, $user) {
            $originalScheduledAt = $booking->scheduled_at;
            $wasRescheduled = false;

            // Update allowed fields
            $allowedFields = [
                'client_name',
                'client_email',
                'client_phone',
                'notes',
                'special_requirements'
            ];

            // Admins can update additional fields
            if ($user->hasPermission('edit_all_bookings')) {
                $allowedFields = array_merge($allowedFields, [
                    'status',
                    'payment_status',
                    'consultation_notes'
                ]);
            }

            $updateData = array_intersect_key($data, array_flip($allowedFields));

            // Handle rescheduling if scheduled_at is being changed
            if (isset($data['scheduled_at']) && $user->hasPermission('edit_all_bookings')) {
                $newScheduledAt = Carbon::parse($data['scheduled_at']);
                if (!$originalScheduledAt->equalTo($newScheduledAt)) {
                    $updateData['scheduled_at'] = $newScheduledAt;
                    $updateData['ends_at'] = $newScheduledAt->clone()->addMinutes($booking->duration_minutes);
                    $wasRescheduled = true;
                }
            }

            if (!empty($updateData)) {
                $booking->update($updateData);
            }

            // ✅ HANDLE EMAIL NOTIFICATIONS FOR RESCHEDULING
            if ($wasRescheduled) {
                // Send immediate rescheduling notification
                $this->emailService->sendImmediateNotification(
                    $booking,
                    'booking_rescheduled',
                    [
                        'email_type' => 'booking_rescheduled',
                        'original_time' => $originalScheduledAt->toDateTimeString(),
                        'new_time' => $booking->scheduled_at->toDateTimeString(),
                        'updated_by' => $user->id,
                    ]
                );

                // ✅ RESCHEDULE ALL PENDING NOTIFICATIONS
                $this->emailService->rescheduleNotifications($booking, $originalScheduledAt);

                Log::info('Booking rescheduled with notifications updated', [
                    'booking_id' => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'original_time' => $originalScheduledAt->toDateTimeString(),
                    'new_time' => $booking->scheduled_at->toDateTimeString(),
                    'updated_by' => $user->id,
                ]);
            }

            // Log update
            Log::info('Booking updated successfully', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'updated_by' => $user->id,
                'updated_fields' => array_keys($updateData),
                'was_rescheduled' => $wasRescheduled,
            ]);

            return $this->ok('Booking updated successfully', [
                'booking' => new BookingResource($booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn']))
            ]);
        });
    }

    /**
     * Get notification statistics for a booking
     */
    public function getBookingNotificationStats(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check permissions
        if (!$user->hasPermission('view_all_bookings') && $booking->user_id !== $user->id) {
            return $this->error('You do not have permission to view notification statistics.', 403);
        }

        $stats = $this->emailService->getBookingNotificationStats($booking);
        $queueStats = $this->emailService->getQueueStatistics();

        return $this->ok('Notification statistics retrieved', [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->booking_reference,
            'notification_stats' => $stats,
            'queue_stats' => $queueStats,
        ]);
    }

    /**
     * Get system health check
     */
    public function getSystemHealth(Request $request)
    {
        $user = $request->user();

        // Check admin permissions
        if (!$user->hasPermission('view_all_bookings')) {
            return $this->error('You do not have permission to view system health.', 403);
        }

        $health = $this->emailService->healthCheck();

        return $this->ok('System health retrieved', [
            'health' => $health,
            'checked_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Process overdue notifications manually (admin only)
     */
    public function processOverdueNotifications(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to process notifications.', 403);
        }

        $processed = $this->emailService->processOverdueNotifications();

        Log::info('Manual overdue notification processing completed', [
            'processed_count' => $processed,
            'triggered_by' => $user->id,
        ]);

        return $this->ok('Overdue notifications processed', [
            'processed_count' => $processed,
        ]);
    }
}

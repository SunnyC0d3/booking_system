<?php

namespace App\Services\V1\Bookings;

use App\Constants\BookingStatuses;
use App\Constants\ConsultationStatuses;
use App\Constants\PaymentStatuses;
use App\Filters\V1\BookingFilter;
use App\Mail\BookingCancelledMail;
use App\Mail\BookingConfirmationMail;
use App\Mail\ConsultationConfirmationMail;
use App\Models\Booking;
use App\Models\ConsultationBooking;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\User;
use App\Resources\V1\BookingResource;
use App\Resources\V1\ConsultationBookingResource;
use App\Services\V1\Emails\BookingEmailService;
use App\Traits\V1\ApiResponses;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BookingService
{
    use ApiResponses;

    private BookingEmailService $emailService;
    private TimeSlotService $timeSlotService;
    private ConsultationBookingService $consultationService; // ✅ INJECT CONSULTATION SERVICE

    public function __construct(
        BookingEmailService        $emailService,
        TimeSlotService            $timeSlotService,
        ConsultationBookingService $consultationService // ✅ INJECT CONSULTATION SERVICE
    )
    {
        $this->emailService = $emailService;
        $this->timeSlotService = $timeSlotService;
        $this->consultationService = $consultationService; // ✅ ASSIGN CONSULTATION SERVICE
    }

    /**
     * ✅ REFACTORED: Create booking with proper consultation integration
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
            $depositAmount = $this->calculateDepositAmount($totalAmount, $service);

            // Generate unique booking reference
            $bookingReference = $this->generateBookingReference();

            // Create the main booking
            $booking = Booking::create([
                'booking_reference' => $bookingReference,
                'user_id' => $user->id,
                'service_id' => $service->id,
                'service_location_id' => $location?->id,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $durationMinutes,
                'base_price' => $service->price,
                'total_amount' => $totalAmount,
                'deposit_amount' => $depositAmount,
                'remaining_amount' => $totalAmount - $depositAmount,
                'status' => $service->requires_consultation ? BookingStatuses::PENDING : BookingStatuses::CONFIRMED,
                'payment_status' => PaymentStatuses::PENDING,
                'client_name' => $data['client_name'] ?? $user->name,
                'client_email' => $data['client_email'] ?? $user->email,
                'client_phone' => $data['client_phone'] ?? $user->phone,
                'notes' => $data['notes'] ?? null,
                'special_requirements' => $data['special_requirements'] ?? null,
                'requires_consultation' => $service->requires_consultation,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Add booking add-ons if specified
            if (!empty($data['add_ons'])) {
                $this->addBookingAddOns($booking, $data['add_ons']);
            }

            // ✅ CONSULTATION INTEGRATION: Use ConsultationBookingService
            $consultation = null;
            if ($service->requires_consultation) {
                try {
                    $consultation = $this->createAutoConsultationViaService($booking, $service, $user, $data);

                    // Link consultation to booking
                    $booking->update([
                        'consultation_booking_id' => $consultation->id,
                        'consultation_notes' => "Pre-booking consultation scheduled for {$consultation->scheduled_at->format('M j, Y \a\t g:i A')}"
                    ]);
                } catch (Exception $e) {
                    Log::error('Failed to create auto-consultation', [
                        'booking_id' => $booking->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without consultation but log the error
                }
            }

            // Send confirmation emails
            $this->sendBookingConfirmation($booking, $consultation);

            // Schedule reminders
            $this->scheduleBookingReminders($booking);

            // Log booking creation
            Log::info('Booking created with consultation integration', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'consultation_id' => $consultation?->id,
                'consultation_reference' => $consultation?->consultation_reference ?? null,
                'user_id' => $user->id,
                'service_id' => $service->id,
                'requires_consultation' => $service->requires_consultation,
                'scheduled_at' => $scheduledAt->toDateTimeString(),
            ]);

            $response = [
                'booking' => new BookingResource($booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn']))
            ];

            // Include consultation details if created
            if ($consultation) {
                $response['consultation'] = new ConsultationBookingResource($consultation->load(['service']));
                $response['next_steps'] = [
                    'consultation_required' => true,
                    'consultation_scheduled_at' => $consultation->scheduled_at->toDateTimeString(),
                    'consultation_format' => $consultation->format,
                    'preparation_instructions' => $consultation->preparation_instructions,
                ];
            }

            return $this->ok(
                $consultation ?
                    'Booking created successfully. Consultation has been automatically scheduled.' :
                    'Booking created successfully.',
                $response
            );
        });
    }

    /**
     * ✅ SIMPLIFIED: Use ConsultationBookingService to create auto-consultation
     */
    private function createAutoConsultationViaService(Booking $booking, Service $service, User $user, array $bookingData): ?ConsultationBooking
    {
        // Prepare consultation data for the ConsultationBookingService
        $consultationData = $this->prepareAutoConsultationData($booking, $service, $user, $bookingData);

        // Create a validated request for the consultation service
        $consultationRequest = new \Illuminate\Http\Request($consultationData);
        $consultationRequest->setUserResolver(function () use ($user) {
            return $user;
        });

        // Mock validation by setting validated data
        $consultationRequest->merge($consultationData);

        // ✅ USE CONSULTATIONBOOKINGSERVICE - No duplication!
        $result = $this->consultationService->createConsultation($consultationRequest);

        // Extract the consultation from the service response
        if (isset($result['data']['consultation'])) {
            return $result['data']['consultation']->resource ?? $result['data']['consultation'];
        }

        return null;
    }

    /**
     * ✅ HELPER: Prepare consultation data based on booking requirements
     */
    private function prepareAutoConsultationData(Booking $booking, Service $service, User $user, array $bookingData): array
    {
        // Calculate optimal consultation date (typically 3-7 days before booking)
        $consultationDate = $this->calculateOptimalConsultationDate($booking->scheduled_at, $service);

        return [
            'service_id' => $service->id,
            'main_booking_id' => $booking->id,
            'scheduled_at' => $consultationDate->toDateTimeString(),
            'duration_minutes' => $service->consultation_duration_minutes ?? 30,
            'type' => 'pre_booking', // Auto-created consultations are pre-booking type
            'format' => $this->determineOptimalConsultationFormat($service, $bookingData),
            'client_name' => $booking->client_name,
            'client_email' => $booking->client_email,
            'client_phone' => $booking->client_phone,
            'consultation_notes' => $this->generateAutoConsultationNotes($booking, $service, $bookingData),
            'metadata' => [
                'auto_created' => true,
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'priority' => $this->determineConsultationPriority($booking, $service),
            ],
        ];
    }

    /**
     * ✅ HELPER: Calculate optimal consultation date
     */
    private function calculateOptimalConsultationDate(Carbon $bookingDate, Service $service): Carbon
    {
        $daysBeforeBooking = $service->consultation_lead_days ?? 5; // Default 5 days before
        $minDaysAdvance = 1; // At least 1 day advance notice

        $idealConsultationDate = $bookingDate->clone()->subDays($daysBeforeBooking);
        $earliestPossibleDate = now()->addDays($minDaysAdvance);

        // If ideal date is too soon, use earliest possible date
        if ($idealConsultationDate->lt($earliestPossibleDate)) {
            $idealConsultationDate = $earliestPossibleDate;
        }

        // Adjust to business hours (9 AM to 6 PM on weekdays)
        return $this->adjustToBusinessHours($idealConsultationDate);
    }

    /**
     * ✅ HELPER: Adjust date to business hours
     */
    private function adjustToBusinessHours(Carbon $date): Carbon
    {
        // If weekend, move to next Monday
        if ($date->isWeekend()) {
            $date = $date->next(Carbon::MONDAY);
        }

        // Set to 10 AM (good default consultation time)
        $date->setTime(10, 0, 0);

        return $date;
    }

    /**
     * ✅ HELPER: Determine optimal consultation format
     */
    private function determineOptimalConsultationFormat(Service $service, array $bookingData): string
    {
        // Check if booking specified preference
        if (!empty($bookingData['preferred_consultation_format'])) {
            return $bookingData['preferred_consultation_format'];
        }

        // Use service-based smart defaults
        return match ($service->service_type ?? 'standard') {
            'on_site', 'venue_based' => 'site_visit',
            'design_heavy' => 'video',
            'simple_consultation' => 'phone',
            default => 'video' // Default to video for most services
        };
    }

    /**
     * ✅ HELPER: Generate consultation notes for auto-created consultations
     */
    private function generateAutoConsultationNotes(Booking $booking, Service $service, array $bookingData): string
    {
        $notes = "Automatically scheduled pre-booking consultation for {$service->name}.\n\n";

        $notes .= "Booking Details:\n";
        $notes .= "- Service: {$service->name}\n";
        $notes .= "- Scheduled Date: {$booking->scheduled_at->format('l, F j, Y \a\t g:i A')}\n";
        $notes .= "- Duration: {$booking->duration_minutes} minutes\n";

        if ($booking->serviceLocation) {
            $notes .= "- Location: {$booking->serviceLocation->name}\n";
        }

        if (!empty($booking->special_requirements)) {
            $notes .= "\nSpecial Requirements:\n{$booking->special_requirements}\n";
        }

        if (!empty($booking->notes)) {
            $notes .= "\nClient Notes:\n{$booking->notes}\n";
        }

        $notes .= "\nConsultation Purpose:\n";
        $notes .= "- Confirm service requirements and expectations\n";
        $notes .= "- Discuss timeline and logistics\n";
        $notes .= "- Review pricing and any additional needs\n";
        $notes .= "- Address any questions or concerns\n";

        return $notes;
    }

    /**
     * ✅ HELPER: Determine consultation priority
     */
    private function determineConsultationPriority(Booking $booking, Service $service): string
    {
        // High value bookings get higher priority
        if ($booking->total_amount > 500000) { // £5000+
            return 'high';
        }

        // Complex services get medium priority
        if ($service->complexity_level === 'complex' || $booking->duration_minutes > 240) {
            return 'medium';
        }

        // Urgent bookings (within 7 days) get higher priority
        if ($booking->scheduled_at->diffInDays(now()) <= 7) {
            return 'medium';
        }

        return 'normal';
    }

    /**
     * ✅ SIMPLIFIED: Complete booking consultation - reuse ConsultationBookingService
     */
    public function completeBookingConsultation(Request $request, Booking $booking): array
    {
        $data = $request->validated();
        $user = $request->user();

        if (!$booking->requires_consultation) {
            return $this->error('This booking does not require a consultation.', 422);
        }

        if ($booking->consultation_completed_at) {
            return $this->error('Consultation has already been completed for this booking.', 422);
        }

        return DB::transaction(function () use ($booking, $data, $user) {
            // If there's a linked consultation, complete it using ConsultationBookingService
            if ($booking->consultation_booking_id) {
                $consultation = ConsultationBooking::find($booking->consultation_booking_id);

                if ($consultation && $consultation->status !== ConsultationStatuses::COMPLETED) {
                    // Prepare completion data for ConsultationBookingService
                    $completionData = [
                        'outcome_summary' => $data['consultation_summary'] ?? 'Consultation completed via booking system',
                        'feasibility_assessment' => $data['approve_booking'] ?? true ? 'feasible' : 'not_feasible',
                        'recommendations' => $data['recommendations'] ?? null,
                        'estimated_cost' => $booking->total_amount,
                        'follow_up_required' => false,
                    ];

                    // Create request for consultation completion
                    $completionRequest = new \Illuminate\Http\Request($completionData);
                    $completionRequest->setUserResolver(function () use ($user) {
                        return $user;
                    });
                    $completionRequest->merge($completionData);

                    // ✅ USE CONSULTATIONBOOKINGSERVICE - No duplication!
                    $this->consultationService->completeConsultation($completionRequest, $consultation);
                }
            }

            // Update booking with consultation completion
            $booking->update([
                'consultation_completed' => true,
                'consultation_completed_at' => now(),
                'consultation_summary' => $data['consultation_summary'] ?? null,
                'status' => $data['approve_booking'] ?? true ? BookingStatuses::CONFIRMED : BookingStatuses::CANCELLED,
            ]);

            // Send appropriate notifications
            if ($booking->status === BookingStatuses::CONFIRMED) {
                Mail::to($booking->client_email)->send(new BookingConfirmationMail($booking));
            } else {
                Mail::to($booking->client_email)->send(new BookingCancelledMail($booking, 'Consultation did not meet requirements'));
            }

            Log::info('Booking consultation completed via booking service', [
                'booking_id' => $booking->id,
                'consultation_booking_id' => $booking->consultation_booking_id,
                'approved' => $booking->status === BookingStatuses::CONFIRMED,
                'completed_by' => $user->id,
            ]);

            return $this->ok('Consultation completed successfully', [
                'booking' => new BookingResource($booking->load(['service', 'serviceLocation'])),
                'status' => $booking->status === BookingStatuses::CONFIRMED ? 'approved' : 'cancelled',
            ]);
        });
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

    // ✅ BOOKING-SPECIFIC HELPER METHODS (Keep these)

    /**
     * Validate booking availability
     */
    private function validateBookingAvailability(Service $service, Carbon $scheduledAt, int $durationMinutes, ?ServiceLocation $location)
    {
        // Check if the service is available for booking
        if (!$service->isAvailableForBooking()) {
            throw new Exception('Service is not currently available for booking', 422);
        }

        // Calculate end time
        $endsAt = $scheduledAt->clone()->addMinutes($durationMinutes);

        // Check if the requested time is in the past
        if ($scheduledAt->lte(now())) {
            throw new Exception('Cannot book appointments in the past', 422);
        }

        // Check minimum advance booking requirements
        $minAdvanceHours = $service->min_advance_booking_hours ?? 2;
        $minBookingTime = now()->addHours($minAdvanceHours);

        if ($scheduledAt->lt($minBookingTime)) {
            throw new Exception("Bookings must be made at least {$minAdvanceHours} hours in advance", 422);
        }

        // Check maximum advance booking restrictions
        $maxAdvanceDays = $service->max_advance_booking_days ?? 365;
        $maxBookingTime = now()->addDays($maxAdvanceDays);

        if ($scheduledAt->gt($maxBookingTime)) {
            throw new Exception("Bookings cannot be made more than {$maxAdvanceDays} days in advance", 422);
        }

        // Get available slots for the specific date to validate availability
        $dateStart = $scheduledAt->clone()->startOfDay();
        $dateEnd = $scheduledAt->clone()->endOfDay();

        $availableSlots = $this->timeSlotService->getAvailableSlots(
            $service,
            $dateStart,
            $dateEnd,
            $location,
            $durationMinutes
        );

        // Check if the requested time slot is available
        $isSlotAvailable = $availableSlots->contains(function ($slot) use ($scheduledAt, $endsAt) {
            $slotStart = $slot['start_time'];
            $slotEnd = $slot['end_time'];

            // Check if the requested booking fits within this available slot
            return $scheduledAt->greaterThanOrEqualTo($slotStart) &&
                $endsAt->lessThanOrEqualTo($slotEnd) &&
                $slot['is_available'] === true;
        });

        if (!$isSlotAvailable) {
            throw new Exception('The requested time slot is not available', 422);
        }

        // Validate using the TimeSlotService's validation errors
        $validationErrors = $this->timeSlotService->validateBookingTime(
            $service,
            $scheduledAt,
            $location,
            $durationMinutes
        );

        if (!empty($validationErrors)) {
            throw new Exception(implode('; ', $validationErrors), 422);
        }

        // Additional business rule validations
        $this->validateBusinessRules($service, $scheduledAt, $durationMinutes, $location);
    }

    /**
     * Additional business rule validations
     */
    private function validateBusinessRules(Service $service, Carbon $scheduledAt, int $durationMinutes, ?ServiceLocation $location)
    {
        // Check if service requires consultation and if one has been completed
        if ($service->requires_consultation) {
            // This could be enhanced to check if consultation has been completed
            // For now, we'll allow booking with consultation requirement
        }

        // Check location-specific rules
        if ($location) {
            // Validate if the service can be provided at this location
            if (!$location->is_active) {
                throw new Exception('The selected location is not currently available', 422);
            }

            // Check location-specific advance booking requirements
            if ($location->min_advance_booking_hours &&
                $scheduledAt->lt(now()->addHours($location->min_advance_booking_hours))) {
                throw new Exception("This location requires at least {$location->min_advance_booking_hours} hours advance booking", 422);
            }
        }

        // Check service-specific duration constraints
        if ($durationMinutes < $service->duration_minutes) {
            throw new Exception("Booking duration cannot be less than the service's minimum duration of {$service->duration_minutes} minutes", 422);
        }

        $maxDuration = $service->max_duration_minutes ?? 480; // 8 hours default max
        if ($durationMinutes > $maxDuration) {
            throw new Exception("Booking duration cannot exceed {$maxDuration} minutes", 422);
        }

        // Check for blackout dates (if implemented)
        $this->validateBlackoutDates($service, $scheduledAt, $location);
    }

    /**
     * Check for blackout dates
     */
    private function validateBlackoutDates(Service $service, Carbon $scheduledAt, ?ServiceLocation $location)
    {
        // Check for service availability exceptions that block this date
        $exceptions = $service->availabilityExceptions()
            ->where('exception_type', 'blocked')
            ->where('start_date', '<=', $scheduledAt->toDateString())
            ->where(function ($query) use ($scheduledAt) {
                $query->where('end_date', '>=', $scheduledAt->toDateString())
                    ->orWhereNull('end_date');
            })
            ->when($location, function ($query, $location) {
                $query->where(function ($q) use ($location) {
                    $q->where('service_location_id', $location->id)
                        ->orWhereNull('service_location_id');
                });
            })
            ->where('is_active', true)
            ->exists();

        if ($exceptions) {
            throw new Exception('The requested date is blocked and not available for booking', 422);
        }
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
     * Helper method: Calculate deposit amount
     */
    private function calculateDepositAmount(int $totalAmount, Service $service): int
    {
        // Check if service has a custom deposit requirement
        if ($service->deposit_type === 'fixed') {
            return $service->deposit_amount ?? 0;
        }

        if ($service->deposit_type === 'percentage') {
            $percentage = $service->deposit_percentage ?? 20; // Default 20%
            return (int)round(($totalAmount * $percentage) / 100);
        }

        // Default: 20% deposit
        return (int)round($totalAmount * 0.2);
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
     * Enhanced booking confirmation that includes consultation details
     */
    private function sendBookingConfirmation(Booking $booking, ?ConsultationBooking $consultation = null): void
    {
        try {
            // Send main booking confirmation
            Mail::to($booking->client_email)->send(new BookingConfirmationMail($booking));

            // Send separate consultation confirmation if consultation was auto-created
            if ($consultation) {
                Mail::to($consultation->client_email)->send(new ConsultationConfirmationMail($consultation, $booking));
            }

            Log::info('Booking confirmation emails sent', [
                'booking_id' => $booking->id,
                'consultation_included' => $consultation !== null,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation emails', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }            // Send main booking confirmation

    /**
     * Schedule booking reminders
     */
    private function scheduleBookingReminders(Booking $booking): void
    {
        try {
            // Use the email service to schedule booking reminders
            $this->emailService->scheduleBookingReminders($booking);

            Log::info('Booking reminders scheduled', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to schedule booking reminders', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ✅ ESSENTIAL MISSING METHODS FOR CONTROLLERS

    /**
     * Get all bookings (admin view)
     */
    public function getAllBookings(Request $request, BookingFilter $filter): array
    {
        $user = $request->user();

        if (!$user->hasPermission('view_all_bookings')) {
            return $this->error('You do not have permission to view all bookings.', 403);
        }

        $request->validated();

        $query = Booking::with(['service', 'user', 'serviceLocation', 'bookingAddOns.serviceAddOn'])
            ->filter($filter);

        $perPage = $request->input('per_page', 25);
        $perPage = min($perPage, 100); // Maximum 100 items per page

        $bookings = $query->paginate($perPage);

        return $this->ok('All bookings retrieved successfully', [
            'bookings' => BookingResource::collection($bookings->items()),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'last_page' => $bookings->lastPage(),
                'has_more_pages' => $bookings->hasMorePages(),
            ],
            'filters_applied' => $request->only([
                'user_id', 'service_id', 'status', 'payment_status',
                'date_from', 'date_to', 'search', 'sort', 'per_page'
            ]),
            'statistics' => $this->getBasicBookingStatistics(),
        ]);
    }

    /**
     * Get user's bookings
     */
    public function getUserBookings(Request $request, BookingFilter $filter): array
    {
        $user = $request->user();

        if (!$user->hasPermission('view_own_bookings')) {
            return $this->error('You do not have permission to view bookings.', 403);
        }

        $request->validated();

        $query = Booking::with(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn'])
            ->where('user_id', $user->id)
            ->filter($filter);

        $perPage = $request->input('per_page', 15);
        $perPage = min($perPage, 50); // Maximum 50 items per page for users

        $bookings = $query->paginate($perPage);

        return $this->ok('User bookings retrieved successfully', [
            'bookings' => BookingResource::collection($bookings->items()),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
                'last_page' => $bookings->lastPage(),
                'has_more_pages' => $bookings->hasMorePages(),
            ],
            'filters_applied' => $request->only([
                'service_id', 'status', 'date_from', 'date_to', 'search', 'sort', 'per_page'
            ]),
        ]);
    }

    /**
     * Get booking details
     */
    public function getBookingDetails(Request $request, Booking $booking): array
    {
        $user = $request->user();

        // Check permissions
        if (!$user->hasPermission('view_all_bookings') && $booking->user_id !== $user->id) {
            return $this->error('You do not have permission to view this booking.', 403);
        }

        $booking->load([
            'service',
            'serviceLocation',
            'bookingAddOns.serviceAddOn',
            'user'
        ]);

        // Load consultation if exists
        if ($booking->consultation_booking_id) {
            $booking->load('consultationBooking');
        }

        return $this->ok('Booking details retrieved', [
            'booking' => new BookingResource($booking)
        ]);
    }

    /**
     * Get basic booking statistics
     */
    public function getBookingStatistics(Request $request): array
    {
        $user = $request->user();

        if (!$user->hasPermission('view_booking_statistics')) {
            return $this->error('You do not have permission to view booking statistics.', 403);
        }

        // Get validated filters from the request
        $filters = $request->only(['period', 'start_date', 'end_date']);
        $period = $filters['period'] ?? 'month';

        // Calculate date range based on period
        switch ($period) {
            case 'today':
                $startDate = now()->startOfDay();
                $endDate = now()->endOfDay();
                break;
            case 'week':
                $startDate = now()->startOfWeek();
                $endDate = now()->endOfWeek();
                break;
            case 'month':
                $startDate = now()->startOfMonth();
                $endDate = now()->endOfMonth();
                break;
            case 'quarter':
                $startDate = now()->startOfQuarter();
                $endDate = now()->endOfQuarter();
                break;
            case 'year':
                $startDate = now()->startOfYear();
                $endDate = now()->endOfYear();
                break;
            default:
                $startDate = $filters['start_date'] ? Carbon::parse($filters['start_date']) : now()->startOfMonth();
                $endDate = $filters['end_date'] ? Carbon::parse($filters['end_date']) : now()->endOfMonth();
        }

        $baseQuery = Booking::whereBetween('scheduled_at', [$startDate, $endDate]);

        $totalRevenue = (clone $baseQuery)->where('status', 'completed')->sum('total_amount') ?? 0;
        $averageBookingValue = (clone $baseQuery)->where('status', 'completed')->avg('total_amount') ?? 0;

        $statistics = [
            'period' => $period,
            'date_range' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_bookings' => (clone $baseQuery)->count(),
            'confirmed_bookings' => (clone $baseQuery)->where('status', 'confirmed')->count(),
            'pending_bookings' => (clone $baseQuery)->where('status', 'pending')->count(),
            'completed_bookings' => (clone $baseQuery)->where('status', 'completed')->count(),
            'cancelled_bookings' => (clone $baseQuery)->where('status', 'cancelled')->count(),
            'total_revenue' => $totalRevenue,
            'average_booking_value' => $averageBookingValue,
            'formatted_revenue' => $this->formatPrice($totalRevenue),
            'formatted_average' => $this->formatPrice($averageBookingValue),
        ];

        return $this->ok('Booking statistics retrieved', $statistics);
    }

    /**
     * Resend booking notification
     */
    public function resendBookingNotification(Request $request, Booking $booking, string $notificationType): bool
    {
        $user = $request->user();

        // Check permissions
        if (!$user->hasPermission('edit_all_bookings') && $booking->user_id !== $user->id) {
            return false;
        }

        try {
            $result = $this->emailService->sendImmediateNotification(
                $booking,
                $notificationType,
                [
                    'email_type' => $notificationType,
                    'resent_by' => $user->id,
                    'resent_at' => now()->toDateTimeString(),
                ]
            );

            Log::info('Booking notification resent', [
                'booking_id' => $booking->id,
                'notification_type' => $notificationType,
                'resent_by' => $user->id,
                'success' => $result,
            ]);

            return $result;
        } catch (Exception $e) {
            Log::error('Failed to resend booking notification', [
                'booking_id' => $booking->id,
                'notification_type' => $notificationType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get basic booking statistics helper
     */
    private function getBasicBookingStatistics(): array
    {
        return [
            'total_bookings' => Booking::count(),
            'confirmed_bookings' => Booking::where('status', 'confirmed')->count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'completed_bookings' => Booking::where('status', 'completed')->count(),
            'cancelled_bookings' => Booking::where('status', 'cancelled')->count(),
        ];
    }

    /**
     * Helper method: Format price for display
     */
    private function formatPrice(int $priceInPence): string
    {
        return '£' . number_format($priceInPence / 100, 2);
    }
}

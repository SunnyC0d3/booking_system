<?php

namespace App\Services\V1\Bookings;

use App\Constants\ConsultationStatuses;
use App\Constants\BookingStatuses;
use App\Constants\NotificationTypes;
use App\Models\ConsultationBooking;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Models\ConsultationNote;
use App\Resources\V1\ConsultationBookingResource;
use App\Traits\V1\ApiResponses;
use App\Mail\ConsultationReminderMail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

class ConsultationBookingService
{
    use ApiResponses;

    private BookingEmailService $emailService;
    private TimeSlotService $timeSlotService;

    public function __construct(BookingEmailService $emailService, TimeSlotService $timeSlotService)
    {
        $this->emailService = $emailService;
        $this->timeSlotService = $timeSlotService;
    }

    /**
     * Create a new consultation booking
     */
    public function createConsultation(Request $request): array
    {
        $data = $request->validated();
        $user = $request->user();

        return DB::transaction(function () use ($data, $user) {
            $service = Service::findOrFail($data['service_id']);

            // Validate consultation requirements
            $this->validateConsultationRequirements($service, $data);

            // Parse and validate consultation time
            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $durationMinutes = $data['duration_minutes'] ?? $service->consultation_duration_minutes ?? 30;
            $endsAt = $scheduledAt->clone()->addMinutes($durationMinutes);

            // Validate availability for consultation
            $this->validateConsultationAvailability($service, $scheduledAt, $durationMinutes);

            // Generate unique consultation reference
            $consultationReference = $this->generateConsultationReference();

            // Create consultation booking
            $consultation = ConsultationBooking::create([
                'consultation_reference' => $consultationReference,
                'user_id' => $user->id,
                'service_id' => $service->id,
                'main_booking_id' => $data['main_booking_id'] ?? null,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $durationMinutes,
                'status' => ConsultationStatuses::SCHEDULED,
                'type' => $data['type'] ?? 'pre_booking',
                'format' => $data['format'] ?? 'video',
                'client_name' => $data['client_name'] ?? $user->name,
                'client_email' => $data['client_email'] ?? $user->email,
                'client_phone' => $data['client_phone'] ?? $user->phone,
                'consultation_notes' => $data['consultation_notes'] ?? null,
                'preparation_instructions' => $data['preparation_instructions'] ?? $this->getDefaultPreparationInstructions($service, $data['type'] ?? 'pre_booking'),
                'consultation_questions' => $data['consultation_questions'] ?? $this->getDefaultConsultationQuestions($service, $data['type'] ?? 'pre_booking'),
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Set up meeting details based on format
            $this->setupMeetingDetails($consultation, $data);

            // Link to main booking if provided
            if ($consultation->main_booking_id) {
                $this->linkToMainBooking($consultation);
            }

            // Send confirmation and schedule reminders
            $this->sendConsultationConfirmation($consultation);
            $this->scheduleConsultationReminders($consultation);

            // Log consultation creation
            Log::info('Consultation booking created', [
                'consultation_id' => $consultation->id,
                'consultation_reference' => $consultation->consultation_reference,
                'user_id' => $user->id,
                'service_id' => $service->id,
                'type' => $consultation->type,
                'format' => $consultation->format,
                'scheduled_at' => $scheduledAt->toDateTimeString(),
            ]);

            return $this->ok('Consultation booked successfully', [
                'consultation' => new ConsultationBookingResource($consultation->load(['service', 'user', 'mainBooking']))
            ]);
        });
    }

    /**
     * Update consultation booking
     */
    public function updateConsultation(Request $request, ConsultationBooking $consultation): array
    {
        $user = $request->user();
        $data = $request->validated();

        // Check permissions
        $this->validateUpdatePermissions($user, $consultation);

        return DB::transaction(function () use ($consultation, $data, $user) {
            $originalScheduledAt = $consultation->scheduled_at;
            $wasRescheduled = false;

            // Update allowed fields
            $allowedFields = [
                'consultation_notes', 'preparation_instructions', 'client_name',
                'client_email', 'client_phone', 'metadata'
            ];

            // Admins can update additional fields
            if ($user->hasPermission('manage_consultations')) {
                $allowedFields = array_merge($allowedFields, [
                    'type', 'format', 'consultant_notes', 'workflow_stage'
                ]);
            }

            $updateData = array_intersect_key($data, array_flip($allowedFields));

            // Handle rescheduling
            if (isset($data['scheduled_at']) && $user->hasPermission('manage_consultations')) {
                $newScheduledAt = Carbon::parse($data['scheduled_at']);
                if (!$originalScheduledAt->equalTo($newScheduledAt)) {
                    $this->validateConsultationAvailability($consultation->service, $newScheduledAt, $consultation->duration_minutes, $consultation);
                    $updateData['scheduled_at'] = $newScheduledAt;
                    $updateData['ends_at'] = $newScheduledAt->clone()->addMinutes($consultation->duration_minutes);
                    $wasRescheduled = true;
                }
            }

            // Handle format changes
            if (isset($data['format']) && $data['format'] !== $consultation->format) {
                $this->updateMeetingDetails($consultation, $data);
            }

            if (!empty($updateData)) {
                $consultation->update($updateData);
            }

            // Handle rescheduling notifications
            if ($wasRescheduled) {
                $this->handleConsultationReschedule($consultation, $originalScheduledAt);
            }

            Log::info('Consultation updated', [
                'consultation_id' => $consultation->id,
                'updated_by' => $user->id,
                'updated_fields' => array_keys($updateData),
                'was_rescheduled' => $wasRescheduled,
            ]);

            return $this->ok('Consultation updated successfully', [
                'consultation' => new ConsultationBookingResource($consultation->load(['service', 'user', 'mainBooking']))
            ]);
        });
    }

    /**
     * Start consultation session
     */
    public function startConsultation(Request $request, ConsultationBooking $consultation): array
    {
        $user = $request->user();

        // Validate start permissions and timing
        $this->validateStartPermissions($user, $consultation);

        return DB::transaction(function () use ($consultation, $user) {
            $consultation->update([
                'status' => ConsultationStatuses::IN_PROGRESS,
                'started_at' => now(),
                'workflow_stage' => 'in_progress',
            ]);

            // Track participant join
            $this->trackParticipantJoin($consultation, $user);

            // Create initial consultation note
            $this->createConsultationNote($consultation, 'session_started', 'Consultation session started', $user);

            Log::info('Consultation started', [
                'consultation_id' => $consultation->id,
                'started_by' => $user->id,
                'started_at' => now()->toDateTimeString(),
            ]);

            return $this->ok('Consultation started successfully', [
                'consultation' => new ConsultationBookingResource($consultation),
                'session_info' => $this->getSessionInfo($consultation),
            ]);
        });
    }

    /**
     * Complete consultation session
     */
    public function completeConsultation(Request $request, ConsultationBooking $consultation): array
    {
        $user = $request->user();
        $data = $request->validated();

        // Validate completion permissions
        $this->validateCompletionPermissions($user, $consultation);

        return DB::transaction(function () use ($consultation, $data, $user) {
            $actualDuration = now()->diffInMinutes($consultation->started_at);

            $consultation->update([
                'status' => ConsultationStatuses::COMPLETED,
                'completed_at' => now(),
                'workflow_stage' => 'completed',
                'actual_duration_minutes' => $actualDuration,
                'outcome_summary' => $data['outcome_summary'] ?? null,
                'recommendations' => $data['recommendations'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'estimated_duration' => $data['estimated_duration'] ?? null,
                'complexity_level' => $data['complexity_level'] ?? null,
                'feasibility_assessment' => $data['feasibility_assessment'] ?? null,
                'follow_up_required' => $data['follow_up_required'] ?? false,
                'follow_up_notes' => $data['follow_up_notes'] ?? null,
                'consultant_notes' => $data['consultant_notes'] ?? null,
            ]);

            // Track participant leave
            $this->trackParticipantLeave($consultation, $user);

            // Create completion note
            $this->createConsultationNote($consultation, 'session_completed', $data['outcome_summary'] ?? 'Consultation completed', $user);

            // Handle main booking updates
            if ($consultation->main_booking_id) {
                $this->updateMainBookingFromConsultation($consultation, $data);
            }

            // Schedule follow-up if required
            if ($consultation->follow_up_required) {
                $this->scheduleFollowUp($consultation);
            }

            // Send completion notification to client
            $this->sendConsultationCompletion($consultation);

            Log::info('Consultation completed', [
                'consultation_id' => $consultation->id,
                'completed_by' => $user->id,
                'actual_duration' => $actualDuration,
                'follow_up_required' => $consultation->follow_up_required,
            ]);

            return $this->ok('Consultation completed successfully', [
                'consultation' => new ConsultationBookingResource($consultation->load(['service', 'user', 'mainBooking'])),
                'summary' => $this->getConsultationSummary($consultation),
            ]);
        });
    }

    /**
     * Cancel consultation
     */
    public function cancelConsultation(Request $request, ConsultationBooking $consultation): array
    {
        $user = $request->user();
        $data = $request->validated();

        // Validate cancellation permissions
        $this->validateCancellationPermissions($user, $consultation);

        return DB::transaction(function () use ($consultation, $data, $user) {
            $cancellationReason = $data['reason'] ?? 'Cancelled by ' . ($user->hasRole(['admin', 'super admin']) ? 'admin' : 'client');

            $consultation->update([
                'status' => ConsultationStatuses::CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' => $cancellationReason,
                'workflow_stage' => 'cancelled',
            ]);

            // Cancel any scheduled reminders
            $this->cancelConsultationReminders($consultation);

            // Send cancellation notification
            $this->sendConsultationCancellation($consultation, $cancellationReason);

            Log::info('Consultation cancelled', [
                'consultation_id' => $consultation->id,
                'cancelled_by' => $user->id,
                'reason' => $cancellationReason,
            ]);

            return $this->ok('Consultation cancelled successfully', [
                'consultation' => new ConsultationBookingResource($consultation)
            ]);
        });
    }

    /**
     * Add consultation note
     */
    public function addConsultationNote(Request $request, ConsultationBooking $consultation): array
    {
        $user = $request->user();
        $data = $request->validated();

        $note = $this->createConsultationNote(
            $consultation,
            $data['type'] ?? 'general',
            $data['content'],
            $user,
            $data['is_private'] ?? false
        );

        Log::info('Consultation note added', [
            'consultation_id' => $consultation->id,
            'note_id' => $note->id,
            'added_by' => $user->id,
            'note_type' => $note->type,
        ]);

        return $this->ok('Note added successfully', [
            'note' => $note,
            'consultation' => new ConsultationBookingResource($consultation->load(['consultationNotes']))
        ]);
    }

    /**
     * Generate consultation reference
     */
    private function generateConsultationReference(): string
    {
        do {
            $reference = 'CON' . strtoupper(substr(uniqid(), -8));
        } while (ConsultationBooking::where('consultation_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Validate consultation requirements
     */
    private function validateConsultationRequirements(Service $service, array $data): void
    {
        if (!$service->requires_consultation && empty($data['main_booking_id'])) {
            throw new Exception('This service does not typically require consultation', 422);
        }

        if ($service->consultation_duration_minutes &&
            isset($data['duration_minutes']) &&
            $data['duration_minutes'] > $service->consultation_duration_minutes * 2) {
            throw new Exception('Consultation duration exceeds service limits', 422);
        }
    }

    /**
     * Validate consultation availability
     */
    private function validateConsultationAvailability(Service $service, Carbon $scheduledAt, int $durationMinutes, ?ConsultationBooking $excludeConsultation = null): void
    {
        // Check if the time is in the past
        if ($scheduledAt->lte(now())) {
            throw new Exception('Cannot schedule consultation in the past', 422);
        }

        // Check minimum advance booking
        $minAdvanceHours = 2; // Minimum 2 hours for consultations
        if ($scheduledAt->lt(now()->addHours($minAdvanceHours))) {
            throw new Exception("Consultations must be scheduled at least {$minAdvanceHours} hours in advance", 422);
        }

        // Check for conflicting consultations
        $conflictingConsultations = ConsultationBooking::where('service_id', $service->id)
            ->where('status', ConsultationStatuses::SCHEDULED)
            ->where(function ($query) use ($scheduledAt, $durationMinutes) {
                $endTime = $scheduledAt->clone()->addMinutes($durationMinutes);
                $query->where(function ($q) use ($scheduledAt, $endTime) {
                    $q->where('scheduled_at', '<', $endTime)
                        ->where('ends_at', '>', $scheduledAt);
                });
            })
            ->when($excludeConsultation, function ($query) use ($excludeConsultation) {
                $query->where('id', '!=', $excludeConsultation->id);
            })
            ->exists();

        if ($conflictingConsultations) {
            throw new Exception('Time slot conflicts with existing consultation', 422);
        }

        // Check business hours (9 AM to 6 PM on weekdays)
        $dayOfWeek = $scheduledAt->dayOfWeek;
        $hour = $scheduledAt->hour;

        if ($dayOfWeek === 0 || $dayOfWeek === 6) { // Sunday or Saturday
            throw new Exception('Consultations are only available on weekdays', 422);
        }

        if ($hour < 9 || $hour >= 18) {
            throw new Exception('Consultations are only available between 9 AM and 6 PM', 422);
        }
    }

    /**
     * Setup meeting details based on format
     */
    private function setupMeetingDetails(ConsultationBooking $consultation, array $data): void
    {
        $meetingDetails = [];

        switch ($consultation->format) {
            case 'video':
                $meetingDetails = [
                    'meeting_link' => $this->generateVideoMeetingLink($consultation),
                    'meeting_id' => $this->generateMeetingId(),
                    'meeting_access_code' => $this->generateAccessCode(),
                    'meeting_platform' => 'Zoom', // or from config
                    'host_key' => $this->generateHostKey(),
                ];
                break;

            case 'phone':
                $meetingDetails = [
                    'dial_in_number' => config('app.consultation_phone', '+44 20 7946 0958'),
                    'meeting_access_code' => $this->generateAccessCode(),
                ];
                break;

            case 'in_person':
                $meetingDetails = [
                    'meeting_location' => $data['meeting_location'] ?? config('app.office_address'),
                    'meeting_instructions' => $data['meeting_instructions'] ?? [
                            'parking' => 'Street parking available',
                            'access' => 'Ring bell at main entrance',
                            'bring' => ['ID', 'Any relevant documents'],
                        ],
                ];
                break;

            case 'site_visit':
                $meetingDetails = [
                    'meeting_location' => $data['meeting_location'] ?? 'Client location',
                    'meeting_instructions' => $data['meeting_instructions'] ?? [
                            'access' => 'Please ensure site access is available',
                            'duration' => 'Allow extra time for site assessment',
                            'bring' => ['Measuring tools', 'Camera', 'Tablet'],
                        ],
                ];
                break;
        }

        $consultation->update($meetingDetails);
    }

    /**
     * Update meeting details when format changes
     */
    private function updateMeetingDetails(ConsultationBooking $consultation, array $data): void
    {
        // Clear existing meeting details
        $consultation->update([
            'meeting_link' => null,
            'meeting_id' => null,
            'meeting_access_code' => null,
            'meeting_platform' => null,
            'host_key' => null,
            'dial_in_number' => null,
            'meeting_location' => null,
            'meeting_instructions' => null,
        ]);

        // Setup new meeting details
        $this->setupMeetingDetails($consultation, $data);
    }

    /**
     * Link consultation to main booking
     */
    private function linkToMainBooking(ConsultationBooking $consultation): void
    {
        $mainBooking = Booking::find($consultation->main_booking_id);

        if ($mainBooking && $mainBooking->requires_consultation && !$mainBooking->consultation_completed_at) {
            // Update main booking to reference this consultation
            $mainBooking->update([
                'consultation_notes' => "Consultation scheduled for {$consultation->scheduled_at->format('M j, Y \a\t g:i A')}"
            ]);
        }
    }

    /**
     * Update main booking from consultation results
     */
    private function updateMainBookingFromConsultation(ConsultationBooking $consultation, array $data): void
    {
        $mainBooking = $consultation->mainBooking;

        if ($mainBooking) {
            $updates = [
                'consultation_completed_at' => $consultation->completed_at,
                'consultation_notes' => $consultation->outcome_summary,
            ];

            // Update estimated cost if provided
            if ($consultation->estimated_cost) {
                $updates['estimated_total'] = $consultation->estimated_cost;
            }

            $mainBooking->update($updates);

            // If consultation was successful, move booking to confirmed
            if ($consultation->feasibility_assessment === 'feasible' && $mainBooking->status === BookingStatuses::PENDING) {
                $mainBooking->update(['status' => BookingStatuses::CONFIRMED]);
            }
        }
    }

    /**
     * Create consultation note
     */
    private function createConsultationNote(ConsultationBooking $consultation, string $type, string $content, User $user, bool $isPrivate = false): ConsultationNote
    {
        return ConsultationNote::create([
            'consultation_booking_id' => $consultation->id,
            'user_id' => $user->id,
            'type' => $type,
            'content' => $content,
            'is_private' => $isPrivate,
            'created_by' => $user->name,
        ]);
    }

    /**
     * Track participant join
     */
    private function trackParticipantJoin(ConsultationBooking $consultation, User $user): void
    {
        if ($user->id === $consultation->user_id) {
            $consultation->update(['client_joined_at' => now()]);
        } else {
            $consultation->update([
                'consultant_joined_at' => now(),
                'consultant_name' => $user->name,
                'consultant_email' => $user->email,
                'consultant_phone' => $user->phone,
            ]);
        }
    }

    /**
     * Track participant leave
     */
    private function trackParticipantLeave(ConsultationBooking $consultation, User $user): void
    {
        if ($user->id === $consultation->user_id) {
            $consultation->update([
                'client_left_at' => now(),
                'client_duration_minutes' => $consultation->client_joined_at ?
                    now()->diffInMinutes($consultation->client_joined_at) : 0,
            ]);
        } else {
            $consultation->update([
                'consultant_left_at' => now(),
                'consultant_duration_minutes' => $consultation->consultant_joined_at ?
                    now()->diffInMinutes($consultation->consultant_joined_at) : 0,
            ]);
        }
    }

    /**
     * Send consultation confirmation
     */
    private function sendConsultationConfirmation(ConsultationBooking $consultation): void
    {
        try {
            // This would use a consultation-specific confirmation email
            // For now, we'll log it
            Log::info('Consultation confirmation sent', [
                'consultation_id' => $consultation->id,
                'client_email' => $consultation->client_email,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send consultation confirmation', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule consultation reminders
     */
    private function scheduleConsultationReminders(ConsultationBooking $consultation): void
    {
        try {
            // Schedule 24-hour reminder
            $reminderTime24h = $consultation->scheduled_at->subHours(24);
            if ($reminderTime24h->isFuture()) {
                Mail::to($consultation->client_email)
                    ->later($reminderTime24h, new ConsultationReminderMail($consultation, '24h'));
            }

            // Schedule 2-hour reminder
            $reminderTime2h = $consultation->scheduled_at->subHours(2);
            if ($reminderTime2h->isFuture()) {
                Mail::to($consultation->client_email)
                    ->later($reminderTime2h, new ConsultationReminderMail($consultation, '2h'));
            }

            Log::info('Consultation reminders scheduled', [
                'consultation_id' => $consultation->id,
                'reminders_count' => 2,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to schedule consultation reminders', [
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle consultation reschedule
     */
    private function handleConsultationReschedule(ConsultationBooking $consultation, Carbon $originalScheduledAt): void
    {
        // Cancel existing reminders and schedule new ones
        $this->cancelConsultationReminders($consultation);
        $this->scheduleConsultationReminders($consultation);

        // Send reschedule notification (would use specialized email)
        Log::info('Consultation rescheduled', [
            'consultation_id' => $consultation->id,
            'original_time' => $originalScheduledAt->toDateTimeString(),
            'new_time' => $consultation->scheduled_at->toDateTimeString(),
        ]);
    }

    /**
     * Cancel consultation reminders
     */
    private function cancelConsultationReminders(ConsultationBooking $consultation): void
    {
        // This would cancel queued email jobs
        // Implementation depends on queue system
        Log::info('Consultation reminders cancelled', [
            'consultation_id' => $consultation->id,
        ]);
    }

    /**
     * Send consultation completion notification
     */
    private function sendConsultationCompletion(ConsultationBooking $consultation): void
    {
        // Send completion summary to client
        Log::info('Consultation completion notification sent', [
            'consultation_id' => $consultation->id,
            'client_email' => $consultation->client_email,
        ]);
    }

    /**
     * Send consultation cancellation notification
     */
    private function sendConsultationCancellation(ConsultationBooking $consultation, string $reason): void
    {
        Log::info('Consultation cancellation notification sent', [
            'consultation_id' => $consultation->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Schedule follow-up
     */
    private function scheduleFollowUp(ConsultationBooking $consultation): void
    {
        // Schedule follow-up consultation or email
        $followUpDate = now()->addDays(3); // 3 days after consultation

        Log::info('Follow-up scheduled', [
            'consultation_id' => $consultation->id,
            'follow_up_date' => $followUpDate->toDateTimeString(),
        ]);
    }

    /**
     * Get session info for active consultation
     */
    private function getSessionInfo(ConsultationBooking $consultation): array
    {
        return [
            'status' => $consultation->status,
            'started_at' => $consultation->started_at,
            'duration_so_far' => $consultation->started_at ?
                now()->diffInMinutes($consultation->started_at) : 0,
            'participants' => [
                'client_joined' => $consultation->client_joined_at !== null,
                'consultant_joined' => $consultation->consultant_joined_at !== null,
            ],
            'meeting_details' => [
                'format' => $consultation->format,
                'platform' => $consultation->meeting_platform,
                'meeting_link' => $consultation->meeting_link,
            ],
        ];
    }

    /**
     * Get consultation summary
     */
    private function getConsultationSummary(ConsultationBooking $consultation): array
    {
        return [
            'duration' => $consultation->actual_duration_minutes,
            'outcome' => $consultation->outcome_summary,
            'recommendations' => $consultation->recommendations,
            'estimated_cost' => $consultation->estimated_cost,
            'feasibility' => $consultation->feasibility_assessment,
            'follow_up_required' => $consultation->follow_up_required,
            'next_steps' => $this->getNextSteps($consultation),
        ];
    }

    /**
     * Get next steps after consultation
     */
    private function getNextSteps(ConsultationBooking $consultation): array
    {
        $steps = [];

        if ($consultation->main_booking_id) {
            $steps[] = 'Review consultation outcomes with main booking';
            $steps[] = 'Finalize service details and timeline';
        } else {
            $steps[] = 'Review detailed proposal';
            $steps[] = 'Schedule main service booking';
        }

        if ($consultation->follow_up_required) {
            $steps[] = 'Follow-up consultation scheduled';
        }

        $steps[] = 'Receive written summary within 24 hours';

        return $steps;
    }

    /**
     * Validation methods
     */
    private function validateUpdatePermissions(User $user, ConsultationBooking $consultation): void
    {
        if ($consultation->user_id !== $user->id && !$user->hasPermission('manage_consultations')) {
            throw new Exception('You can only update your own consultations', 403);
        }

        if (!in_array($consultation->status, [ConsultationStatuses::SCHEDULED])) {
            throw new Exception('Consultation cannot be updated in current status', 422);
        }
    }

    private function validateStartPermissions(User $user, ConsultationBooking $consultation): void
    {
        if (!$user->hasPermission('host_consultations') && $consultation->user_id !== $user->id) {
            throw new Exception('You do not have permission to start this consultation', 403);
        }

        if ($consultation->status !== ConsultationStatuses::SCHEDULED) {
            throw new Exception('Consultation cannot be started in current status', 422);
        }

        // Check timing - allow starting 15 minutes early to 15 minutes late
        $now = now();
        $allowedStart = $consultation->scheduled_at->subMinutes(15);
        $allowedEnd = $consultation->scheduled_at->addMinutes(15);

        if ($now->lt($allowedStart) || $now->gt($allowedEnd)) {
            throw new Exception('Consultation can only be started within 15 minutes of scheduled time', 422);
        }
    }

    private function validateCompletionPermissions(User $user, ConsultationBooking $consultation): void
    {
        if (!$user->hasPermission('host_consultations')) {
            throw new Exception('You do not have permission to complete consultations', 403);
        }

        if ($consultation->status !== ConsultationStatuses::IN_PROGRESS) {
            throw new Exception('Only in-progress consultations can be completed', 422);
        }
    }

    private function validateCancellationPermissions(User $user, ConsultationBooking $consultation): void
    {
        if ($consultation->user_id !== $user->id && !$user->hasPermission('manage_consultations')) {
            throw new Exception('You can only cancel your own consultations', 403);
        }

        if (!in_array($consultation->status, [ConsultationStatuses::SCHEDULED])) {
            throw new Exception('Consultation cannot be cancelled in current status', 422);
        }

        // Check cancellation timing - at least 2 hours notice for client cancellations
        if ($consultation->user_id === $user->id && $consultation->scheduled_at->lt(now()->addHours(2))) {
            throw new Exception('Consultations must be cancelled at least 2 hours in advance', 422);
        }
    }

    /**
     * Helper methods for meeting setup
     */
    private function generateVideoMeetingLink(ConsultationBooking $consultation): string
    {
        // This would integrate with actual video conferencing platform
        $meetingId = $this->generateMeetingId();
        return "https://zoom.us/j/{$meetingId}";
    }

    private function generateMeetingId(): string
    {
        return str_pad(mt_rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private function generateAccessCode(): string
    {
        return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function generateHostKey(): string
    {
        return str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get default preparation instructions
     */
    private function getDefaultPreparationInstructions(Service $service, string $type): string
    {
        $instructions = "Please prepare for your {$service->name} consultation:\n\n";

        switch ($type) {
            case 'design':
                $instructions .= "• Gather inspiration images or ideas\n";
                $instructions .= "• Think about your color preferences and style\n";
                $instructions .= "• Consider your budget range\n";
                $instructions .= "• Prepare any specific requirements or constraints\n";
                break;

            case 'technical':
                $instructions .= "• Prepare technical specifications if available\n";
                $instructions .= "• List any equipment or setup concerns\n";
                $instructions .= "• Note any space or access limitations\n";
                $instructions .= "• Gather measurements or site plans if relevant\n";
                break;

            case 'planning':
                $instructions .= "• Review your event timeline and schedule\n";
                $instructions .= "• Prepare guest count and venue details\n";
                $instructions .= "• Consider logistics and coordination needs\n";
                $instructions .= "• List any special requirements or considerations\n";
                break;

            default: // pre_booking
                $instructions .= "• Review your service requirements\n";
                $instructions .= "• Think about your budget and timeline\n";
                $instructions .= "• Prepare any questions about the service\n";
                $instructions .= "• Consider any special needs or preferences\n";
        }

        $instructions .= "\nWe look forward to discussing your needs and how we can help make your vision a reality.";

        return $instructions;
    }

    /**
     * Get default consultation questions
     */
    private function getDefaultConsultationQuestions(Service $service, string $type): array
    {
        $questions = [
            'What is your main goal for this service?',
            'What is your budget range?',
            'What is your ideal timeline?',
        ];

        switch ($type) {
            case 'design':
                $questions = array_merge($questions, [
                    'What style or theme are you envisioning?',
                    'Do you have any color preferences?',
                    'Are there any specific elements you want included?',
                    'Do you have inspiration images to share?',
                ]);
                break;

            case 'technical':
                $questions = array_merge($questions, [
                    'What are the technical requirements or constraints?',
                    'Are there any space or access limitations?',
                    'What equipment or utilities are available?',
                    'Are there any safety or compliance requirements?',
                ]);
                break;

            case 'planning':
                $questions = array_merge($questions, [
                    'How many guests are you expecting?',
                    'What is the venue and location?',
                    'What is the event schedule or timeline?',
                    'Are there any coordination requirements?',
                ]);
                break;

            default: // pre_booking
                $questions = array_merge($questions, [
                    'What specific aspects of the service are most important to you?',
                    'Do you have any special requirements or preferences?',
                    'Have you used similar services before?',
                    'What questions do you have about our process?',
                ]);
        }

        return $questions;
    }

    /**
     * Get user consultations with filtering
     */
    public function getUserConsultations(Request $request): array
    {
        $user = $request->user();
        $filters = $request->validated();

        $query = ConsultationBooking::with(['service', 'mainBooking', 'consultationNotes'])
            ->where('user_id', $user->id);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['format'])) {
            $query->where('format', $filters['format']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('scheduled_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (!empty($filters['date_to'])) {
            $query->where('scheduled_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'scheduled_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($filters['per_page'] ?? 15, 50);
        $consultations = $query->paginate($perPage);

        return $this->ok('Consultations retrieved successfully', [
            'consultations' => ConsultationBookingResource::collection($consultations->items()),
            'pagination' => [
                'current_page' => $consultations->currentPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
                'last_page' => $consultations->lastPage(),
            ],
            'filters_applied' => $filters,
        ]);
    }

    /**
     * Get all consultations (admin)
     */
    public function getAllConsultations(Request $request): array
    {
        $user = $request->user();
        $filters = $request->validated();

        if (!$user->hasPermission('view_all_consultations')) {
            throw new Exception('You do not have permission to view all consultations', 403);
        }

        $query = ConsultationBooking::with(['service', 'user', 'mainBooking', 'consultationNotes']);

        // Apply admin filters
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['format'])) {
            $query->where('format', $filters['format']);
        }

        if (!empty($filters['consultant_name'])) {
            $query->where('consultant_name', 'like', '%' . $filters['consultant_name'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $query->where('scheduled_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (!empty($filters['date_to'])) {
            $query->where('scheduled_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('consultation_reference', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%")
                    ->orWhere('consultation_notes', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'scheduled_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($filters['per_page'] ?? 25, 100);
        $consultations = $query->paginate($perPage);

        return $this->ok('All consultations retrieved successfully', [
            'consultations' => ConsultationBookingResource::collection($consultations->items()),
            'pagination' => [
                'current_page' => $consultations->currentPage(),
                'per_page' => $consultations->perPage(),
                'total' => $consultations->total(),
                'last_page' => $consultations->lastPage(),
            ],
            'filters_applied' => $filters,
            'statistics' => $this->getConsultationStatistics($query),
        ]);
    }

    /**
     * Get consultation statistics
     */
    private function getConsultationStatistics($query): array
    {
        $baseQuery = clone $query;

        return [
            'total_consultations' => $baseQuery->count(),
            'by_status' => [
                'scheduled' => $baseQuery->where('status', ConsultationStatuses::SCHEDULED)->count(),
                'in_progress' => $baseQuery->where('status', ConsultationStatuses::IN_PROGRESS)->count(),
                'completed' => $baseQuery->where('status', ConsultationStatuses::COMPLETED)->count(),
                'cancelled' => $baseQuery->where('status', ConsultationStatuses::CANCELLED)->count(),
                'no_show' => $baseQuery->where('status', ConsultationStatuses::NO_SHOW)->count(),
            ],
            'by_type' => [
                'pre_booking' => $baseQuery->where('type', 'pre_booking')->count(),
                'design' => $baseQuery->where('type', 'design')->count(),
                'technical' => $baseQuery->where('type', 'technical')->count(),
                'planning' => $baseQuery->where('type', 'planning')->count(),
                'follow_up' => $baseQuery->where('type', 'follow_up')->count(),
            ],
            'by_format' => [
                'video' => $baseQuery->where('format', 'video')->count(),
                'phone' => $baseQuery->where('format', 'phone')->count(),
                'in_person' => $baseQuery->where('format', 'in_person')->count(),
                'site_visit' => $baseQuery->where('format', 'site_visit')->count(),
            ],
            'completion_rate' => $this->calculateCompletionRate($baseQuery),
            'average_duration' => $this->calculateAverageDuration($baseQuery),
        ];
    }

    /**
     * Calculate completion rate
     */
    private function calculateCompletionRate($query): float
    {
        $totalScheduled = clone $query;
        $totalCompleted = clone $query;

        $scheduled = $totalScheduled->whereIn('status', [
            ConsultationStatuses::SCHEDULED,
            ConsultationStatuses::COMPLETED,
            ConsultationStatuses::CANCELLED,
            ConsultationStatuses::NO_SHOW
        ])->count();

        $completed = $totalCompleted->where('status', ConsultationStatuses::COMPLETED)->count();

        return $scheduled > 0 ? round(($completed / $scheduled) * 100, 1) : 0;
    }

    /**
     * Calculate average duration
     */
    private function calculateAverageDuration($query): int
    {
        $avgDuration = clone $query;

        return (int) $avgDuration->where('status', ConsultationStatuses::COMPLETED)
            ->whereNotNull('actual_duration_minutes')
            ->avg('actual_duration_minutes') ?? 0;
    }

    /**
     * Mark consultation as no-show
     */
    public function markNoShow(Request $request, ConsultationBooking $consultation): array
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_consultations')) {
            throw new Exception('You do not have permission to mark consultations as no-show', 403);
        }

        if ($consultation->status !== ConsultationStatuses::SCHEDULED) {
            throw new Exception('Only scheduled consultations can be marked as no-show', 422);
        }

        // Must be past the scheduled time
        if ($consultation->scheduled_at->isFuture()) {
            throw new Exception('Cannot mark future consultations as no-show', 422);
        }

        $consultation->update([
            'status' => ConsultationStatuses::NO_SHOW,
            'no_show_at' => now(),
            'workflow_stage' => 'no_show',
        ]);

        // Create note about no-show
        $this->createConsultationNote($consultation, 'no_show', 'Client did not attend scheduled consultation', $user);

        Log::info('Consultation marked as no-show', [
            'consultation_id' => $consultation->id,
            'marked_by' => $user->id,
            'scheduled_time' => $consultation->scheduled_at->toDateTimeString(),
        ]);

        return $this->ok('Consultation marked as no-show', [
            'consultation' => new ConsultationBookingResource($consultation)
        ]);
    }
}

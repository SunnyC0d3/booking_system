<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\ConsultationBooking;
use App\Models\Service;
use App\Requests\V1\StoreConsultationRequest;
use App\Requests\V1\UpdateConsultationRequest;
use App\Resources\V1\ConsultationBookingResource;
use App\Services\V1\Bookings\ConsultationBookingService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class ConsultationController extends Controller
{
    use ApiResponses;

    private ConsultationBookingService $consultationService;

    public function __construct(ConsultationBookingService $consultationService)
    {
    }

    /**
     * Get user's consultation bookings
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,no_show,all',
                'type' => 'nullable|in:pre_booking,design,planning,technical,follow_up',
                'format' => 'nullable|in:phone,video,in_person,site_visit',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'service_id' => 'nullable|exists:services,id',
                'sort' => 'nullable|in:scheduled_asc,scheduled_desc,created_asc,created_desc,status_asc,status_desc',
                'per_page' => 'nullable|integer|min:1|max:50',
            ]);

            $user = $request->user();

            Log::info('User requesting consultation list', [
                'user_id' => $user->id,
                'filters' => $request->only(['status', 'type', 'format', 'date_from', 'date_to', 'service_id']),
            ]);

            return $this->consultationService->getUserConsultations($request);

        } catch (Exception $e) {
            Log::error('Failed to retrieve user consultations', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Book a new consultation
     */
    public function store(StoreConsultationRequest $request)
    {
        try {
            $user = $request->user();

            Log::info('User creating consultation booking', [
                'user_id' => $user->id,
                'service_id' => $request->input('service_id'),
                'scheduled_at' => $request->input('scheduled_at'),
                'type' => $request->input('type'),
                'format' => $request->input('format'),
            ]);

            return $this->consultationService->createConsultation($request);

        } catch (Exception $e) {
            Log::error('Failed to create consultation booking', [
                'user_id' => $request->user()?->id,
                'service_id' => $request->input('service_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get consultation details
     */
    public function show(Request $request, ConsultationBooking $consultation)
    {
        try {
            $user = $request->user();

            // Ensure user can only view their own consultations
            if ($consultation->user_id !== $user->id) {
                Log::warning('User attempted to view consultation they do not own', [
                    'user_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'consultation_user_id' => $consultation->user_id,
                ]);

                return $this->error('You can only view your own consultations.', 403);
            }

            Log::info('User viewing consultation details', [
                'user_id' => $user->id,
                'consultation_id' => $consultation->id,
                'consultation_reference' => $consultation->consultation_reference,
            ]);

            $consultation->load(['service', 'user', 'mainBooking', 'notes']);

            return $this->ok('Consultation details retrieved', [
                'consultation' => new ConsultationBookingResource($consultation)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve consultation details', [
                'user_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update consultation details
     */
    public function update(UpdateConsultationRequest $request, ConsultationBooking $consultation)
    {
        try {
            $user = $request->user();

            // Ensure user can only update their own consultations
            if ($consultation->user_id !== $user->id) {
                Log::warning('User attempted to update consultation they do not own', [
                    'user_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'consultation_user_id' => $consultation->user_id,
                ]);

                return $this->error('You can only update your own consultations.', 403);
            }

            Log::info('User updating consultation', [
                'user_id' => $user->id,
                'consultation_id' => $consultation->id,
                'updates' => $request->only(['scheduled_at', 'type', 'format', 'consultation_notes']),
            ]);

            return $this->consultationService->updateConsultation($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to update consultation', [
                'user_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Cancel a consultation
     */
    public function cancel(Request $request, ConsultationBooking $consultation)
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
                'notify_admin' => 'boolean',
            ]);

            $user = $request->user();

            // Ensure user can only cancel their own consultations
            if ($consultation->user_id !== $user->id) {
                Log::warning('User attempted to cancel consultation they do not own', [
                    'user_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'consultation_user_id' => $consultation->user_id,
                ]);

                return $this->error('You can only cancel your own consultations.', 403);
            }

            Log::info('User cancelling consultation', [
                'user_id' => $user->id,
                'consultation_id' => $consultation->id,
                'reason' => $request->input('reason'),
            ]);

            return $this->consultationService->cancelConsultation($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to cancel consultation', [
                'user_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Reschedule a consultation
     */
    public function reschedule(Request $request, ConsultationBooking $consultation)
    {
        try {
            $request->validate([
                'scheduled_at' => 'required|date|after:now',
                'reason' => 'nullable|string|max:500',
                'send_notifications' => 'boolean',
            ]);

            $user = $request->user();

            // Ensure user can only reschedule their own consultations
            if ($consultation->user_id !== $user->id) {
                Log::warning('User attempted to reschedule consultation they do not own', [
                    'user_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'consultation_user_id' => $consultation->user_id,
                ]);

                return $this->error('You can only reschedule your own consultations.', 403);
            }

            Log::info('User rescheduling consultation', [
                'user_id' => $user->id,
                'consultation_id' => $consultation->id,
                'old_scheduled_at' => $consultation->scheduled_at,
                'new_scheduled_at' => $request->input('scheduled_at'),
                'reason' => $request->input('reason'),
            ]);

            return $this->consultationService->rescheduleConsultation($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to reschedule consultation', [
                'user_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Join a consultation session
     */
    public function join(Request $request, ConsultationBooking $consultation)
    {
        try {
            $user = $request->user();

            // Ensure user can only join their own consultations
            if ($consultation->user_id !== $user->id) {
                Log::warning('User attempted to join consultation they do not own', [
                    'user_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'consultation_user_id' => $consultation->user_id,
                ]);

                return $this->error('You can only join your own consultations.', 403);
            }

            Log::info('User joining consultation session', [
                'user_id' => $user->id,
                'consultation_id' => $consultation->id,
                'consultation_reference' => $consultation->consultation_reference,
            ]);

            return $this->consultationService->joinConsultation($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to join consultation session', [
                'user_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get available consultation slots for a service
     */
    public function getAvailableSlots(Request $request, Service $service)
    {
        try {
            $request->validate([
                'date' => 'required|date|after_or_equal:today',
                'duration_minutes' => 'nullable|integer|min:15|max:180',
                'type' => 'nullable|in:pre_booking,design,planning,technical,follow_up',
                'format' => 'nullable|in:phone,video,in_person,site_visit',
                'timezone' => 'nullable|string|max:50',
            ]);

            Log::info('User requesting available consultation slots', [
                'user_id' => $request->user()->id,
                'service_id' => $service->id,
                'date' => $request->input('date'),
                'type' => $request->input('type'),
                'format' => $request->input('format'),
            ]);

            return $this->consultationService->getAvailableConsultationSlots($request, $service);

        } catch (Exception $e) {
            Log::error('Failed to get available consultation slots', [
                'user_id' => $request->user()?->id,
                'service_id' => $service->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Provide feedback for a completed consultation
     */
    public function provideFeedback(Request $request, ConsultationBooking $consultation)
    {
        try {
            $request->validate([
                'client_satisfaction_rating' => 'required|integer|min:1|max:5',
                'client_feedback' => 'nullable|string|max:1000',
                'would_recommend' => 'boolean',
                'consultant_rating' => 'nullable|integer|min:1|max:5',
                'improvement_suggestions' => 'nullable|string|max:500',
            ]);

            $user = $request->user();

            // Ensure user can only provide feedback for their own consultations
            if ($consultation->user_id !== $user->id) {
                Log::warning('User attempted to provide feedback for consultation they do not own', [
                    'user_id' => $user->id,
                    'consultation_id' => $consultation->id,
                    'consultation_user_id' => $consultation->user_id,
                ]);

                return $this->error('You can only provide feedback for your own consultations.', 403);
            }

            // Ensure consultation is completed
            if ($consultation->status !== 'completed') {
                return $this->error('You can only provide feedback for completed consultations.', 422);
            }

            Log::info('User providing consultation feedback', [
                'user_id' => $user->id,
                'consultation_id' => $consultation->id,
                'rating' => $request->input('client_satisfaction_rating'),
            ]);

            return $this->consultationService->provideFeedback($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to provide consultation feedback', [
                'user_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}

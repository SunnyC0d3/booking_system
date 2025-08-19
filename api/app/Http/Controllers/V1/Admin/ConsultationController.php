<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConsultationBooking;
use App\Models\Service;
use App\Models\User;
use App\Requests\V1\StoreConsultationRequest;
use App\Requests\V1\UpdateConsultationRequest;
use App\Resources\V1\ConsultationBookingResource;
use App\Services\V1\Bookings\ConsultationBookingService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class ConsultationController extends Controller
{
    use ApiResponses;

    private ConsultationBookingService $consultationService;

    public function __construct(ConsultationBookingService $consultationService)
    {
        $this->consultationService = $consultationService;
    }

    /**
     * Get all consultation bookings (admin view)
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
                'user_id' => 'nullable|exists:users,id',
                'consultant_id' => 'nullable|exists:users,id',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'workflow_stage' => 'nullable|in:scheduled,preparing,in_progress,wrapping_up,completed,follow_up_pending,closed',
                'requires_follow_up' => 'nullable|boolean',
                'search' => 'nullable|string|max:100',
                'sort' => 'nullable|in:scheduled_asc,scheduled_desc,created_asc,created_desc,status_asc,status_desc,priority_asc,priority_desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to manage consultations.', 403);
            }

            Log::info('Admin requesting consultation list', [
                'admin_id' => $user->id,
                'filters' => $request->only(['status', 'type', 'format', 'date_from', 'date_to', 'service_id', 'user_id']),
            ]);

            return $this->consultationService->getAllConsultations($request);

        } catch (Exception $e) {
            Log::error('Failed to retrieve admin consultation list', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create consultation booking as admin
     */
    public function store(StoreConsultationRequest $request)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to create consultations.', 403);
            }

            Log::info('Admin creating consultation booking', [
                'admin_id' => $user->id,
                'client_user_id' => $request->input('user_id'),
                'service_id' => $request->input('service_id'),
                'scheduled_at' => $request->input('scheduled_at'),
                'type' => $request->input('type'),
            ]);

            return $this->consultationService->createConsultationAsAdmin($request);

        } catch (Exception $e) {
            Log::error('Failed to create consultation as admin', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get consultation details (admin view)
     */
    public function show(Request $request, ConsultationBooking $consultation)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to view consultation details.', 403);
            }

            Log::info('Admin viewing consultation details', [
                'admin_id' => $user->id,
                'consultation_id' => $consultation->id,
                'consultation_reference' => $consultation->consultation_reference,
            ]);

            $consultation->load([
                'service',
                'user',
                'mainBooking',
                'notes.user',
                'outcome'
            ]);

            return $this->ok('Consultation details retrieved', [
                'consultation' => new ConsultationBookingResource($consultation),
                'admin_actions' => $this->getAvailableAdminActions($consultation),
                'workflow_data' => $this->getWorkflowData($consultation),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve consultation details as admin', [
                'admin_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update consultation as admin
     */
    public function update(UpdateConsultationRequest $request, ConsultationBooking $consultation)
    {
        try {
            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to update consultations.', 403);
            }

            Log::info('Admin updating consultation', [
                'admin_id' => $user->id,
                'consultation_id' => $consultation->id,
                'updates' => array_keys($request->validated()),
            ]);

            return $this->consultationService->updateConsultationAsAdmin($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to update consultation as admin', [
                'admin_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Delete/cancel consultation as admin
     */
    public function destroy(Request $request, ConsultationBooking $consultation)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
                'notify_client' => 'boolean',
                'force_delete' => 'boolean',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to delete consultations.', 403);
            }

            Log::info('Admin deleting consultation', [
                'admin_id' => $user->id,
                'consultation_id' => $consultation->id,
                'reason' => $request->input('reason'),
                'force_delete' => $request->input('force_delete', false),
            ]);

            return $this->consultationService->deleteConsultationAsAdmin($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to delete consultation as admin', [
                'admin_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Assign consultant to consultation
     */
    public function assignConsultant(Request $request, ConsultationBooking $consultation)
    {
        try {
            $request->validate([
                'consultant_id' => 'required|exists:users,id',
                'notify_consultant' => 'boolean',
                'notify_client' => 'boolean',
                'assignment_notes' => 'nullable|string|max:500',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to assign consultants.', 403);
            }

            $consultantId = $request->input('consultant_id');
            $consultant = User::findOrFail($consultantId);

            // Verify consultant has appropriate role/permissions
            if (!$consultant->hasAnyRole(['admin', 'super admin', 'customer service'])) {
                return $this->error('Selected user is not authorized to conduct consultations.', 422);
            }

            Log::info('Admin assigning consultant to consultation', [
                'admin_id' => $user->id,
                'consultation_id' => $consultation->id,
                'consultant_id' => $consultantId,
                'consultant_name' => $consultant->name,
            ]);

            return $this->consultationService->assignConsultant($request, $consultation, $consultant);

        } catch (Exception $e) {
            Log::error('Failed to assign consultant', [
                'admin_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Complete consultation session as admin
     */
    public function complete(Request $request, ConsultationBooking $consultation)
    {
        try {
            $request->validate([
                'outcome_summary' => 'required|string|max:2000',
                'recommendations' => 'nullable|string|max:2000',
                'estimated_cost' => 'nullable|integer|min:0',
                'estimated_duration' => 'nullable|integer|min:15|max:480',
                'complexity_level' => 'nullable|in:simple,moderate,complex,very_complex',
                'feasibility_assessment' => 'required|in:feasible,challenging,not_feasible,needs_revision',
                'follow_up_required' => 'boolean',
                'follow_up_notes' => 'nullable|string|max:1000',
                'consultant_notes' => 'nullable|string|max:2000',
                'client_satisfaction_rating' => 'nullable|integer|min:1|max:5',
                'notify_client' => 'boolean',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to complete consultations.', 403);
            }

            Log::info('Admin completing consultation', [
                'admin_id' => $user->id,
                'consultation_id' => $consultation->id,
                'feasibility' => $request->input('feasibility_assessment'),
                'follow_up_required' => $request->input('follow_up_required', false),
            ]);

            return $this->consultationService->completeConsultationAsAdmin($request, $consultation);

        } catch (Exception $e) {
            Log::error('Failed to complete consultation as admin', [
                'admin_id' => $request->user()?->id,
                'consultation_id' => $consultation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Bulk update consultations
     */
    public function bulkUpdate(Request $request)
    {
        try {
            $request->validate([
                'consultation_ids' => 'required|array|min:1|max:50',
                'consultation_ids.*' => 'exists:consultation_bookings,id',
                'action' => 'required|in:assign_consultant,update_status,update_priority,reschedule,cancel',
                'consultant_id' => 'nullable|exists:users,id',
                'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,no_show',
                'priority' => 'nullable|in:low,medium,high,urgent',
                'scheduled_at' => 'nullable|date|after:now',
                'reason' => 'nullable|string|max:500',
                'notify_clients' => 'boolean',
                'notify_consultants' => 'boolean',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to perform bulk operations.', 403);
            }

            $consultationIds = $request->input('consultation_ids');
            $action = $request->input('action');

            Log::info('Admin performing bulk consultation update', [
                'admin_id' => $user->id,
                'action' => $action,
                'consultation_count' => count($consultationIds),
                'consultation_ids' => $consultationIds,
            ]);

            return $this->consultationService->bulkUpdateConsultations($request);

        } catch (Exception $e) {
            Log::error('Failed to perform bulk consultation update', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get consultation dashboard data
     */
    public function dashboard(Request $request)
    {
        try {
            $request->validate([
                'date_range' => 'nullable|in:today,week,month,quarter,year',
                'service_id' => 'nullable|exists:services,id',
                'consultant_id' => 'nullable|exists:users,id',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_consultation_notes')) {
                return $this->error('You do not have permission to view consultation dashboard.', 403);
            }

            Log::info('Admin requesting consultation dashboard', [
                'admin_id' => $user->id,
                'date_range' => $request->input('date_range', 'month'),
                'filters' => $request->only(['service_id', 'consultant_id']),
            ]);

            return $this->consultationService->getDashboardData($request);

        } catch (Exception $e) {
            Log::error('Failed to retrieve consultation dashboard', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get consultation statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $request->validate([
                'period' => 'nullable|in:daily,weekly,monthly,yearly',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'group_by' => 'nullable|in:service,consultant,type,format,status',
                'include_revenue' => 'boolean',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('view_consultation_notes')) {
                return $this->error('You do not have permission to view consultation statistics.', 403);
            }

            Log::info('Admin requesting consultation statistics', [
                'admin_id' => $user->id,
                'period' => $request->input('period', 'monthly'),
                'group_by' => $request->input('group_by'),
            ]);

            return $this->consultationService->getStatistics($request);

        } catch (Exception $e) {
            Log::error('Failed to retrieve consultation statistics', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Export consultations data
     */
    public function export(Request $request)
    {
        try {
            $request->validate([
                'format' => 'nullable|in:csv,xlsx,pdf',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,no_show,all',
                'include_notes' => 'boolean',
                'include_outcomes' => 'boolean',
            ]);

            $user = $request->user();

            // Check permissions
            if (!$user->hasPermission('manage_consultations')) {
                return $this->error('You do not have permission to export consultation data.', 403);
            }

            Log::info('Admin exporting consultation data', [
                'admin_id' => $user->id,
                'format' => $request->input('format', 'csv'),
                'filters' => $request->only(['date_from', 'date_to', 'status']),
            ]);

            return $this->consultationService->exportConsultations($request);

        } catch (Exception $e) {
            Log::error('Failed to export consultation data', [
                'admin_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get available admin actions for a consultation
     */
    private function getAvailableAdminActions(ConsultationBooking $consultation): array
    {
        $actions = [];

        switch ($consultation->status) {
            case 'scheduled':
                $actions = ['start', 'reschedule', 'cancel', 'assign_consultant'];
                break;
            case 'in_progress':
                $actions = ['complete', 'cancel'];
                break;
            case 'completed':
                $actions = ['view_outcome', 'schedule_follow_up'];
                break;
            case 'cancelled':
                $actions = ['reschedule'];
                break;
            case 'no_show':
                $actions = ['reschedule', 'mark_completed'];
                break;
        }

        return $actions;
    }

    /**
     * Get workflow data for a consultation
     */
    private function getWorkflowData(ConsultationBooking $consultation): array
    {
        return [
            'current_stage' => $consultation->workflow_stage ?? 'scheduled',
            'next_actions' => $this->getAvailableAdminActions($consultation),
            'timeline' => [
                'scheduled_at' => $consultation->scheduled_at,
                'started_at' => $consultation->started_at,
                'completed_at' => $consultation->completed_at,
                'cancelled_at' => $consultation->cancelled_at,
            ],
            'duration_tracking' => [
                'scheduled_duration' => $consultation->duration_minutes,
                'actual_duration' => $consultation->actual_duration_minutes,
                'client_duration' => $consultation->client_duration_minutes,
                'consultant_duration' => $consultation->consultant_duration_minutes,
            ],
            'follow_up' => [
                'required' => $consultation->follow_up_required,
                'scheduled_at' => $consultation->follow_up_scheduled_at,
                'notes' => $consultation->follow_up_notes,
            ],
        ];
    }
}

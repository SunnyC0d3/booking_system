<?php

namespace App\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsultationBookingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consultation_reference' => $this->consultation_reference,
            'main_booking_id' => $this->main_booking_id,

            // User and service relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'phone' => $this->user->phone,
                ];
            }),

            'service' => $this->whenLoaded('service', function () {
                return [
                    'id' => $this->service->id,
                    'name' => $this->service->name,
                    'category' => $this->service->category,
                    'requires_consultation' => $this->service->requires_consultation,
                    'consultation_duration_minutes' => $this->service->consultation_duration_minutes,
                ];
            }),

            'main_booking' => $this->whenLoaded('mainBooking', function () {
                return $this->mainBooking ? [
                    'id' => $this->mainBooking->id,
                    'booking_reference' => $this->mainBooking->booking_reference,
                    'status' => $this->mainBooking->status,
                    'scheduled_at' => $this->mainBooking->scheduled_at?->toISOString(),
                    'service_name' => $this->mainBooking->service->name ?? null,
                ] : null;
            }),

            // Consultation scheduling
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'formatted_scheduled_at' => $this->scheduled_at?->format('l, F j, Y \a\t g:i A'),
            'formatted_time_range' => $this->scheduled_at && $this->ends_at ?
                $this->scheduled_at->format('g:i A') . ' - ' . $this->ends_at->format('g:i A') : null,
            'formatted_duration' => $this->getFormattedDuration(),

            // Status and workflow
            'status' => $this->status,
            'status_display' => $this->getStatusDisplayAttribute(),
            'status_color' => $this->getStatusColorAttribute(),
            'type' => $this->type,
            'type_display' => $this->getTypeDisplayAttribute(),
            'format' => $this->format,
            'format_display' => $this->getFormatDisplayAttribute(),

            // Client information
            'client_name' => $this->client_name,
            'client_email' => $this->client_email,
            'client_phone' => $this->client_phone,

            // Consultation content and workflow
            'consultation_notes' => $this->consultation_notes,
            'preparation_instructions' => $this->preparation_instructions,
            'consultation_questions' => $this->consultation_questions,
            'consultant_notes' => $this->consultant_notes,
            'outcome_summary' => $this->outcome_summary,
            'follow_up_required' => $this->follow_up_required,
            'follow_up_notes' => $this->follow_up_notes,

            // Meeting details
            'meeting_details' => [
                'link' => $this->meeting_link,
                'location' => $this->meeting_location,
                'instructions' => $this->meeting_instructions,
                'access_code' => $this->when(
                    $request->user() && ($request->user()->id === $this->user_id || $request->user()->hasPermission('view_all_consultations')),
                    $this->meeting_access_code
                ),
                'dial_in_number' => $this->dial_in_number,
                'meeting_id' => $this->meeting_id,
                'host_key' => $this->when(
                    $request->user() && $request->user()->hasPermission('host_consultations'),
                    $this->host_key
                ),
            ],

            // Workflow tracking
            'workflow_stage' => $this->workflow_stage,
            'workflow_stage_display' => $this->getWorkflowStageDisplayAttribute(),
            'can_reschedule' => $this->canBeRescheduled(),
            'can_cancel' => $this->canBeCancelled(),
            'can_start' => $this->canBeStarted(),
            'can_complete' => $this->canBeCompleted(),

            // Timing information
            'is_upcoming' => $this->isUpcoming(),
            'is_today' => $this->isToday(),
            'is_past' => $this->isPast(),
            'is_in_progress' => $this->isInProgress(),
            'time_until_consultation' => $this->getTimeUntilConsultation(),
            'duration_since_completion' => $this->getDurationSinceCompletion(),

            // Completion tracking
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'no_show_at' => $this->no_show_at?->toISOString(),
            'cancellation_reason' => $this->cancellation_reason,

            // Consultation outcomes
            'recommendations' => $this->recommendations,
            'estimated_cost' => $this->estimated_cost,
            'formatted_estimated_cost' => $this->estimated_cost ?
                '£' . number_format($this->estimated_cost / 100, 2) : null,
            'estimated_duration' => $this->estimated_duration,
            'complexity_level' => $this->complexity_level,
            'feasibility_assessment' => $this->feasibility_assessment,

            // Documents and attachments
            'attachments' => $this->whenLoaded('attachments', function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'name' => $attachment->name,
                        'type' => $attachment->type,
                        'size' => $attachment->size,
                        'url' => $attachment->url,
                        'uploaded_at' => $attachment->created_at?->toISOString(),
                    ];
                });
            }),

            // Consultation notes (detailed breakdown)
            'notes' => $this->whenLoaded('consultationNotes', function () {
                return ConsultationNoteResource::collection($this->consultationNotes);
            }),

            // Notification and reminder settings
            'reminder_sent' => $this->reminder_sent,
            'confirmation_sent' => $this->confirmation_sent,
            'follow_up_sent' => $this->follow_up_sent,
            'last_reminder_at' => $this->last_reminder_at?->toISOString(),

            // Participant tracking
            'participants' => [
                'client' => [
                    'name' => $this->client_name,
                    'email' => $this->client_email,
                    'phone' => $this->client_phone,
                    'joined_at' => $this->client_joined_at?->toISOString(),
                    'left_at' => $this->client_left_at?->toISOString(),
                    'duration_minutes' => $this->client_duration_minutes,
                ],
                'consultant' => [
                    'name' => $this->consultant_name,
                    'email' => $this->consultant_email,
                    'phone' => $this->consultant_phone,
                    'joined_at' => $this->consultant_joined_at?->toISOString(),
                    'left_at' => $this->consultant_left_at?->toISOString(),
                    'duration_minutes' => $this->consultant_duration_minutes,
                ],
                'total_participants' => $this->total_participants ?? 2,
                'actual_duration_minutes' => $this->actual_duration_minutes,
            ],

            // Quality and feedback
            'client_satisfaction_rating' => $this->client_satisfaction_rating,
            'client_feedback' => $this->client_feedback,
            'consultant_rating' => $this->consultant_rating,
            'consultant_feedback' => $this->consultant_feedback,
            'internal_rating' => $this->internal_rating,
            'internal_notes' => $this->when(
                $request->user() && $request->user()->hasPermission('view_internal_consultation_notes'),
                $this->internal_notes
            ),

            // Technical details (for virtual consultations)
            'technical_details' => $this->when($this->format === 'video', [
                'platform' => $this->meeting_platform,
                'recording_enabled' => $this->recording_enabled,
                'recording_url' => $this->when(
                    $request->user() && $request->user()->hasPermission('view_consultation_recordings'),
                    $this->recording_url
                ),
                'connection_quality' => $this->connection_quality,
                'technical_issues' => $this->technical_issues,
            ]),

            // Business intelligence
            'consultation_value' => $this->consultation_value ?? 0,
            'formatted_consultation_value' => $this->consultation_value ?
                '£' . number_format($this->consultation_value / 100, 2) : '£0.00',
            'conversion_potential' => $this->conversion_potential,
            'lead_score' => $this->lead_score,
            'priority_level' => $this->priority_level,

            // Integration data
            'calendar_event_id' => $this->calendar_event_id,
            'crm_contact_id' => $this->crm_contact_id,
            'external_meeting_id' => $this->external_meeting_id,

            // Metadata and custom fields
            'metadata' => $this->metadata,
            'custom_fields' => $this->custom_fields,

            // Audit trail
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'updated_at_human' => $this->updated_at?->diffForHumans(),

            // Actions available to current user
            'available_actions' => $this->getAvailableActions($request),
        ];
    }

    /**
     * Get formatted duration
     */
    private function getFormattedDuration(): string
    {
        if (!$this->duration_minutes) {
            return '0m';
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }

    /**
     * Get status display name
     */
    private function getStatusDisplayAttribute(): string
    {
        return match($this->status) {
            'scheduled' => 'Scheduled',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_show' => 'No Show',
            'rescheduled' => 'Rescheduled',
            default => ucfirst($this->status)
        };
    }

    /**
     * Get status color for UI
     */
    private function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'scheduled' => 'blue',
            'in_progress' => 'green',
            'completed' => 'green',
            'cancelled' => 'red',
            'no_show' => 'red',
            'rescheduled' => 'orange',
            default => 'gray'
        };
    }

    /**
     * Get consultation type display name
     */
    private function getTypeDisplayAttribute(): string
    {
        return match($this->type) {
            'pre_booking' => 'Pre-Booking Consultation',
            'design' => 'Design Consultation',
            'planning' => 'Planning Session',
            'technical' => 'Technical Review',
            'follow_up' => 'Follow-Up Meeting',
            default => ucfirst(str_replace('_', ' ', $this->type))
        };
    }

    /**
     * Get format display name
     */
    private function getFormatDisplayAttribute(): string
    {
        return match($this->format) {
            'phone' => 'Phone Call',
            'video' => 'Video Call',
            'in_person' => 'In Person',
            'site_visit' => 'Site Visit',
            default => ucfirst(str_replace('_', ' ', $this->format))
        };
    }

    /**
     * Get workflow stage display name
     */
    private function getWorkflowStageDisplayAttribute(): string
    {
        return match($this->workflow_stage) {
            'scheduled' => 'Scheduled',
            'preparing' => 'Preparing',
            'in_progress' => 'In Progress',
            'wrapping_up' => 'Wrapping Up',
            'completed' => 'Completed',
            'follow_up_pending' => 'Follow-Up Pending',
            'closed' => 'Closed',
            default => ucfirst(str_replace('_', ' ', $this->workflow_stage))
        };
    }

    /**
     * Check if consultation is upcoming
     */
    private function isUpcoming(): bool
    {
        return $this->scheduled_at && $this->scheduled_at > now();
    }

    /**
     * Check if consultation is today
     */
    private function isToday(): bool
    {
        return $this->scheduled_at && $this->scheduled_at->isToday();
    }

    /**
     * Check if consultation is in the past
     */
    private function isPast(): bool
    {
        return $this->scheduled_at && $this->scheduled_at < now();
    }

    /**
     * Check if consultation is currently in progress
     */
    private function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Get time until consultation
     */
    private function getTimeUntilConsultation(): ?string
    {
        if (!$this->isUpcoming()) {
            return null;
        }

        return $this->scheduled_at->diffForHumans();
    }

    /**
     * Get duration since completion
     */
    private function getDurationSinceCompletion(): ?string
    {
        if (!$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffForHumans();
    }

    /**
     * Check if consultation can be rescheduled
     */
    private function canBeRescheduled(): bool
    {
        return in_array($this->status, ['scheduled']) &&
            $this->scheduled_at > now()->addHours(24);
    }

    /**
     * Check if consultation can be cancelled
     */
    private function canBeCancelled(): bool
    {
        return in_array($this->status, ['scheduled']) &&
            $this->scheduled_at > now()->addHours(2);
    }

    /**
     * Check if consultation can be started
     */
    private function canBeStarted(): bool
    {
        return $this->status === 'scheduled' &&
            $this->scheduled_at <= now()->addMinutes(15) &&
            $this->scheduled_at >= now()->subMinutes(15);
    }

    /**
     * Check if consultation can be completed
     */
    private function canBeCompleted(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Get available actions for current user
     */
    private function getAvailableActions(Request $request): array
    {
        $actions = [];
        $user = $request->user();

        if (!$user) {
            return $actions;
        }

        // Client actions
        if ($user->id === $this->user_id) {
            if ($this->canBeRescheduled()) {
                $actions[] = 'reschedule';
            }
            if ($this->canBeCancelled()) {
                $actions[] = 'cancel';
            }
            if ($this->canBeStarted()) {
                $actions[] = 'join';
            }
            if ($this->status === 'completed' && !$this->client_satisfaction_rating) {
                $actions[] = 'rate';
            }
        }

        // Consultant/Admin actions
        if ($user->hasPermission('manage_consultations')) {
            if ($this->canBeStarted()) {
                $actions[] = 'start';
            }
            if ($this->canBeCompleted()) {
                $actions[] = 'complete';
            }
            if ($this->status === 'scheduled') {
                $actions[] = 'mark_no_show';
            }
            if ($this->status === 'completed' && $this->follow_up_required && !$this->follow_up_sent) {
                $actions[] = 'send_follow_up';
            }
        }

        // Admin actions
        if ($user->hasPermission('admin_consultations')) {
            $actions[] = 'view_details';
            $actions[] = 'export';
            if ($this->recording_url) {
                $actions[] = 'view_recording';
            }
        }

        return array_unique($actions);
    }
}

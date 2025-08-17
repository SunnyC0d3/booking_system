<?php

namespace App\Requests\V1;

class UpdateConsultationRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $consultation = $this->route('consultation');
        $user = $this->user();

        // Users can update their own consultations, admins can update any
        return $consultation->user_id === $user->id ||
            $user->hasPermission('manage_consultations');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'scheduled_at' => 'sometimes|date',
            'duration_minutes' => 'sometimes|integer|min:15|max:180',
            'type' => 'sometimes|in:pre_booking,design,planning,technical,follow_up',
            'format' => 'sometimes|in:phone,video,in_person,site_visit',

            // Client information (client can update, admin can update any)
            'client_name' => 'sometimes|string|max:255',
            'client_email' => 'sometimes|email|max:255',
            'client_phone' => 'sometimes|nullable|string|max:20',

            // Consultation content
            'consultation_notes' => 'sometimes|nullable|string|max:2000',
            'preparation_instructions' => 'sometimes|nullable|string|max:2000',
            'consultation_questions' => 'sometimes|nullable|array',
            'consultation_questions.*' => 'string|max:500',

            // Meeting details (format-specific)
            'meeting_location' => 'sometimes|nullable|string|max:500',
            'meeting_instructions' => 'sometimes|nullable|array',
            'preferred_platform' => 'sometimes|nullable|string|in:zoom,teams,google_meet,phone',

            // Admin-only fields
            'workflow_stage' => 'sometimes|in:scheduled,preparing,in_progress,wrapping_up,completed,follow_up_pending,closed',
            'consultant_notes' => 'sometimes|nullable|string|max:2000',
            'outcome_summary' => 'sometimes|nullable|string|max:2000',
            'recommendations' => 'sometimes|nullable|string|max:2000',
            'estimated_cost' => 'sometimes|nullable|integer|min:0|max:99999999',
            'estimated_duration' => 'sometimes|nullable|integer|min:15|max:480',
            'complexity_level' => 'sometimes|nullable|in:simple,moderate,complex,very_complex',
            'feasibility_assessment' => 'sometimes|nullable|in:feasible,challenging,not_feasible,needs_revision',
            'follow_up_required' => 'sometimes|boolean',
            'follow_up_notes' => 'sometimes|nullable|string|max:1000',

            // Session completion fields
            'client_satisfaction_rating' => 'sometimes|nullable|integer|min:1|max:5',
            'client_feedback' => 'sometimes|nullable|string|max:1000',
            'consultant_rating' => 'sometimes|nullable|integer|min:1|max:5',
            'consultant_feedback' => 'sometimes|nullable|string|max:1000',

            // Scheduling preferences
            'timezone' => 'sometimes|string|max:50',
            'language_preference' => 'sometimes|string|max:10',
            'accessibility_requirements' => 'sometimes|nullable|string|max:500',

            // Additional options
            'send_reminders' => 'sometimes|boolean',
            'allow_recording' => 'sometimes|boolean',

            // Metadata
            'metadata' => 'sometimes|nullable|array',
            'priority_level' => 'sometimes|in:low,normal,high,urgent',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'scheduled_at.date' => 'Please provide a valid consultation date and time.',
            'duration_minutes.min' => 'Consultation duration must be at least 15 minutes.',
            'duration_minutes.max' => 'Consultation duration cannot exceed 3 hours.',
            'type.in' => 'Invalid consultation type selected.',
            'format.in' => 'Invalid consultation format selected.',
            'client_name.max' => 'Client name cannot exceed 255 characters.',
            'client_email.email' => 'Please provide a valid email address.',
            'consultation_questions.*.max' => 'Each question cannot exceed 500 characters.',
            'preferred_platform.in' => 'Invalid platform selected.',
            'workflow_stage.in' => 'Invalid workflow stage selected.',
            'estimated_cost.min' => 'Estimated cost cannot be negative.',
            'estimated_duration.min' => 'Estimated duration must be at least 15 minutes.',
            'complexity_level.in' => 'Invalid complexity level selected.',
            'feasibility_assessment.in' => 'Invalid feasibility assessment selected.',
            'client_satisfaction_rating.between' => 'Rating must be between 1 and 5.',
            'consultant_rating.between' => 'Rating must be between 1 and 5.',
            'priority_level.in' => 'Invalid priority level selected.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $consultation = $this->route('consultation');

            // Validate consultation status for updates
            $this->validateConsultationStatus($validator, $consultation);

            // Validate rescheduling constraints
            $this->validateReschedulingConstraints($validator, $consultation);

            // Validate format-specific requirements
            $this->validateFormatSpecificRequirements($validator, $consultation);

            // Validate admin-only field access
            $this->validateAdminOnlyFields($validator, $consultation);

            // Validate business hours for rescheduling
            $this->validateBusinessHours($validator, $consultation);

            // Validate consultant availability for rescheduling
            $this->validateConsultantAvailability($validator, $consultation);

            // Validate completion requirements
            $this->validateCompletionRequirements($validator, $consultation);
        });
    }

    /**
     * Validate consultation status for updates
     */
    private function validateConsultationStatus($validator, $consultation): void
    {
        // Cannot update cancelled or no-show consultations
        if (in_array($consultation->status, ['cancelled', 'no_show'])) {
            $validator->errors()->add('consultation', 'Cannot update cancelled or no-show consultations.');
            return;
        }

        // Cannot update completed consultations (except for feedback)
        if ($consultation->status === 'completed') {
            $allowedFields = ['client_satisfaction_rating', 'client_feedback', 'metadata'];
            $providedFields = array_keys($this->validated());
            $invalidFields = array_diff($providedFields, $allowedFields);

            if (!empty($invalidFields) && !$this->user()->hasPermission('manage_consultations')) {
                $validator->errors()->add('consultation', 'Completed consultations can only have feedback updated.');
            }
        }

        // Check if consultation is in progress
        if ($consultation->status === 'in_progress') {
            $restrictedFields = ['scheduled_at', 'type', 'format', 'duration_minutes'];
            $providedFields = array_keys($this->validated());
            $invalidFields = array_intersect($providedFields, $restrictedFields);

            if (!empty($invalidFields)) {
                $validator->errors()->add('consultation', 'Cannot change core details while consultation is in progress.');
            }
        }
    }

    /**
     * Validate rescheduling constraints
     */
    private function validateReschedulingConstraints($validator, $consultation): void
    {
        if (!$this->has('scheduled_at')) {
            return;
        }

        $newScheduledAt = \Carbon\Carbon::parse($this->input('scheduled_at'));
        $originalScheduledAt = $consultation->scheduled_at;

        // Check if actually rescheduling
        if ($newScheduledAt->equalTo($originalScheduledAt)) {
            return;
        }

        // Validate rescheduling permissions and timing
        $user = $this->user();
        $hoursUntilOriginal = now()->diffInHours($originalScheduledAt, false);

        // Client rescheduling rules
        if ($consultation->user_id === $user->id && !$user->hasPermission('manage_consultations')) {
            // Clients need at least 4 hours notice to reschedule
            if ($hoursUntilOriginal < 4) {
                $validator->errors()->add('scheduled_at', 'Consultations must be rescheduled at least 4 hours in advance.');
                return;
            }

            // Clients can only reschedule once
            if ($consultation->rescheduled_count >= 1) {
                $validator->errors()->add('scheduled_at', 'You can only reschedule a consultation once. Please contact support for further changes.');
                return;
            }
        }

        // Validate new time constraints
        if ($newScheduledAt->lt(now()->addHours(2))) {
            $validator->errors()->add('scheduled_at', 'New consultation time must be at least 2 hours from now.');
        }

        if ($newScheduledAt->gt(now()->addDays(90))) {
            $validator->errors()->add('scheduled_at', 'Consultations cannot be scheduled more than 90 days in advance.');
        }

        // Check against main booking if exists
        if ($consultation->main_booking_id) {
            $mainBooking = $consultation->mainBooking;
            if ($mainBooking && $newScheduledAt->gte($mainBooking->scheduled_at)) {
                $validator->errors()->add('scheduled_at', 'Consultation must remain before the main service date.');
            }
        }
    }

    /**
     * Validate format-specific requirements
     */
    private function validateFormatSpecificRequirements($validator, $consultation): void
    {
        $format = $this->input('format', $consultation->format);
        $meetingLocation = $this->input('meeting_location', $consultation->meeting_location);
        $preferredPlatform = $this->input('preferred_platform');

        switch ($format) {
            case 'in_person':
                if (empty($meetingLocation)) {
                    $validator->errors()->add('meeting_location', 'Meeting location is required for in-person consultations.');
                }
                break;

            case 'site_visit':
                if (empty($meetingLocation)) {
                    $validator->errors()->add('meeting_location', 'Site location is required for site visit consultations.');
                }

                // Site visits need longer duration
                $duration = $this->input('duration_minutes', $consultation->duration_minutes);
                if ($duration < 45) {
                    $validator->errors()->add('duration_minutes', 'Site visits typically require at least 45 minutes.');
                }
                break;

            case 'video':
                if ($preferredPlatform === 'phone') {
                    $validator->errors()->add('preferred_platform', 'Phone platform is not compatible with video format.');
                }
                break;

            case 'phone':
                if ($preferredPlatform && !in_array($preferredPlatform, ['phone', 'zoom'])) {
                    $validator->errors()->add('preferred_platform', 'Invalid platform for phone consultations.');
                }
                break;
        }

        // Check if format change affects existing preparation
        if ($this->has('format') && $format !== $consultation->format) {
            $hoursUntilConsultation = now()->diffInHours($consultation->scheduled_at, false);

            if ($hoursUntilConsultation < 24 && !$this->user()->hasPermission('manage_consultations')) {
                $validator->errors()->add('format', 'Cannot change consultation format within 24 hours without admin approval.');
            }
        }
    }

    /**
     * Validate admin-only field access
     */
    private function validateAdminOnlyFields($validator, $consultation): void
    {
        $user = $this->user();

        if (!$user->hasPermission('manage_consultations')) {
            $adminOnlyFields = [
                'workflow_stage', 'consultant_notes', 'outcome_summary', 'recommendations',
                'estimated_cost', 'estimated_duration', 'complexity_level', 'feasibility_assessment',
                'follow_up_required', 'follow_up_notes', 'consultant_rating', 'consultant_feedback'
            ];

            $providedFields = array_keys($this->validated());
            $invalidFields = array_intersect($providedFields, $adminOnlyFields);

            if (!empty($invalidFields)) {
                $validator->errors()->add('permissions', 'You do not have permission to update: ' . implode(', ', $invalidFields));
            }
        }
    }

    /**
     * Validate business hours for rescheduling
     */
    private function validateBusinessHours($validator, $consultation): void
    {
        if (!$this->has('scheduled_at')) {
            return;
        }

        $newScheduledAt = \Carbon\Carbon::parse($this->input('scheduled_at'));
        $dayOfWeek = $newScheduledAt->dayOfWeek;
        $hour = $newScheduledAt->hour;

        // Check if it's a weekend
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            $validator->errors()->add('scheduled_at', 'Consultations are only available Monday through Friday.');
        }

        // Check business hours (9 AM to 6 PM)
        if ($hour < 9 || $hour >= 18) {
            $validator->errors()->add('scheduled_at', 'Consultations are only available between 9:00 AM and 6:00 PM.');
        }

        // Special handling for lunch break (12:00-13:00)
        if ($hour === 12) {
            $validator->errors()->add('scheduled_at', 'Consultations are not available during lunch hour (12:00-1:00 PM).');
        }
    }

    /**
     * Validate consultant availability for rescheduling
     */
    private function validateConsultantAvailability($validator, $consultation): void
    {
        if (!$this->has('scheduled_at')) {
            return;
        }

        $newScheduledAt = \Carbon\Carbon::parse($this->input('scheduled_at'));
        $durationMinutes = $this->input('duration_minutes', $consultation->duration_minutes);
        $endTime = $newScheduledAt->clone()->addMinutes($durationMinutes);

        // Check for conflicting consultations (exclude current consultation)
        $conflictingConsultations = \App\Models\ConsultationBooking::where('status', 'scheduled')
            ->where('id', '!=', $consultation->id)
            ->where(function ($query) use ($newScheduledAt, $endTime) {
                $query->where(function ($q) use ($newScheduledAt, $endTime) {
                    $q->where('scheduled_at', '<', $endTime)
                        ->where('ends_at', '>', $newScheduledAt);
                });
            })
            ->count();

        // Allow max 2 concurrent consultations for rescheduling
        if ($conflictingConsultations >= 2) {
            $validator->errors()->add('scheduled_at', 'This time slot conflicts with existing consultations. Please select a different time.');
        }
    }

    /**
     * Validate completion requirements
     */
    private function validateCompletionRequirements($validator, $consultation): void
    {
        // If updating to completed status, ensure required fields are present
        $workflowStage = $this->input('workflow_stage');

        if ($workflowStage === 'completed') {
            if (!$this->has('outcome_summary') && !$consultation->outcome_summary) {
                $validator->errors()->add('outcome_summary', 'Outcome summary is required when completing a consultation.');
            }

            if (!$this->has('feasibility_assessment') && !$consultation->feasibility_assessment) {
                $validator->errors()->add('feasibility_assessment', 'Feasibility assessment is required when completing a consultation.');
            }
        }

        // Validate rating ranges
        if ($this->has('client_satisfaction_rating')) {
            $rating = $this->input('client_satisfaction_rating');
            if ($rating < 1 || $rating > 5) {
                $validator->errors()->add('client_satisfaction_rating', 'Rating must be between 1 and 5.');
            }
        }

        if ($this->has('consultant_rating')) {
            $rating = $this->input('consultant_rating');
            if ($rating < 1 || $rating > 5) {
                $validator->errors()->add('consultant_rating', 'Rating must be between 1 and 5.');
            }
        }

        // Validate estimated cost format
        if ($this->has('estimated_cost')) {
            $cost = $this->input('estimated_cost');
            if ($cost !== null && $cost < 0) {
                $validator->errors()->add('estimated_cost', 'Estimated cost cannot be negative.');
            }
        }
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $consultation = $this->route('consultation');

        // Convert estimated cost from pounds to pence if needed
        if ($this->has('estimated_cost') && is_numeric($this->input('estimated_cost'))) {
            $value = $this->input('estimated_cost');
            if (str_contains((string)$value, '.') || ($value > 0 && $value < 100)) {
                $this->merge(['estimated_cost' => (int) round($value * 100)]);
            }
        }

        // Convert boolean fields
        $booleanFields = ['send_reminders', 'allow_recording', 'follow_up_required'];
        foreach ($booleanFields as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        // Track rescheduling count
        if ($this->has('scheduled_at')) {
            $newScheduledAt = \Carbon\Carbon::parse($this->input('scheduled_at'));
            if (!$newScheduledAt->equalTo($consultation->scheduled_at)) {
                $currentCount = $consultation->rescheduled_count ?? 0;
                $this->merge(['rescheduled_count' => $currentCount + 1]);
            }
        }

        // Set timezone if not provided
        if (!$this->has('timezone') && $this->has('scheduled_at')) {
            $this->merge(['timezone' => config('app.timezone', 'UTC')]);
        }
    }
}

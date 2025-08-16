<?php

namespace App\Requests\V1;

class BulkUpdateCapacityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('manage_booking_capacity');
    }

    public function rules(): array
    {
        return [
            'service_id' => 'required|exists:services,id',
            'service_location_id' => 'nullable|exists:service_locations,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'action' => 'required|in:block,unblock,set_capacity',
            'capacity' => 'required_if:action,set_capacity|integer|min:1|max:50',
            'reason' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $startDate = Carbon::parse($this->start_date);
            $endDate = Carbon::parse($this->end_date);

            if ($endDate->diffInDays($startDate) > 90) {
                $validator->errors()->add('end_date', 'Date range cannot exceed 90 days.');
            }
        });
    }
}

<?php

namespace App\Requests\V1;

class FilterBookingRequest extends BaseFormRequest
{
public function authorize(): bool
{
return true; // Authorization handled by controller/service
}

public function rules(): array
{
return [
'status' => 'nullable|in:pending,confirmed,in_progress,completed,cancelled,no_show',
'payment_status' => 'nullable|in:pending,partial,paid,refunded,failed',
'service_id' => 'nullable|exists:services,id',
'service_location_id' => 'nullable|exists:service_locations,id',
'user_id' => 'nullable|exists:users,id',
'from_date' => 'nullable|date',
'to_date' => 'nullable|date|after_or_equal:from_date',
'search' => 'nullable|string|max:255',
'upcoming_only' => 'nullable|boolean',
'per_page' => 'nullable|integer|min:1|max:100',
'sort_by' => 'nullable|in:scheduled_at,created_at,total_amount,status',
'sort_direction' => 'nullable|in:asc,desc',
];
}

protected function prepareForValidation(): void
{
// Set default pagination
if (!$this->has('per_page')) {
$this->merge(['per_page' => 15]);
}

// Set default sorting
if (!$this->has('sort_by')) {
$this->merge(['sort_by' => 'scheduled_at']);
}

if (!$this->has('sort_direction')) {
$this->merge(['sort_direction' => 'desc']);
}
}
}

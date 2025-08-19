<?php

namespace App\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConsultationFilter extends QueryFilter
{
    protected array $sortable = [
        'scheduled_at' => 'scheduled_at',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'status' => 'status',
        'type' => 'type',
        'format' => 'format',
        'duration_minutes' => 'duration_minutes',
        'client_name' => 'client_name',
        'consultation_reference' => 'consultation_reference',
    ];

    public function userId(string|int $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('user_id', $value);
    }

    public function serviceId(string|int $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('service_id', $value);
    }

    public function mainBookingId(string|int $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('main_booking_id', $value);
    }

    public function status(string|array $value)
    {
        $allowedStatuses = ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'];

        if (is_array($value)) {
            $validStatuses = array_intersect($value, $allowedStatuses);
            if (!empty($validStatuses)) {
                return $this->builder->whereIn('status', $validStatuses);
            }
        } else {
            $statuses = explode(',', $value);
            $validStatuses = array_intersect($statuses, $allowedStatuses);
            if (!empty($validStatuses)) {
                return $this->builder->whereIn('status', $validStatuses);
            }
        }

        return $this->builder;
    }

    public function type(string|array $value)
    {
        $allowedTypes = ['pre_booking', 'design', 'technical', 'planning', 'follow_up'];

        if (is_array($value)) {
            $validTypes = array_intersect($value, $allowedTypes);
            if (!empty($validTypes)) {
                return $this->builder->whereIn('type', $validTypes);
            }
        } else {
            $types = explode(',', $value);
            $validTypes = array_intersect($types, $allowedTypes);
            if (!empty($validTypes)) {
                return $this->builder->whereIn('type', $validTypes);
            }
        }

        return $this->builder;
    }

    public function format(string|array $value)
    {
        $allowedFormats = ['video', 'phone', 'in_person', 'site_visit'];

        if (is_array($value)) {
            $validFormats = array_intersect($value, $allowedFormats);
            if (!empty($validFormats)) {
                return $this->builder->whereIn('format', $validFormats);
            }
        } else {
            $formats = explode(',', $value);
            $validFormats = array_intersect($formats, $allowedFormats);
            if (!empty($validFormats)) {
                return $this->builder->whereIn('format', $validFormats);
            }
        }

        return $this->builder;
    }

    public function dateFrom(string $value)
    {
        try {
            $date = Carbon::parse($value)->startOfDay();
            return $this->builder->where('scheduled_at', '>=', $date);
        } catch (\Exception $e) {
            return $this->builder;
        }
    }

    public function dateTo(string $value)
    {
        try {
            $date = Carbon::parse($value)->endOfDay();
            return $this->builder->where('scheduled_at', '<=', $date);
        } catch (\Exception $e) {
            return $this->builder;
        }
    }

    public function clientName(string $value)
    {
        $this->safeLikeQuery('client_name', $value);
        return $this->builder;
    }

    public function clientEmail(string $value)
    {
        $this->safeLikeQuery('client_email', $value);
        return $this->builder;
    }

    public function consultationReference(string $value)
    {
        $this->safeLikeQuery('consultation_reference', $value);
        return $this->builder;
    }

    public function search(string $value)
    {
        return $this->builder->where(function (Builder $query) use ($value) {
            $this->builder = $query;
            $this->safeLikeQuery('consultation_reference', $value);

            $query->orWhere(function (Builder $subQuery) use ($value) {
                $this->builder = $subQuery;
                $this->safeLikeQuery('client_name', $value);
            });

            $query->orWhere(function (Builder $subQuery) use ($value) {
                $this->builder = $subQuery;
                $this->safeLikeQuery('client_email', $value);
            });

            $query->orWhere(function (Builder $subQuery) use ($value) {
                $this->builder = $subQuery;
                $this->safeLikeQuery('consultation_notes', $value);
            });
        });
    }

    public function durationMinutes(string $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('duration_minutes', $value);
    }

    public function durationMin(string $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('duration_minutes', '>=', $value);
    }

    public function durationMax(string $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('duration_minutes', '<=', $value);
    }

    public function priority(string|array $value)
    {
        $allowedPriorities = ['low', 'normal', 'medium', 'high', 'urgent'];

        if (is_array($value)) {
            $validPriorities = array_intersect($value, $allowedPriorities);
            if (!empty($validPriorities)) {
                return $this->builder->whereIn('priority', $validPriorities);
            }
        } else {
            $priorities = explode(',', $value);
            $validPriorities = array_intersect($priorities, $allowedPriorities);
            if (!empty($validPriorities)) {
                return $this->builder->whereIn('priority', $validPriorities);
            }
        }

        return $this->builder;
    }

    public function workflowStage(string|array $value)
    {
        $allowedStages = ['scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'];

        if (is_array($value)) {
            $validStages = array_intersect($value, $allowedStages);
            if (!empty($validStages)) {
                return $this->builder->whereIn('workflow_stage', $validStages);
            }
        } else {
            $stages = explode(',', $value);
            $validStages = array_intersect($stages, $allowedStages);
            if (!empty($validStages)) {
                return $this->builder->whereIn('workflow_stage', $validStages);
            }
        }

        return $this->builder;
    }

    public function hasMainBooking(string $value)
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolValue !== null) {
            if ($boolValue) {
                return $this->builder->whereNotNull('main_booking_id');
            } else {
                return $this->builder->whereNull('main_booking_id');
            }
        }

        return $this->builder;
    }

    public function consultantName(string $value)
    {
        $this->safeLikeQuery('consultant_name', $value);
        return $this->builder;
    }

    public function include(string|array $value)
    {
        $allowedIncludes = [
            'service',
            'user',
            'mainBooking',
            'consultationNotes'
        ];

        if (is_string($value)) {
            $includes = explode(',', $value);
        } else {
            $includes = $value;
        }

        $validIncludes = array_intersect($includes, $allowedIncludes);

        if (!empty($validIncludes)) {
            return $this->builder->with($validIncludes);
        }

        return $this->builder;
    }

    protected function isAllowedFilter(string $method): bool
    {
        $allowedMethods = [
            'userId',
            'serviceId',
            'mainBookingId',
            'status',
            'type',
            'format',
            'dateFrom',
            'dateTo',
            'clientName',
            'clientEmail',
            'consultationReference',
            'search',
            'durationMinutes',
            'durationMin',
            'durationMax',
            'priority',
            'workflowStage',
            'hasMainBooking',
            'consultantName',
            'include',
            'sort'
        ];

        return in_array($method, $allowedMethods);
    }
}

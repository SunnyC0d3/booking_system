<?php

namespace App\Filters\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingFilter extends QueryFilter
{
    protected array $sortable = [
        'scheduled_at' => 'scheduled_at',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'total_amount' => 'total_amount',
        'status' => 'status',
        'payment_status' => 'payment_status',
        'client_name' => 'client_name',
        'booking_reference' => 'booking_reference',
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

    public function status(string|array $value)
    {
        $allowedStatuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];

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

    public function paymentStatus(string|array $value)
    {
        $allowedStatuses = ['pending', 'paid', 'partially_paid', 'refunded', 'failed'];

        if (is_array($value)) {
            $validStatuses = array_intersect($value, $allowedStatuses);
            if (!empty($validStatuses)) {
                return $this->builder->whereIn('payment_status', $validStatuses);
            }
        } else {
            $statuses = explode(',', $value);
            $validStatuses = array_intersect($statuses, $allowedStatuses);
            if (!empty($validStatuses)) {
                return $this->builder->whereIn('payment_status', $validStatuses);
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

    public function bookingReference(string $value)
    {
        $this->safeLikeQuery('booking_reference', $value);
        return $this->builder;
    }

    public function search(string $value)
    {
        return $this->builder->where(function (Builder $query) use ($value) {
            $this->builder = $query;
            $this->safeLikeQuery('booking_reference', $value);

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
                $this->safeLikeQuery('notes', $value);
            });
        });
    }

    public function totalAmount(string $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('total_amount', $value);
    }

    public function totalAmountMin(string $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('total_amount', '>=', $value);
    }

    public function totalAmountMax(string $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('total_amount', '<=', $value);
    }

    public function requiresConsultation(string $value)
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolValue !== null) {
            return $this->builder->where('requires_consultation', $boolValue);
        }

        return $this->builder;
    }

    public function consultationCompleted(string $value)
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolValue !== null) {
            if ($boolValue) {
                return $this->builder->whereNotNull('consultation_completed_at');
            } else {
                return $this->builder->whereNull('consultation_completed_at');
            }
        }

        return $this->builder;
    }

    public function locationId(string|int $value)
    {
        if (!is_numeric($value)) {
            return $this->builder;
        }

        return $this->builder->where('service_location_id', $value);
    }

    public function include(string|array $value)
    {
        $allowedIncludes = [
            'service',
            'serviceLocation',
            'user',
            'bookingAddOns',
            'bookingAddOns.serviceAddOn',
            'consultationBooking'
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
            'status',
            'paymentStatus',
            'dateFrom',
            'dateTo',
            'clientName',
            'clientEmail',
            'bookingReference',
            'search',
            'totalAmount',
            'totalAmountMin',
            'totalAmountMax',
            'requiresConsultation',
            'consultationCompleted',
            'locationId',
            'include',
            'sort'
        ];

        return in_array($method, $allowedMethods);
    }
}

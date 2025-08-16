<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Requests\V1\StoreBookingRequest;
use App\Requests\V1\UpdateBookingRequest;
use App\Requests\V1\FilterBookingRequest;
use App\Services\V1\Bookings\BookingService;
use App\Services\V1\Bookings\TimeSlotService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;

class BookingController extends Controller
{
    use ApiResponses;

    private BookingService $bookingService;
    private TimeSlotService $timeSlotService;

    public function __construct(BookingService $bookingService, TimeSlotService $timeSlotService)
    {
        $this->bookingService = $bookingService;
        $this->timeSlotService = $timeSlotService;
    }

    public function index(FilterBookingRequest $request)
    {
        try {
            return $this->bookingService->getUserBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreBookingRequest $request)
    {
        try {
            return $this->bookingService->createBooking($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function show(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->getBookingDetails($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        try {
            return $this->bookingService->updateBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->cancelBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function reschedule(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->rescheduleBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function getAvailableSlots(Request $request, Service $service)
    {
        try {
            $date = Carbon::parse($request->input('date'));
            $locationId = $request->input('location_id');

            $location = $locationId ? ServiceLocation::find($locationId) : null;

            if ($locationId && !$location) {
                return $this->error('Invalid location specified', 404);
            }

            $slots = $this->timeSlotService->getAvailableSlots($service, $date, $location);

            return $this->ok('Available time slots retrieved successfully', [
                'date' => $date->format('Y-m-d'),
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration_minutes' => $service->duration_minutes,
                    'formatted_price' => $service->getFormattedPriceAttribute(),
                ],
                'location' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name,
                    'type' => $location->type,
                    'additional_charge' => $location->getFormattedAdditionalChargeAttribute(),
                ] : null,
                'slots' => $slots,
                'total_slots' => count($slots),
                'available_slots' => count(array_filter($slots, fn($slot) => $slot['is_available'])),
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getAvailabilitySummary(Request $request, Service $service)
    {
        try {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date', $startDate->clone()->addDays(7)));
            $locationId = $request->input('location_id');

            // Limit to 30 days to prevent performance issues
            if ($endDate->diffInDays($startDate) > 30) {
                $endDate = $startDate->clone()->addDays(30);
            }

            $location = $locationId ? ServiceLocation::find($locationId) : null;

            $summary = $this->timeSlotService->getAvailabilitySummary(
                $service,
                $startDate,
                $endDate,
                $location
            );

            return $this->ok('Availability summary retrieved successfully', [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                ],
                'location' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name,
                ] : null,
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'summary' => $summary,
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function checkSlotAvailability(Request $request, Service $service)
    {
        try {
            $startTime = Carbon::parse($request->input('start_time'));
            $durationMinutes = $request->input('duration_minutes', $service->duration_minutes);
            $locationId = $request->input('location_id');

            $location = $locationId ? ServiceLocation::find($locationId) : null;

            $isAvailable = $this->timeSlotService->isSlotAvailable(
                $service,
                $startTime,
                $durationMinutes,
                $location
            );

            $conflicts = [];
            if (!$isAvailable) {
                $conflicts = $this->timeSlotService->getConflictingBookings(
                    $service,
                    $startTime,
                    $durationMinutes,
                    $location
                )->map(function ($booking) {
                    return [
                        'booking_reference' => $booking->booking_reference,
                        'client_name' => $booking->client_name,
                        'scheduled_at' => $booking->scheduled_at->format('Y-m-d H:i'),
                        'ends_at' => $booking->ends_at->format('Y-m-d H:i'),
                        'status' => $booking->status,
                    ];
                })->toArray();
            }

            return $this->ok('Slot availability checked', [
                'is_available' => $isAvailable,
                'requested_slot' => [
                    'start_time' => $startTime->format('Y-m-d H:i'),
                    'end_time' => $startTime->clone()->addMinutes($durationMinutes)->format('Y-m-d H:i'),
                    'duration_minutes' => $durationMinutes,
                ],
                'conflicts' => $conflicts,
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getNextAvailableSlot(Request $request, Service $service)
    {
        try {
            $fromDate = $request->input('from_date') ?
                Carbon::parse($request->input('from_date')) :
                Carbon::now()->addDay();

            $locationId = $request->input('location_id');
            $location = $locationId ? ServiceLocation::find($locationId) : null;

            $nextSlot = $this->timeSlotService->getNextAvailableSlot($service, $fromDate, $location);

            if (!$nextSlot) {
                return $this->error('No available slots found in the next 30 days', 404);
            }

            return $this->ok('Next available slot found', [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                ],
                'next_slot' => $nextSlot,
            ]);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getPricingEstimate(Request $request, Service $service)
    {
        try {
            return $this->bookingService->getPricingEstimate($request, $service);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function completeConsultation(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->completeConsultation($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}

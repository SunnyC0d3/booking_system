<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Service;
use App\Requests\V1\StoreBookingRequest;
use App\Requests\V1\UpdateBookingRequest;
use App\Requests\V1\FilterBookingRequest;
use App\Services\V1\Bookings\BookingService;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Exception;

class BookingController extends Controller
{
    use ApiResponses;

    private BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(FilterBookingRequest $request)
    {
        try {
            return $this->bookingService->getAllBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function store(StoreBookingRequest $request)
    {
        try {
            return $this->bookingService->createBookingAsAdmin($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function show(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->getBookingDetailsAdmin($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        try {
            return $this->bookingService->updateBookingAsAdmin($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function destroy(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->deleteBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function confirm(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->confirmBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function markInProgress(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->markBookingInProgress($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function markCompleted(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->markBookingCompleted($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function markNoShow(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->markBookingNoShow($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function getStatistics(Request $request)
    {
        try {
            return $this->bookingService->getBookingStatistics($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function getCalendarData(Request $request)
    {
        try {
            return $this->bookingService->getCalendarData($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function export(Request $request)
    {
        try {
            return $this->bookingService->exportBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function bulkUpdate(Request $request)
    {
        try {
            return $this->bookingService->bulkUpdateBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    public function getDailySchedule(Request $request)
    {
        try {
            return $this->bookingService->getDailySchedule($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

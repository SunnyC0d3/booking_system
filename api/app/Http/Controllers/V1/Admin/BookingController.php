<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
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

    /**
     * Get all bookings with admin filtering
     */
    public function index(FilterBookingRequest $request)
    {
        try {
            return $this->bookingService->getAllBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create booking as admin (for customer)
     */
    public function store(StoreBookingRequest $request)
    {
        try {
            return $this->bookingService->createBooking($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get booking details with admin view
     */
    public function show(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->getBookingDetails($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update booking as admin
     */
    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        try {
            return $this->bookingService->updateBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Cancel booking (admin can cancel any booking)
     */
    public function cancel(Request $request, Booking $booking)
    {
        try {
            $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            return $this->bookingService->cancelBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Confirm a pending booking
     */
    public function confirm(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->confirmBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Mark booking as in progress
     */
    public function markInProgress(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->markBookingInProgress($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Mark booking as completed
     */
    public function markCompleted(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->markBookingCompleted($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Mark booking as no-show
     */
    public function markNoShow(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->markBookingNoShow($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get basic booking statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $request->validate([
                'period' => 'nullable|in:today,week,month,quarter,year',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            return $this->bookingService->getBookingStatistics($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(Request $request)
    {
        try {
            return $this->bookingService->getSystemHealth($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Process overdue notifications manually
     */
    public function processOverdueNotifications(Request $request)
    {
        try {
            return $this->bookingService->processOverdueNotifications($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Resend notification for a booking
     */
    public function resendNotification(Request $request, Booking $booking)
    {
        try {
            $request->validate([
                'notification_type' => 'required|in:booking_confirmation,booking_reminder,consultation_reminder',
            ]);

            $success = $this->bookingService->resendBookingNotification(
                $request,
                $booking,
                $request->notification_type
            );

            if ($success) {
                return $this->ok('Notification resent successfully');
            } else {
                return $this->error('Failed to resend notification', 500);
            }

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get booking notification statistics
     */
    public function getNotificationStats(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->getBookingNotificationStats($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

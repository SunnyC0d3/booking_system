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

    /**
     * Get all bookings (admin view)
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
     * Create booking as admin
     */
    public function store(StoreBookingRequest $request)
    {
        try {
            return $this->bookingService->createBookingAsAdmin($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get booking details (admin view)
     */
    public function show(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->getBookingDetailsAdmin($request, $booking);
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
            return $this->bookingService->updateBookingAsAdmin($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Force delete booking
     */
    public function destroy(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->deleteBooking($request, $booking);
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
     * Get booking statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            return $this->bookingService->getBookingStatistics($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get calendar data for booking management
     */
    public function getCalendarData(Request $request)
    {
        try {
            return $this->bookingService->getCalendarData($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Export bookings
     */
    public function export(Request $request)
    {
        try {
            return $this->bookingService->exportBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Bulk update bookings
     */
    public function bulkUpdate(Request $request)
    {
        try {
            return $this->bookingService->bulkUpdateBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get daily schedule
     */
    public function getDailySchedule(Request $request)
    {
        try {
            return $this->bookingService->getDailySchedule($request);
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
                'notification_type' => 'required|in:booking_confirmation,booking_reminder,payment_reminder,consultation_reminder',
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
     * Get booking notification history
     */
    public function getNotificationHistory(Request $request, Booking $booking)
    {
        try {
            return $this->bookingService->getBookingNotificationHistory($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Cancel multiple bookings
     */
    public function bulkCancel(Request $request)
    {
        try {
            $request->validate([
                'booking_ids' => 'required|array|min:1|max:50',
                'booking_ids.*' => 'exists:bookings,id',
                'reason' => 'required|string|max:500',
                'send_notifications' => 'boolean',
            ]);

            return $this->bookingService->bulkCancelBookings($request);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Reschedule multiple bookings
     */
    public function bulkReschedule(Request $request)
    {
        try {
            $request->validate([
                'booking_ids' => 'required|array|min:1|max:20',
                'booking_ids.*' => 'exists:bookings,id',
                'date_offset_days' => 'required|integer|min:-30|max:30',
                'time_offset_minutes' => 'nullable|integer|min:-480|max:480',
                'reason' => 'required|string|max:500',
                'send_notifications' => 'boolean',
            ]);

            return $this->bookingService->bulkRescheduleBookings($request);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics(Request $request)
    {
        try {
            $request->validate([
                'period' => 'required|in:day,week,month,quarter,year',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'service_id' => 'nullable|exists:services,id',
                'location_id' => 'nullable|exists:service_locations,id',
            ]);

            return $this->bookingService->getRevenueAnalytics($request);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get customer insights
     */
    public function getCustomerInsights(Request $request)
    {
        try {
            $request->validate([
                'period' => 'nullable|in:week,month,quarter,year',
                'limit' => 'nullable|integer|min:5|max:100',
            ]);

            return $this->bookingService->getCustomerInsights($request);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

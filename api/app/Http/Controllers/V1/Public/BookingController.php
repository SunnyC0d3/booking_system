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

        // Apply rate limiting middleware
        $this->middleware('throttle:api')->except(['index', 'show', 'getAvailableSlots']);
        $this->middleware('throttle:bookings:10,1')->only(['store']);
        $this->middleware('throttle:booking-updates:5,1')->only(['update', 'cancel', 'reschedule']);
    }

    /**
     * Get user's bookings
     */
    public function index(FilterBookingRequest $request)
    {
        try {
            return $this->bookingService->getUserBookings($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new booking
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
     * Get booking details
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
     * Update a booking
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
     * Cancel a booking
     */
    public function cancel(Request $request, Booking $booking)
    {
        try {
            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            return $this->bookingService->cancelBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Reschedule a booking
     */
    public function reschedule(Request $request, Booking $booking)
    {
        try {
            $request->validate([
                'scheduled_at' => 'required|date|after:now',
                'reason' => 'nullable|string|max:500',
            ]);

            return $this->bookingService->rescheduleBooking($request, $booking);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }

    /**
     * Get available time slots for a service
     */
    public function getAvailableSlots(Request $request, Service $service)
    {
        try {
            $request->validate([
                'date' => 'required|date|after_or_equal:today',
                'location_id' => 'nullable|exists:service_locations,id',
                'duration_minutes' => 'nullable|integer|min:15|max:480',
            ]);

            $date = Carbon::parse($request->input('date'));
            $locationId = $request->input('location_id');
            $durationMinutes = $request->input('duration_minutes', $service->duration_minutes);

            $location = $locationId ?
                ServiceLocation::where('service_id', $service->id)
                    ->where('id', $locationId)
                    ->where('is_active', true)
                    ->first() : null;

            if ($locationId && !$location) {
                return $this->error('Invalid location for this service', 422);
            }

            $endDate = $date->clone()->endOfDay();
            $slots = $this->timeSlotService->getAvailableSlots(
                $service,
                $date,
                $endDate,
                $location,
                $durationMinutes
            );

            return $this->ok('Available slots retrieved', [
                'service_id' => $service->id,
                'service_name' => $service->name,
                'date' => $date->format('Y-m-d'),
                'location' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name,
                ] : null,
                'duration_minutes' => $durationMinutes,
                'slots' => $slots->values(),
                'total_slots' => $slots->count(),
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
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

    /**
     * Resend booking confirmation email
     */
    public function resendConfirmation(Request $request, Booking $booking)
    {
        try {
            $user = $request->user();

            // Check if user owns this booking
            if ($booking->user_id !== $user->id) {
                return $this->error('You can only resend confirmations for your own bookings.', 403);
            }

            // Use the booking service to resend confirmation
            $success = $this->bookingService->resendBookingConfirmation($request, $booking);

            if ($success) {
                return $this->ok('Confirmation email resent successfully');
            } else {
                return $this->error('Failed to resend confirmation email', 500);
            }

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get booking summary for checkout
     */
    public function getBookingSummary(Request $request)
    {
        try {
            $request->validate([
                'service_id' => 'required|exists:services,id',
                'service_location_id' => 'nullable|exists:service_locations,id',
                'scheduled_at' => 'required|date|after:now',
                'duration_minutes' => 'nullable|integer|min:15|max:480',
                'add_ons' => 'nullable|array',
                'add_ons.*.service_add_on_id' => 'required|exists:service_add_ons,id',
                'add_ons.*.quantity' => 'required|integer|min:1|max:10',
            ]);

            $service = Service::with(['addOns'])->findOrFail($request->service_id);
            $location = $request->service_location_id ?
                ServiceLocation::find($request->service_location_id) : null;

            $durationMinutes = $request->duration_minutes ?? $service->duration_minutes;
            $scheduledAt = Carbon::parse($request->scheduled_at);
            $endsAt = $scheduledAt->clone()->addMinutes($durationMinutes);

            // Calculate pricing
            $basePrice = $service->base_price;
            $addOnTotal = 0;
            $addOnDetails = [];

            if ($request->add_ons) {
                foreach ($request->add_ons as $addOn) {
                    $serviceAddOn = $service->addOns()->find($addOn['service_add_on_id']);
                    if ($serviceAddOn) {
                        $quantity = $addOn['quantity'];
                        $lineTotal = $serviceAddOn->price * $quantity;
                        $addOnTotal += $lineTotal;

                        $addOnDetails[] = [
                            'id' => $serviceAddOn->id,
                            'name' => $serviceAddOn->name,
                            'price' => $serviceAddOn->price,
                            'quantity' => $quantity,
                            'line_total' => $lineTotal,
                        ];
                    }
                }
            }

            $totalAmount = $basePrice + $addOnTotal;

            return $this->ok('Booking summary calculated', [
                'service' => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'base_price' => $basePrice,
                    'duration_minutes' => $durationMinutes,
                ],
                'location' => $location ? [
                    'id' => $location->id,
                    'name' => $location->name,
                    'address' => $location->address,
                ] : null,
                'schedule' => [
                    'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                    'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                    'duration_minutes' => $durationMinutes,
                ],
                'pricing' => [
                    'base_price' => $basePrice,
                    'add_ons_total' => $addOnTotal,
                    'total_amount' => $totalAmount,
                    'formatted_total' => '£' . number_format($totalAmount / 100, 2),
                ],
                'add_ons' => $addOnDetails,
            ]);

        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 422);
        }
    }
}

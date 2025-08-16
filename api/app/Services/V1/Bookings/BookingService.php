<?php

namespace App\Services\V1\Bookings;

use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\ServiceAddOn;
use App\Models\BookingAddOn;
use App\Models\User;
use App\Resources\V1\BookingResource;
use App\Resources\V1\BookingCollection;
use App\Filters\V1\BookingFilter;
use App\Services\V1\Emails\Email;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class BookingService
{
    use ApiResponses;

    private TimeSlotService $timeSlotService;
    private Email $emailService;

    public function __construct(TimeSlotService $timeSlotService, Email $emailService)
    {
        $this->timeSlotService = $timeSlotService;
        $this->emailService = $emailService;
    }

    public function getUserBookings(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_own_bookings')) {
            return $this->error('You do not have permission to view bookings.', 403);
        }

        $bookings = Booking::where('user_id', $user->id)
            ->with(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn', 'payments'])
            ->when($request->input('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->input('payment_status'), function ($query, $paymentStatus) {
                return $query->where('payment_status', $paymentStatus);
            })
            ->when($request->input('from_date'), function ($query, $fromDate) {
                return $query->where('scheduled_at', '>=', Carbon::parse($fromDate));
            })
            ->when($request->input('to_date'), function ($query, $toDate) {
                return $query->where('scheduled_at', '<=', Carbon::parse($toDate)->endOfDay());
            })
            ->when($request->input('upcoming_only') === 'true', function ($query) {
                return $query->where('scheduled_at', '>', now());
            })
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return BookingResource::collection($bookings)->additional([
            'message' => 'Bookings retrieved successfully',
            'status' => 200
        ]);
    }

    public function getAllBookings(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_all_bookings')) {
            return $this->error('You do not have permission to view all bookings.', 403);
        }

        $bookings = Booking::with([
            'user',
            'service',
            'serviceLocation',
            'bookingAddOns.serviceAddOn',
            'payments'
        ])
            ->when($request->input('user_id'), function ($query, $userId) {
                return $query->where('user_id', $userId);
            })
            ->when($request->input('service_id'), function ($query, $serviceId) {
                return $query->where('service_id', $serviceId);
            })
            ->when($request->input('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->when($request->input('payment_status'), function ($query, $paymentStatus) {
                return $query->where('payment_status', $paymentStatus);
            })
            ->when($request->input('from_date'), function ($query, $fromDate) {
                return $query->where('scheduled_at', '>=', Carbon::parse($fromDate));
            })
            ->when($request->input('to_date'), function ($query, $toDate) {
                return $query->where('scheduled_at', '<=', Carbon::parse($toDate)->endOfDay());
            })
            ->when($request->input('search'), function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('booking_reference', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('client_email', 'like', "%{$search}%");
                });
            })
            ->orderBy('scheduled_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return BookingResource::collection($bookings)->additional([
            'message' => 'All bookings retrieved successfully',
            'status' => 200
        ]);
    }

    public function createBooking(Request $request)
    {
        $data = $request->validated();
        $user = $request->user();

        if (!$user->hasPermission('create_own_bookings')) {
            return $this->error('You do not have permission to create bookings.', 403);
        }

        return DB::transaction(function () use ($data, $user) {
            $service = Service::findOrFail($data['service_id']);
            if (!$service->isAvailableForBooking()) {
                throw new Exception('Service is not available for booking', 422);
            }

            $location = null;
            if (!empty($data['service_location_id'])) {
                $location = ServiceLocation::where('service_id', $service->id)
                    ->where('id', $data['service_location_id'])
                    ->where('is_active', true)
                    ->firstOrFail();
            }

            // Parse and validate booking time
            $scheduledAt = Carbon::parse($data['scheduled_at']);
            $durationMinutes = $data['duration_minutes'] ?? $service->duration_minutes;

            // Validate booking time constraints
            $timeValidationErrors = $this->timeSlotService->validateBookingTime(
                $service,
                $scheduledAt,
                $location
            );

            if (!empty($timeValidationErrors)) {
                throw new Exception(implode('. ', $timeValidationErrors), 422);
            }

            // Check slot availability
            if (!$this->timeSlotService->isSlotAvailable($service, $scheduledAt, $durationMinutes, $location)) {
                throw new Exception('Selected time slot is not available', 422);
            }

            // Calculate pricing
            $pricing = $this->calculateBookingPricing($service, $location, $data['add_ons'] ?? [], $scheduledAt);

            // Create booking
            $booking = Booking::create([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'service_location_id' => $location?->id,
                'scheduled_at' => $scheduledAt,
                'ends_at' => $scheduledAt->clone()->addMinutes($pricing['total_duration']),
                'duration_minutes' => $pricing['total_duration'],
                'base_price' => $pricing['base_price'],
                'addons_total' => $pricing['addons_total'],
                'total_amount' => $pricing['total_amount'],
                'deposit_amount' => $pricing['deposit_amount'],
                'remaining_amount' => $pricing['remaining_amount'],
                'status' => 'pending',
                'payment_status' => 'pending',
                'client_name' => $data['client_name'] ?? $user->name,
                'client_email' => $data['client_email'] ?? $user->email,
                'client_phone' => $data['client_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'special_requirements' => $data['special_requirements'] ?? null,
                'requires_consultation' => $data['requires_consultation'] ?? false,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Add booking add-ons
            if (!empty($data['add_ons'])) {
                $this->addBookingAddOns($booking, $data['add_ons']);
            }

            // Send confirmation email
            $this->emailService->sendBookingConfirmation($booking);

            // Log booking creation
            Log::info('Booking created', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'user_id' => $user->id,
                'service_id' => $service->id,
            ]);

            return $this->ok('Booking created successfully', [
                'booking' => new BookingResource($booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn']))
            ]);
        });
    }

    /**
     * Create booking as admin
     */
    public function createBookingAsAdmin(Request $request)
    {
        $user = $request->user();
        $data = $request->validated();

        // Check admin permissions
        if (!$user->hasPermission('create_bookings_for_users')) {
            return $this->error('You do not have permission to create bookings for other users.', 403);
        }

        // Find the user for whom we're creating the booking
        $targetUser = User::findOrFail($data['user_id']);

        // Temporarily set the user in the request for the createBooking method
        $request->setUserResolver(function () use ($targetUser) {
            return $targetUser;
        });

        // Override permission check for admin creation
        $targetUser->temp_permission_override = ['create_own_bookings'];

        return $this->createBooking($request);
    }

    /**
     * Get booking details
     */
    public function getBookingDetails(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check permissions - user can view own bookings or admin can view all
        if (!$user->hasPermission('view_own_bookings') && !$user->hasPermission('view_all_bookings')) {
            return $this->error('You do not have permission to view booking details.', 403);
        }

        // Ensure user can only view their own bookings unless they're admin
        if ($booking->user_id !== $user->id && !$user->hasPermission('view_all_bookings')) {
            return $this->error('You can only view your own bookings.', 403);
        }

        $booking->load([
            'service',
            'serviceLocation',
            'bookingAddOns.serviceAddOn',
            'payments',
            'rescheduledFromBooking',
            'rescheduledBookings'
        ]);

        return $this->ok('Booking details retrieved', [
            'booking' => new BookingResource($booking)
        ]);
    }

    /**
     * Get booking details (admin)
     */
    public function getBookingDetailsAdmin(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check admin permissions
        if (!$user->hasPermission('view_all_bookings')) {
            return $this->error('You do not have permission to view booking details.', 403);
        }

        $booking->load([
            'user',
            'service',
            'serviceLocation',
            'bookingAddOns.serviceAddOn',
            'payments',
            'rescheduledFromBooking',
            'rescheduledBookings'
        ]);

        return $this->ok('Booking details retrieved', [
            'booking' => new BookingResource($booking)
        ]);
    }

    public function updateBooking(Request $request, Booking $booking)
    {
        $user = $request->user();
        $data = $request->validated();

        // Check permissions
        if (!$user->hasPermission('edit_own_bookings') && !$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to update bookings.', 403);
        }

        // Ensure user can only update their own bookings unless they're admin
        if ($booking->user_id !== $user->id && !$user->hasPermission('edit_all_bookings')) {
            return $this->error('You can only update your own bookings.', 403);
        }

        // Check if booking can be modified
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            throw new Exception('Booking cannot be modified in current status', 422);
        }

        return DB::transaction(function () use ($booking, $data, $user) {
            // Update allowed fields
            $allowedFields = [
                'client_name',
                'client_email',
                'client_phone',
                'notes',
                'special_requirements'
            ];

            // Admins can update additional fields
            if ($user->hasPermission('edit_all_bookings')) {
                $allowedFields = array_merge($allowedFields, [
                    'status',
                    'payment_status',
                    'consultation_notes'
                ]);
            }

            $updateData = array_intersect_key($data, array_flip($allowedFields));

            if (!empty($updateData)) {
                $booking->update($updateData);
            }

            // Log update
            Log::info('Booking updated', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'updated_by' => $user->id,
                'updated_fields' => array_keys($updateData),
            ]);

            return $this->ok('Booking updated successfully', [
                'booking' => new BookingResource($booking->load(['service', 'serviceLocation', 'bookingAddOns.serviceAddOn']))
            ]);
        });
    }

    public function cancelBooking(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check permissions
        if (!$user->hasPermission('delete_own_bookings') && !$user->hasPermission('delete_all_bookings')) {
            return $this->error('You do not have permission to cancel bookings.', 403);
        }

        // Ensure user can only cancel their own bookings unless they're admin
        if ($booking->user_id !== $user->id && !$user->hasPermission('delete_all_bookings')) {
            return $this->error('You can only cancel your own bookings.', 403);
        }

        if (!$booking->canBeCancelled()) {
            throw new Exception('Booking cannot be cancelled', 422);
        }

        return DB::transaction(function () use ($booking, $request, $user) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->input('reason', 'Cancelled by ' . ($user->hasRole(['admin', 'super admin']) ? 'admin' : 'client')),
            ]);

            // Send cancellation email
            $this->emailService->sendBookingCancellation($booking);

            // Log cancellation
            Log::info('Booking cancelled', [
                'booking_id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'cancelled_by' => $user->id,
                'reason' => $request->input('reason'),
            ]);

            return $this->ok('Booking cancelled successfully', [
                'booking' => new BookingResource($booking)
            ]);
        });
    }

    public function confirmBooking(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check admin permissions
        if (!$user->hasPermission('edit_all_bookings')) {
            return $this->error('You do not have permission to confirm bookings.', 403);
        }

        if ($booking->status !== 'pending') {
            throw new Exception('Only pending bookings can be confirmed', 422);
        }

        $booking->update(['status' => 'confirmed']);

        // Send confirmation email
        $this->emailService->sendBookingConfirmed($booking);

        Log::info('Booking confirmed by admin', [
            'booking_id' => $booking->id,
            'admin_user_id' => $user->id,
        ]);

        return $this->ok('Booking confirmed successfully', [
            'booking' => new BookingResource($booking)
        ]);
    }

    public function deleteBooking(Request $request, Booking $booking)
    {
        $user = $request->user();

        // Check admin permissions for force deletion
        if (!$user->hasPermission('force_delete_bookings')) {
            return $this->error('You do not have permission to delete bookings.', 403);
        }

        $booking->delete();

        Log::info('Booking deleted by admin', [
            'booking_id' => $booking->id,
            'admin_user_id' => $user->id,
        ]);

        return $this->ok('Booking deleted successfully');
    }

    public function getBookingStatistics(Request $request)
    {
        $user = $request->user();

        // Check admin permissions
        if (!$user->hasPermission('view_all_bookings')) {
            return $this->error('You do not have permission to view booking statistics.', 403);
        }

        $fromDate = $request->input('from_date', now()->subDays(30));
        $toDate = $request->input('to_date', now());

        $stats = [
            'total_bookings' => Booking::whereBetween('created_at', [$fromDate, $toDate])->count(),
            'confirmed_bookings' => Booking::whereBetween('created_at', [$fromDate, $toDate])->where('status', 'confirmed')->count(),
            'completed_bookings' => Booking::whereBetween('created_at', [$fromDate, $toDate])->where('status', 'completed')->count(),
            'cancelled_bookings' => Booking::whereBetween('created_at', [$fromDate, $toDate])->where('status', 'cancelled')->count(),
            'total_revenue' => Booking::whereBetween('created_at', [$fromDate, $toDate])
                ->where('payment_status', 'fully_paid')
                ->sum('total_amount'),
            'pending_revenue' => Booking::whereBetween('created_at', [$fromDate, $toDate])
                ->whereIn('payment_status', ['pending', 'deposit_paid'])
                ->sum('total_amount'),
            'average_booking_value' => Booking::whereBetween('created_at', [$fromDate, $toDate])
                ->avg('total_amount'),
        ];

        // Status breakdown
        $statusBreakdown = Booking::whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy('status')
            ->selectRaw('status, count(*) as count')
            ->pluck('count', 'status')
            ->toArray();

        // Daily bookings trend
        $dailyTrend = Booking::whereBetween('created_at', [$fromDate, $toDate])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as date, count(*) as bookings, sum(total_amount) as revenue')
            ->orderBy('date')
            ->get();

        return $this->ok('Booking statistics retrieved', [
            'period' => [
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
            'summary' => [
                'total_bookings' => $stats['total_bookings'],
                'confirmed_bookings' => $stats['confirmed_bookings'],
                'completed_bookings' => $stats['completed_bookings'],
                'cancelled_bookings' => $stats['cancelled_bookings'],
                'total_revenue' => '£' . number_format($stats['total_revenue'] / 100, 2),
                'pending_revenue' => '£' . number_format($stats['pending_revenue'] / 100, 2),
                'average_booking_value' => '£' . number_format($stats['average_booking_value'] / 100, 2),
            ],
            'status_breakdown' => $statusBreakdown,
            'daily_trend' => $dailyTrend,
        ]);
    }

    private function calculateBookingPricing(Service $service, ?ServiceLocation $location, array $addOns, Carbon $scheduledAt): array
    {
        // Implementation remains the same
        $basePrice = $service->base_price;
        $locationCharge = $location ? $location->additional_charge : 0;

        // Find applicable availability window for time-based pricing
        $timeModifier = 0;
        $availabilityWindows = $service->availabilityWindows()
            ->active()
            ->forDate($scheduledAt)
            ->get();

        foreach ($availabilityWindows as $window) {
            if ($window->isValidForDate($scheduledAt)) {
                $timeModifier = $window->price_modifier ?? 0;
                break;
            }
        }

        // Calculate add-ons
        $addOnsTotal = 0;
        $totalAdditionalDuration = 0;
        $addOnsBreakdown = [];

        foreach ($addOns as $addOnData) {
            $addOn = ServiceAddOn::find($addOnData['service_add_on_id']);
            if ($addOn && $addOn->service_id === $service->id) {
                $quantity = min($addOnData['quantity'] ?? 1, $addOn->max_quantity);
                $addOnPrice = $addOn->calculateTotalPrice($quantity);
                $addOnDuration = $addOn->calculateTotalDuration($quantity);

                $addOnsTotal += $addOnPrice;
                $totalAdditionalDuration += $addOnDuration;

                $addOnsBreakdown[] = [
                    'id' => $addOn->id,
                    'name' => $addOn->name,
                    'quantity' => $quantity,
                    'unit_price' => $addOn->getFormattedPriceAttribute(),
                    'total_price' => '£' . number_format($addOnPrice / 100, 2),
                    'duration_minutes' => $addOnDuration,
                ];
            }
        }

        $subtotal = $basePrice + $locationCharge + $timeModifier + $addOnsTotal;
        $totalAmount = $subtotal;
        $totalDuration = $service->duration_minutes + $totalAdditionalDuration;

        // Calculate deposit if required
        $depositAmount = null;
        $remainingAmount = null;
        if ($service->requires_deposit) {
            $depositAmount = $service->getDepositAmountAttribute();
            $remainingAmount = $totalAmount - $depositAmount;
        }

        return [
            'base_price' => $basePrice,
            'location_charge' => $locationCharge,
            'time_modifier' => $timeModifier,
            'addons_total' => $addOnsTotal,
            'subtotal' => $subtotal,
            'total_amount' => $totalAmount,
            'deposit_amount' => $depositAmount,
            'remaining_amount' => $remainingAmount,
            'total_duration' => $totalDuration,
            'add_ons_breakdown' => $addOnsBreakdown,
        ];
    }

    private function addBookingAddOns(Booking $booking, array $addOns): void
    {
        foreach ($addOns as $addOnData) {
            $addOn = ServiceAddOn::find($addOnData['service_add_on_id']);
            if ($addOn && $addOn->service_id === $booking->service_id) {
                $quantity = min($addOnData['quantity'] ?? 1, $addOn->max_quantity);

                BookingAddOn::create([
                    'booking_id' => $booking->id,
                    'service_add_on_id' => $addOn->id,
                    'quantity' => $quantity,
                    'unit_price' => $addOn->price,
                    'total_price' => $addOn->calculateTotalPrice($quantity),
                    'duration_minutes' => $addOn->duration_minutes,
                ]);
            }
        }
    }

    private function getStatusColor(string $status): string
    {
        return match($status) {
            'pending' => '#FCD34D', // Yellow
            'confirmed' => '#60A5FA', // Blue
            'in_progress' => '#34D399', // Green
            'completed' => '#10B981', // Emerald
            'cancelled' => '#F87171', // Red
            'no_show' => '#EF4444', // Red
            'rescheduled' => '#F97316', // Orange
            default => '#9CA3AF' // Gray
        };
    }
}

<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceAvailabilityWindow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceAvailabilityWindowSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $services = Service::with('locations')->get();

            if ($services->isEmpty()) {
                return;
            }

            foreach ($services as $service) {
                $this->createAvailabilityForService($service);
            }
        });
    }

    private function createAvailabilityForService(Service $service): void
    {
        // Create standard weekday availability
        $this->createWeekdayAvailability($service);

        // Create weekend availability (might be different pricing/hours)
        $this->createWeekendAvailability($service);

        // Create evening availability for certain services
        if ($this->serviceSupportsEveningBookings($service)) {
            $this->createEveningAvailability($service);
        }
    }

    private function createWeekdayAvailability(Service $service): void
    {
        // Monday to Friday - Standard business hours
        for ($dayOfWeek = 1; $dayOfWeek <= 5; $dayOfWeek++) {
            // Create availability for each location
            foreach ($service->locations as $location) {
                ServiceAvailabilityWindow::create([
                    'service_id' => $service->id,
                    'service_location_id' => $location->id,
                    'type' => 'regular',
                    'pattern' => 'weekly',
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'max_bookings' => $this->getMaxBookingsForService($service, 'weekday'),
                    'slot_duration_minutes' => $service->duration_minutes,
                    'break_duration_minutes' => 15,
                    // Removed: booking_buffer_minutes, allow_overlapping_bookings, max_concurrent_bookings
                    'min_advance_booking_hours' => 24,
                    'max_advance_booking_days' => 90,
                    'is_active' => true,
                    'is_bookable' => true,
                    'price_modifier' => 0,
                    'price_modifier_type' => 'fixed',
                    'title' => 'Weekday Availability',
                    'description' => 'Standard weekday booking hours',
                ]);
            }

            // Also create location-independent availability for mobile services
            if ($this->isMobileService($service)) {
                ServiceAvailabilityWindow::create([
                    'service_id' => $service->id,
                    'service_location_id' => null, // For mobile/client location services
                    'type' => 'regular',
                    'pattern' => 'weekly',
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '10:00:00',
                    'end_time' => '18:00:00',
                    'max_bookings' => 3,
                    'slot_duration_minutes' => $service->duration_minutes,
                    'break_duration_minutes' => 30,
                    // Removed: booking_buffer_minutes, allow_overlapping_bookings, max_concurrent_bookings
                    'min_advance_booking_hours' => 48,
                    'max_advance_booking_days' => 90,
                    'is_active' => true,
                    'is_bookable' => true,
                    'price_modifier' => 2000, // £20 travel surcharge
                    'price_modifier_type' => 'fixed',
                    'title' => 'Mobile Service - Weekdays',
                    'description' => 'Mobile service availability for client locations',
                ]);
            }
        }
    }

    private function createWeekendAvailability(Service $service): void
    {
        // Saturday availability
        foreach ($service->locations as $location) {
            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'service_location_id' => $location->id,
                'type' => 'regular',
                'pattern' => 'weekly',
                'day_of_week' => 6, // Saturday
                'start_time' => '10:00:00',
                'end_time' => '16:00:00',
                'max_bookings' => $this->getMaxBookingsForService($service, 'weekend'),
                'slot_duration_minutes' => $service->duration_minutes,
                'break_duration_minutes' => 30,
                // Removed: booking_buffer_minutes, allow_overlapping_bookings, max_concurrent_bookings
                'min_advance_booking_hours' => 48,
                'max_advance_booking_days' => 90,
                'is_active' => true,
                'is_bookable' => true,
                'price_modifier' => 1500, // £15 weekend surcharge
                'price_modifier_type' => 'fixed',
                'title' => 'Saturday Availability',
                'description' => 'Weekend booking hours with premium pricing',
            ]);
        }

        // Mobile weekend availability
        if ($this->isMobileService($service)) {
            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'service_location_id' => null,
                'type' => 'regular',
                'pattern' => 'weekly',
                'day_of_week' => 6, // Saturday
                'start_time' => '09:00:00',
                'end_time' => '19:00:00',
                'max_bookings' => 4,
                'slot_duration_minutes' => $service->duration_minutes,
                'break_duration_minutes' => 45,
                // Removed: booking_buffer_minutes, allow_overlapping_bookings, max_concurrent_bookings
                'min_advance_booking_hours' => 72,
                'max_advance_booking_days' => 90,
                'is_active' => true,
                'is_bookable' => true,
                'price_modifier' => 3500, // £35 weekend + travel surcharge
                'price_modifier_type' => 'fixed',
                'title' => 'Mobile Service - Saturday',
                'description' => 'Weekend mobile service with premium pricing',
            ]);
        }

        // Sunday availability (limited)
        if ($this->serviceSupportsWeekends($service)) {
            foreach ($service->locations as $location) {
                ServiceAvailabilityWindow::create([
                    'service_id' => $service->id,
                    'service_location_id' => $location->id,
                    'type' => 'regular',
                    'pattern' => 'weekly',
                    'day_of_week' => 0, // Sunday
                    'start_time' => '12:00:00',
                    'end_time' => '16:00:00',
                    'max_bookings' => $this->getMaxBookingsForService($service, 'sunday'),
                    'slot_duration_minutes' => $service->duration_minutes,
                    'break_duration_minutes' => 30,
                    // Removed: booking_buffer_minutes, allow_overlapping_bookings, max_concurrent_bookings
                    'min_advance_booking_hours' => 72,
                    'max_advance_booking_days' => 90,
                    'is_active' => true,
                    'is_bookable' => true,
                    'price_modifier' => 2500, // £25 Sunday surcharge
                    'price_modifier_type' => 'fixed',
                    'title' => 'Sunday Availability',
                    'description' => 'Limited Sunday availability with premium pricing',
                ]);
            }
        }
    }

    private function createEveningAvailability(Service $service): void
    {
        // Tuesday and Thursday evening availability for consultation services
        foreach ([2, 4] as $dayOfWeek) { // Tuesday and Thursday
            foreach ($service->locations as $location) {
                ServiceAvailabilityWindow::create([
                    'service_id' => $service->id,
                    'service_location_id' => $location->id,
                    'type' => 'special_hours', // Changed from 'evening' to match migration enum
                    'pattern' => 'weekly',
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '18:00:00',
                    'end_time' => '20:00:00',
                    'max_bookings' => 2,
                    'slot_duration_minutes' => $service->duration_minutes,
                    'break_duration_minutes' => 15,
                    // Removed: booking_buffer_minutes, allow_overlapping_bookings, max_concurrent_bookings
                    'min_advance_booking_hours' => 24,
                    'max_advance_booking_days' => 60,
                    'is_active' => true,
                    'is_bookable' => true,
                    'price_modifier' => 1000, // £10 evening surcharge
                    'price_modifier_type' => 'fixed',
                    'title' => 'Evening Consultations',
                    'description' => 'Evening availability for consultations',
                ]);
            }
        }
    }

    private function getMaxBookingsForService(Service $service, string $period = 'weekday'): int
    {
        $serviceName = strtolower($service->name);

        // Consultation services can have more bookings per day
        if (str_contains($serviceName, 'consultation')) {
            return $period === 'weekend' ? 4 : 6; // More consultations possible
        }

        if (str_contains($serviceName, 'arch') || str_contains($serviceName, 'backdrop')) {
            return $period === 'weekend' ? 2 : 3; // Larger installations, fewer per day
        }

        if (str_contains($serviceName, 'centerpiece')) {
            return $period === 'weekend' ? 3 : 4; // Medium complexity
        }

        if (str_contains($serviceName, 'bouquet') || str_contains($serviceName, 'delivery')) {
            return $period === 'weekend' ? 8 : 12; // Quick delivery services
        }

        // Default for other services
        return $period === 'weekend' ? 3 : 5;
    }

    private function serviceSupportsEveningBookings(Service $service): bool
    {
        $serviceName = strtolower($service->name);

        // Only consultation services typically offer evening appointments
        return str_contains($serviceName, 'consultation') ||
            str_contains($serviceName, 'design');
    }

    private function serviceSupportsWeekends(Service $service): bool
    {
        $serviceName = strtolower($service->name);

        // Most services support weekends except maybe consultations
        return !str_contains($serviceName, 'consultation') ||
            str_contains($serviceName, 'emergency') ||
            str_contains($serviceName, 'wedding');
    }

    private function isMobileService(Service $service): bool
    {
        $serviceName = strtolower($service->name);

        // Check if this is a mobile service based on name or if it has mobile locations
        return str_contains($serviceName, 'mobile') ||
            str_contains($serviceName, 'delivery') ||
            $service->locations->where('type', 'client_location')->isNotEmpty();
    }
}

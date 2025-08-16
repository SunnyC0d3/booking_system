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

            foreach ($services as $service) {
                $this->createAvailabilityForService($service);
            }

            $this->command->info('Service availability windows created successfully!');
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
                    'booking_buffer_minutes' => 30,
                    'allow_overlapping_bookings' => false,
                    'max_concurrent_bookings' => 1,
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
                    'booking_buffer_minutes' => 60,
                    'allow_overlapping_bookings' => false,
                    'max_concurrent_bookings' => 1,
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
                'booking_buffer_minutes' => 45,
                'allow_overlapping_bookings' => false,
                'max_concurrent_bookings' => 1,
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
                'booking_buffer_minutes' => 60,
                'allow_overlapping_bookings' => false,
                'max_concurrent_bookings' => 1,
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
                    'booking_buffer_minutes' => 60,
                    'allow_overlapping_bookings' => false,
                    'max_concurrent_bookings' => 1,
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
                    'type' => 'evening',
                    'pattern' => 'weekly',
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '18:00:00',
                    'end_time' => '20:00:00',
                    'max_bookings' => 2,
                    'slot_duration_minutes' => $service->duration_minutes,
                    'break_duration_minutes' => 15,
                    'booking_buffer_minutes' => 15,
                    'allow_overlapping_bookings' => false,
                    'max_concurrent_bookings' => 1,
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
        return $period === 'weekend' ? 2 : 4;
    }

    private function serviceSupportsEveningBookings(Service $service): bool
    {
        $serviceName = strtolower($service->name);

        // Only consultation services support evening bookings
        return str_contains($serviceName, 'consultation') ||
            str_contains($serviceName, 'planning');
    }

    private function serviceSupportsWeekends(Service $service): bool
    {
        $serviceName = strtolower($service->name);

        // Most services support weekends except basic consultations
        return !str_contains($serviceName, 'consultation') ||
            str_contains($serviceName, 'design consultation');
    }

    private function isMobileService(Service $service): bool
    {
        $serviceName = strtolower($service->name);

        // Services that can be provided at client locations
        return str_contains($serviceName, 'arch') ||
            str_contains($serviceName, 'garland') ||
            str_contains($serviceName, 'backdrop') ||
            str_contains($serviceName, 'centerpiece') ||
            str_contains($serviceName, 'ceiling') ||
            str_contains($serviceName, 'delivery');
    }
}

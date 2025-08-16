<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceLocation;
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
            foreach ($service->locations as $location) {
                ServiceAvailabilityWindow::create([
                    'service_id' => $service->id,
                    'service_location_id' => $location->id,
                    'type' => 'regular',
                    'pattern' => 'weekly',
                    'day_of_week' => $dayOfWeek,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'max_bookings' => $this->getMaxBookingsForService($service),
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

// Also create location-independent availability
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
                'break_duration_minutes' => 20,
                'booking_buffer_minutes' => 45,
                'allow_overlapping_bookings' => false,
                'max_concurrent_bookings' => 1,
                'min_advance_booking_hours' => 48,
                'max_advance_booking_days' => 120,
                'is_active' => true,
                'is_bookable' => true,
                'price_modifier' => 1500, // £15 weekend surcharge
                'price_modifier_type' => 'fixed',
                'title' => 'Saturday Availability',
                'description' => 'Weekend booking hours with premium pricing',
            ]);
        }

// Sunday availability (limited)
        $mainLocation = $service->locations->where('is_default', true)->first();
        if ($mainLocation) {
            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'service_location_id' => $mainLocation->id,
                'type' => 'regular',
                'pattern' => 'weekly',
                'day_of_week' => 0, // Sunday
                'start_time' => '11:00:00',
                'end_time' => '15:00:00',
                'max_bookings' => 2,
                'slot_duration_minutes' => $service->duration_minutes,
                'break_duration_minutes' => 30,
                'booking_buffer_minutes' => 60,
                'allow_overlapping_bookings' => false,
                'max_concurrent_bookings' => 1,
                'min_advance_booking_hours' => 72,
                'max_advance_booking_days' => 90,
                'is_active' => true,

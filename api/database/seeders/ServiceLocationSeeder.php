<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\VenueDetails;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceLocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $locations = $this->getLocationData();

            foreach ($locations as $locationData) {
                $venueData = $locationData['venue_details'];
                unset($locationData['venue_details']);

                $location = ServiceLocation::create($locationData);

                VenueDetails::create(array_merge($venueData, [
                    'service_location_id' => $location->id,
                ]));
            }

            $this->command->info('Service locations created successfully!');
        });
    }

    private function getLocationData(): array
    {
        return [
            [
                'name' => 'Main Studio - Central London',
                'address' => '123 Creative Street',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'country' => 'United Kingdom',
                'phone' => '+44 20 7946 0958',
                'email' => 'studio@balloondesigns.co.uk',
                'is_active' => true,
                'is_bookable' => true,
                'price_modifier' => 2500, // £25 Sunday premium
                'price_modifier_type' => 'fixed',
                'title' => 'Sunday Availability',
                'description' => 'Limited Sunday hours with premium pricing',
            ]);
        }
}

private
function createEveningAvailability(Service $service): void
{
    // Evening availability for consultation services (weekdays only)
    for ($dayOfWeek = 1; $dayOfWeek <= 5; $dayOfWeek++) {
        $mainLocation = $service->locations->where('is_default', true)->first();
        if ($mainLocation) {
            ServiceAvailabilityWindow::create([
                'service_id' => $service->id,
                'service_location_id' => $mainLocation->id,
                'type' => 'regular',
                'pattern' => 'weekly',
                'day_of_week' => $dayOfWeek,
                'start_time' => '18:00:00',
                'end_time' => '20:00:00',
                'max_bookings' => 2,
                'slot_duration_minutes' => $service->duration_minutes,
                'break_duration_minutes' => 15,
                'booking_buffer_minutes' => 30,
                'allow_overlapping_bookings' => false,
                'max_concurrent_bookings' => 1,
                'min_advance_booking_hours' => 48,
                'max_advance_booking_days' => 60,
                'is_active' => true,
                'is_bookable' => true,
                'price_modifier' => 1000, // £10 evening surcharge
                'price_modifier_type' => 'fixed',
                'title' => 'Evening Consultation Hours',
                'description' => 'After-hours consultation availability',
            ]);
        }
    }
}

private
function getMaxBookingsForService(Service $service, string $period = 'weekday'): int
{
    // Determine max bookings based on service type and period
    $serviceName = strtolower($service->name);

    if (str_contains($serviceName, 'consultation')) {
        return $period === 'weekend' ? 4 : 6; // More consultations possible
    }

    if (str_contains($serviceName, 'arch') || str_contains($serviceName, 'backdrop')) {
        return $period === 'weekend' ? 2 : 3; // Larger installations, fewer per day
    }

    if (str_contains($serviceName, 'centerpiece')) {
        return $period === 'weekend' ? 3 : 4; // Medium complexity
    }

    // Default for other services
    return $period === 'weekend' ? 2 : 4;
}

private
function serviceSupportsEveningBookings(Service $service): bool
{
    $serviceName = strtolower($service->name);

    // Only consultation services support evening bookings
    return str_contains($serviceName, 'consultation') ||
        str_contains($serviceName, 'planning');
}
}

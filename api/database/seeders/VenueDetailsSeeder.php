<?php

namespace Database\Seeders;

use App\Models\ServiceLocation;
use App\Models\VenueDetails;
use Illuminate\Database\Seeder;


class VenueDetailsSeeder extends Seeder
{
    public function run(): void
    {
// This is handled in ServiceLocationSeeder, but keeping for reference
        $locations = ServiceLocation::whereDoesntHave('venueDetails')->get();

        foreach ($locations as $location) {
            VenueDetails::create([
                'service_location_id' => $location->id,
                'venue_type' => 'general',
                'setup_requirements' => 'Standard setup requirements',
                'equipment_available' => 'Basic equipment available',
                'accessibility_info' => 'Please contact for accessibility information',
                'parking_info' => 'Parking information available upon request',
                'max_capacity' => 20,
                'setup_time_minutes' => 30,
                'breakdown_time_minutes' => 20,
                'additional_fee' => 0.00,
                'amenities' => ['Basic facilities'],
                'restrictions' => ['Standard terms apply'],
            ]);
        }

        $this->command->info('Venue details created for existing locations!');
    }
}

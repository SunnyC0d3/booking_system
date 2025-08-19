<?php

namespace Database\Seeders;

use App\Models\ServiceLocation;
use App\Models\VenueDetails;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VenueDetailsSeeder extends Seeder
{
    public function run(): void
    {
        try {
            // Get all service locations that don't already have venue details
            $locationsWithoutDetails = ServiceLocation::doesntHave('venueDetails')
                ->where('is_active', true)
                ->get();

            if ($locationsWithoutDetails->isEmpty()) {
                return;
            }

            DB::transaction(function () use ($locationsWithoutDetails) {
                foreach ($locationsWithoutDetails as $location) {
                    $this->createVenueDetailsForLocation($location);
                }
            });
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function createVenueDetailsForLocation(ServiceLocation $location): void
    {
        $venueData = $this->getVenueDataByType($location);

        $venueDetails = VenueDetails::create(array_merge([
            'service_location_id' => $location->id,
        ], $venueData));
    }

    private function getVenueDataByType(ServiceLocation $location): array
    {
        $baseData = [
            'setup_time_minutes' => 60,
            'breakdown_time_minutes' => 30,
            'step_free_access' => true,
            'has_adequate_lighting' => true,
            'climate_controlled' => true,
            'photography_allowed' => true,
            'social_media_allowed' => true,
        ];

        return match ($location->type) {
            'business_premises' => array_merge($baseData, [
                'venue_type' => 'studio',
                'space_style' => 'modern',
                'ceiling_height_meters' => 3.0,
                'floor_area_sqm' => 50.0,
                'room_dimensions' => [
                    'length' => 8,
                    'width' => 6,
                    'height' => 3
                ],
                'color_scheme' => ['white', 'neutral'],
                'access_instructions' => 'Main entrance, use intercom',
                'parking_information' => 'Free parking available on-site',
                'loading_instructions' => 'Use rear entrance for equipment',
                'lift_access' => true,
                'power_outlets' => [
                    ['type' => 'standard', 'location' => 'wall_mounted', 'count' => 4],
                    ['type' => 'usb', 'location' => 'desk', 'count' => 2]
                ],
                'typical_temperature' => 20.0,
                'setup_restrictions' => ['No setup before 8am', 'No setup after 10pm'],
                'prohibited_items' => ['open flames', 'confetti', 'helium balloons near ceiling'],
                'venue_contacts' => [
                    ['role' => 'manager', 'name' => 'Studio Manager', 'phone' => '01234567890']
                ],
                'special_instructions' => 'Please sign in at reception upon arrival',
            ]),

            'client_location' => array_merge($baseData, [
                'venue_type' => 'client_home',
                'space_style' => 'mixed',
                'setup_time_minutes' => 90, // Takes longer at client location
                'breakdown_time_minutes' => 45,
                'access_instructions' => 'Contact client 30 minutes before arrival',
                'parking_information' => 'Street parking - check with client',
                'loading_instructions' => 'Use front door unless otherwise specified',
                'lift_access' => false,
                'step_free_access' => false, // Varies by location
                'power_outlets' => [
                    ['type' => 'standard', 'location' => 'various', 'count' => 'varies']
                ],
                'setup_restrictions' => ['Respect neighbors', 'No early morning setup'],
                'prohibited_items' => ['varies by location'],
                'special_instructions' => 'Confirm access and setup requirements with client beforehand',
            ]),

            'outdoor' => array_merge($baseData, [
                'venue_type' => 'outdoor',
                'space_style' => 'garden',
                'ceiling_height_meters' => null, // No ceiling outdoors
                'climate_controlled' => false,
                'access_instructions' => 'Weather dependent - have backup plan',
                'parking_information' => 'Varies by location',
                'loading_instructions' => 'Weather protection required for equipment',
                'lift_access' => false,
                'power_outlets' => [
                    ['type' => 'outdoor_rated', 'location' => 'limited', 'count' => 'varies']
                ],
                'setup_restrictions' => [
                    'Weather dependent',
                    'No setup in high winds',
                    'Rain contingency required'
                ],
                'prohibited_items' => ['items not weather resistant'],
                'special_instructions' => 'Always have indoor backup plan ready',
            ]),

            'virtual' => array_merge($baseData, [
                'venue_type' => 'studio',
                'space_style' => 'modern',
                'ceiling_height_meters' => 3.0,
                'floor_area_sqm' => 20.0,
                'room_dimensions' => [
                    'length' => 5,
                    'width' => 4,
                    'height' => 3
                ],
                'color_scheme' => ['white', 'blue'],
                'access_instructions' => 'Virtual session - no physical access required',
                'parking_information' => 'Not applicable',
                'loading_instructions' => 'Not applicable',
                'lift_access' => true,
                'has_adequate_lighting' => true,
                'lighting_notes' => 'Professional lighting setup for video calls',
                'power_outlets' => [
                    ['type' => 'standard', 'location' => 'desk', 'count' => 4],
                    ['type' => 'usb', 'location' => 'desk', 'count' => 4]
                ],
                'setup_restrictions' => ['Ensure good internet connection', 'Test equipment beforehand'],
                'special_instructions' => 'Technical check 15 minutes before session',
            ]),

            default => $baseData
        };
    }
}

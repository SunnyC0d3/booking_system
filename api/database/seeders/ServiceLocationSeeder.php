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
            // Get the first service to associate locations with
            $service = Service::first();

            if (!$service) {
                return;
            }

            $locations = $this->getLocationData($service->id);

            foreach ($locations as $locationData) {
                $venueData = $locationData['venue_details'];
                unset($locationData['venue_details']);

                $location = ServiceLocation::create($locationData);

                VenueDetails::create(array_merge($venueData, [
                    'service_location_id' => $location->id,
                ]));
            }
        });
    }

    private function getLocationData(int $serviceId): array
    {
        return [
            [
                'service_id' => $serviceId, // Required field
                'name' => 'Main Studio - Balloon Design Workshop',
                'description' => 'Our primary design studio featuring spacious work areas, consultation rooms, and display galleries for balloon arrangements.',
                'type' => 'business_premises', // Match migration enum values
                'address_line_1' => '42 Creative Quarter',
                'address_line_2' => 'Design District',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'E1 6QL',
                'country' => 'United Kingdom',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'max_capacity' => 25,
                'travel_time_minutes' => 0,
                'additional_charge' => 0, // In pence
                'is_active' => true,
                'availability_notes' => ['Open Monday-Saturday', 'Evening consultations available by appointment'],
                'virtual_platform' => null,
                'virtual_instructions' => null,
                'equipment_available' => ['Professional balloon equipment', 'Design tools', 'Consultation areas'],
                'facilities' => ['Parking', 'Wheelchair accessible', 'Client meeting room', 'Display area'],
                'venue_details' => [
                    // Match the actual VenueDetails migration fields
                    'venue_type' => 'studio',
                    'space_style' => 'modern',
                    'ceiling_height_meters' => 3.5,
                    'floor_area_sqm' => 150.0,
                    'room_dimensions' => [
                        'main_studio' => ['length' => 12, 'width' => 10, 'height' => 3.5],
                        'consultation_room' => ['length' => 4, 'width' => 3, 'height' => 3.5],
                    ],
                    'color_scheme' => ['white', 'light_grey', 'accent_blue'],
                    'access_instructions' => 'Enter through main entrance, studio is on ground floor. Keypad code provided before visit.',
                    'parking_information' => 'Free on-site parking for 8 vehicles, additional street parking available',
                    'loading_instructions' => 'Loading bay accessible from rear entrance, suitable for equipment delivery',
                    'lift_access' => false,
                    'step_free_access' => true,
                    'stairs_count' => 0,
                    'power_outlets' => [
                        'main_area' => ['type' => '13A UK', 'quantity' => 8],
                        'consultation_room' => ['type' => '13A UK', 'quantity' => 4],
                    ],
                    'has_adequate_lighting' => true,
                    'lighting_notes' => 'Natural lighting supplemented with professional LED track lighting',
                    'climate_controlled' => true,
                    'typical_temperature' => 21.0,
                    'setup_restrictions' => ['No setup during client consultations', 'Equipment setup only in designated areas'],
                    'setup_time_minutes' => 30,
                    'breakdown_time_minutes' => 20,
                    'noise_restrictions' => 'Quiet work only after 6pm weekdays, none on weekends',
                    'prohibited_items' => ['open_flames', 'confetti', 'liquids_near_equipment'],
                    'venue_contacts' => [
                        'manager' => ['name' => 'Sarah Johnson', 'phone' => '+44 20 7946 0959'],
                        'emergency' => ['contact' => '+44 7700 900123'],
                    ],
                    'special_instructions' => 'Please arrive 10 minutes early for consultations. Parking available on-site.',
                    'photography_allowed' => true,
                    'photography_restrictions' => 'No flash photography near balloons, client permission required',
                    'social_media_allowed' => true,
                ],
            ],
            [
                'service_id' => $serviceId,
                'name' => 'Consultation Suite - West London',
                'description' => 'Elegant consultation space designed for client meetings, planning sessions, and design reviews.',
                'type' => 'business_premises',
                'address_line_1' => '156 Kings Road',
                'address_line_2' => 'Chelsea',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'SW3 4UT',
                'country' => 'United Kingdom',
                'latitude' => 51.4894,
                'longitude' => -0.1675,
                'max_capacity' => 12,
                'travel_time_minutes' => 15,
                'additional_charge' => 1500, // £15 in pence
                'is_active' => true,
                'availability_notes' => ['Consultation appointments Monday-Friday 9am-6pm', 'Saturday by arrangement'],
                'virtual_platform' => 'Zoom',
                'virtual_instructions' => 'Virtual consultations available via Zoom. Link provided 24 hours before appointment.',
                'equipment_available' => ['Presentation screens', 'Sample displays', 'Design software'],
                'facilities' => ['Meeting rooms', 'Refreshments', 'WiFi', 'Presentation equipment'],
                'venue_details' => [
                    'venue_type' => 'corporate',
                    'space_style' => 'modern',
                    'ceiling_height_meters' => 2.8,
                    'floor_area_sqm' => 45.0,
                    'room_dimensions' => [
                        'main_consultation' => ['length' => 8, 'width' => 6, 'height' => 2.8],
                    ],
                    'color_scheme' => ['cream', 'gold', 'deep_blue'],
                    'access_instructions' => 'Ground floor suite, ring bell for entry during consultation hours',
                    'parking_information' => 'Limited on-street parking (paid), recommend public transport',
                    'loading_instructions' => 'Street access only, no dedicated loading area',
                    'lift_access' => false,
                    'step_free_access' => true,
                    'stairs_count' => 0,
                    'power_outlets' => [
                        'consultation_room' => ['type' => '13A UK', 'quantity' => 6],
                    ],
                    'has_adequate_lighting' => true,
                    'lighting_notes' => 'Professional meeting room lighting with dimmer controls',
                    'climate_controlled' => true,
                    'typical_temperature' => 22.0,
                    'setup_restrictions' => ['Consultations only', 'No equipment storage'],
                    'setup_time_minutes' => 15,
                    'breakdown_time_minutes' => 10,
                    'noise_restrictions' => 'Professional consultation environment, maintain quiet atmosphere',
                    'prohibited_items' => ['food', 'large_equipment'],
                    'venue_contacts' => [
                        'consultant' => ['name' => 'Emma Thompson', 'phone' => '+44 20 7946 0961'],
                    ],
                    'special_instructions' => 'Bring inspiration photos and venue details for best consultation experience.',
                    'photography_allowed' => true,
                    'photography_restrictions' => 'Client work only, no venue photography without permission',
                    'social_media_allowed' => false,
                ],
            ],
            [
                'service_id' => $serviceId,
                'name' => 'Mobile Service - Greater London',
                'description' => 'Mobile balloon decoration service covering all of Greater London and surrounding areas.',
                'type' => 'client_location',
                'address_line_1' => 'Various Locations',
                'address_line_2' => 'Greater London Area',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'MOBILE',
                'country' => 'United Kingdom',
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'max_capacity' => 500, // Large event capacity
                'travel_time_minutes' => 30, // Average travel time
                'additional_charge' => 2500, // £25 base travel charge in pence
                'is_active' => true,
                'availability_notes' => ['Available throughout Greater London', 'Advance booking required', 'Travel charges apply'],
                'virtual_platform' => null,
                'virtual_instructions' => null,
                'equipment_available' => ['Mobile equipment van', 'All necessary balloon decoration tools'],
                'facilities' => ['Mobile equipment', 'Professional team', 'Flexible setup'],
                'venue_details' => [
                    'venue_type' => 'client_home',
                    'space_style' => null, // Varies by client location
                    'ceiling_height_meters' => null, // Varies by venue
                    'floor_area_sqm' => null, // Varies by venue
                    'room_dimensions' => null,
                    'color_scheme' => null,
                    'access_instructions' => 'Client to provide access details and arrangements prior to service date',
                    'parking_information' => 'Client must arrange parking space for service vehicle near venue entrance',
                    'loading_instructions' => 'Client to ensure clear access path for equipment delivery',
                    'lift_access' => false, // Assumed false for mobile service, assessed during consultation
                    'step_free_access' => false, // Assessed during consultation, assume false for safety
                    'stairs_count' => 0, // Unknown, default to 0
                    'power_outlets' => null, // Client venue dependent
                    'has_adequate_lighting' => false, // Assume false, team brings lighting
                    'lighting_notes' => 'Team brings portable lighting if needed',
                    'climate_controlled' => false, // Assume false for client locations
                    'typical_temperature' => null,
                    'setup_restrictions' => ['Client venue rules apply', 'Setup time must be coordinated with venue'],
                    'setup_time_minutes' => 60,
                    'breakdown_time_minutes' => 45,
                    'noise_restrictions' => 'Respectful of venue and neighbors, standard working hours preferred',
                    'prohibited_items' => ['varies_by_venue'], // Venue dependent, assessed during consultation
                    'venue_contacts' => [
                        'mobile_team' => ['coordinator' => 'Mobile Team', 'phone' => '+44 20 7946 0963'],
                        'emergency' => ['contact' => '+44 7700 900124'],
                    ],
                    'special_instructions' => 'Please ensure venue access is available and parking space reserved for service vehicle.',
                    'photography_allowed' => true,
                    'photography_restrictions' => 'Client permission required, venue rules apply',
                    'social_media_allowed' => true,
                ],
            ],
        ];
    }
}

<?php

namespace Database\Seeders;

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
                'name' => 'Main Studio - Balloon Design Workshop',
                'description' => 'Our primary design studio featuring spacious work areas, consultation rooms, and display galleries for balloon arrangements.',
                'type' => 'studio',
                'address_line_1' => '42 Creative Quarter',
                'address_line_2' => 'Design District',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'E1 6QL',
                'country' => 'United Kingdom',
                'phone' => '+44 20 7946 0958',
                'email' => 'studio@balloondesigns.co.uk',
                'is_active' => true,
                'is_default' => true,
                'max_capacity' => 25,
                'travel_time_minutes' => 0,
                'additional_charge' => 0,
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'virtual_platform' => null,
                'virtual_instructions' => null,
                'equipment_available' => 'Professional balloon equipment, design tools, consultation areas',
                'facilities' => ['Parking', 'Wheelchair accessible', 'Client meeting room', 'Display area'],
                'availability_notes' => 'Open Monday-Saturday, evening consultations available by appointment',
                'metadata' => [
                    'studio_type' => 'balloon_design',
                    'has_display_gallery' => true,
                    'consultation_rooms' => 2,
                    'work_stations' => 4,
                    'contact_person' => 'Sarah Johnson - Studio Manager',
                ],
                'venue_details' => [
                    'venue_type' => 'studio',
                    'setup_requirements' => 'Professional balloon design studio with climate control and adequate workspace for balloon preparation and assembly.',
                    'equipment_available' => 'Helium tanks, balloon pumps, ribbon cutting station, design tools, consultation furniture, display stands',
                    'accessibility_info' => 'Fully wheelchair accessible with accessible parking, toilets, and meeting areas',
                    'parking_info' => 'Free on-site parking for 8 vehicles, additional street parking available',
                    'catering_options' => 'Tea, coffee, and light refreshments available for consultations. External catering can be arranged for larger groups.',
                    'max_capacity' => 25,
                    'setup_time_minutes' => 30,
                    'breakdown_time_minutes' => 20,
                    'additional_fee' => 0.00,
                    'amenities' => ['Climate control', 'Natural lighting', 'Professional equipment', 'Client consultation area', 'Display gallery'],
                    'restrictions' => ['No food or drink near balloon equipment', 'Maximum 25 people for safety'],
                    'contact_info' => [
                        'manager' => 'Sarah Johnson',
                        'direct_phone' => '+44 20 7946 0959',
                        'emergency_contact' => '+44 7700 900123',
                    ],
                    'operating_hours' => [
                        'monday' => ['09:00', '18:00'],
                        'tuesday' => ['09:00', '18:00'],
                        'wednesday' => ['09:00', '18:00'],
                        'thursday' => ['09:00', '18:00'],
                        'friday' => ['09:00', '18:00'],
                        'saturday' => ['10:00', '16:00'],
                        'sunday' => 'closed',
                    ],
                    'cancellation_policy' => 'Standard cancellation policy applies. 48 hours notice required.',
                    'special_instructions' => 'Please arrive 10 minutes early for consultations. Parking available on-site.',
                ],
            ],
            [
                'name' => 'Consultation Suite - West London',
                'description' => 'Elegant consultation space designed for client meetings, planning sessions, and design reviews.',
                'type' => 'consultation',
                'address_line_1' => '156 Kings Road',
                'address_line_2' => 'Chelsea',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'SW3 4UT',
                'country' => 'United Kingdom',
                'phone' => '+44 20 7946 0960',
                'email' => 'consultations@balloondesigns.co.uk',
                'is_active' => true,
                'is_default' => false,
                'max_capacity' => 12,
                'travel_time_minutes' => 15,
                'additional_charge' => 1500, // £15
                'latitude' => 51.4894,
                'longitude' => -0.1675,
                'virtual_platform' => 'Zoom',
                'virtual_instructions' => 'Virtual consultations available via Zoom. Link provided 24 hours before appointment.',
                'equipment_available' => 'Presentation screens, sample displays, design software',
                'facilities' => ['Meeting rooms', 'Refreshments', 'WiFi', 'Presentation equipment'],
                'availability_notes' => 'Consultation appointments Monday-Friday 9am-6pm, Saturday by arrangement',
                'metadata' => [
                    'consultation_type' => 'premium',
                    'virtual_capable' => true,
                    'meeting_rooms' => 3,
                    'contact_person' => 'Emma Thompson - Senior Consultant',
                ],
                'venue_details' => [
                    'venue_type' => 'office',
                    'setup_requirements' => 'Professional consultation environment with presentation capabilities and sample displays.',
                    'equipment_available' => 'Large presentation screen, design software, sample balloon displays, comfortable seating for up to 12',
                    'accessibility_info' => 'Ground floor access, accessible toilets, hearing loop available in main consultation room',
                    'parking_info' => 'Limited on-street parking (paid), recommended to use public transport or arrange collection',
                    'catering_options' => 'Complimentary tea, coffee, and biscuits. Local catering partners available for larger meetings.',
                    'max_capacity' => 12,
                    'setup_time_minutes' => 15,
                    'breakdown_time_minutes' => 10,
                    'additional_fee' => 15.00,
                    'amenities' => ['Presentation equipment', 'Sample displays', 'Comfortable seating', 'Natural lighting', 'Air conditioning'],
                    'restrictions' => ['No smoking', 'Keep noise levels appropriate for consultations'],
                    'contact_info' => [
                        'consultant' => 'Emma Thompson',
                        'direct_phone' => '+44 20 7946 0961',
                        'email' => 'emma@balloondesigns.co.uk',
                    ],
                    'operating_hours' => [
                        'monday' => ['09:00', '18:00'],
                        'tuesday' => ['09:00', '18:00'],
                        'wednesday' => ['09:00', '18:00'],
                        'thursday' => ['09:00', '18:00'],
                        'friday' => ['09:00', '18:00'],
                        'saturday' => ['10:00', '14:00'],
                        'sunday' => 'closed',
                    ],
                    'cancellation_policy' => '24 hours notice required for consultation changes.',
                    'special_instructions' => 'Bring inspiration photos and venue details for best consultation experience.',
                ],
            ],
            [
                'name' => 'Mobile Service - Greater London',
                'description' => 'Mobile balloon decoration service covering all of Greater London and surrounding areas.',
                'type' => 'mobile',
                'address_line_1' => 'Various Locations',
                'address_line_2' => 'Greater London Area',
                'city' => 'London',
                'county' => 'Greater London',
                'postcode' => 'MOBILE',
                'country' => 'United Kingdom',
                'phone' => '+44 20 7946 0962',
                'email' => 'mobile@balloondesigns.co.uk',
                'is_active' => true,
                'is_default' => false,
                'max_capacity' => 500, // Large event capacity
                'travel_time_minutes' => null, // Varies by location
                'additional_charge' => 2500, // £25 base travel charge
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'virtual_platform' => null,
                'virtual_instructions' => null,
                'equipment_available' => 'Mobile equipment van with all necessary balloon decoration tools',
                'facilities' => ['Mobile equipment', 'Professional team', 'Flexible setup'],
                'availability_notes' => 'Available throughout Greater London, advance booking required, travel charges apply',
                'metadata' => [
                    'service_type' => 'mobile',
                    'coverage_area' => 'Greater London (within M25)',
                    'team_size' => '2-4 decorators',
                    'contact_person' => 'Mobile Team Coordinator',
                ],
                'venue_details' => [
                    'venue_type' => 'client_location',
                    'setup_requirements' => 'Client venue must provide adequate space, access, and basic facilities. Setup requirements vary by decoration type.',
                    'equipment_available' => 'Fully equipped mobile unit with helium tanks, balloon supplies, tools, and setup equipment',
                    'accessibility_info' => 'Accessibility depends on client venue. Team will assess during consultation.',
                    'parking_info' => 'Client must arrange parking space for service vehicle near venue entrance',
                    'catering_options' => 'Not applicable - service provided at client location',
                    'max_capacity' => 500,
                    'setup_time_minutes' => 60,
                    'breakdown_time_minutes' => 45,
                    'additional_fee' => 25.00,
                    'amenities' => ['Professional mobile equipment', 'Experienced team', 'Flexible service'],
                    'restrictions' => [
                        'Minimum 2-hour booking required',
                        'Additional charges for locations outside M25',
                        'Setup space must be available 1 hour before event',
                        'Client responsible for venue permissions'
                    ],
                    'contact_info' => [
                        'coordinator' => 'Mobile Team',
                        'dispatch_phone' => '+44 20 7946 0963',
                        'emergency_contact' => '+44 7700 900124',
                    ],
                    'operating_hours' => [
                        'monday' => ['08:00', '20:00'],
                        'tuesday' => ['08:00', '20:00'],
                        'wednesday' => ['08:00', '20:00'],
                        'thursday' => ['08:00', '20:00'],
                        'friday' => ['08:00', '22:00'],
                        'saturday' => ['08:00', '22:00'],
                        'sunday' => ['10:00', '18:00'],
                    ],
                    'cancellation_policy' => '48 hours notice required. Cancellations within 24 hours subject to 50% charge.',
                    'special_instructions' => 'Please ensure venue access is available and parking space reserved for service vehicle.',
                ],
            ],
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\VenueDetails;
use App\Models\ServiceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

class VenueDetailsFactory extends Factory
{
    protected $model = VenueDetails::class;

    public function definition(): array
    {
        $venueType = $this->faker->randomElement([
            'indoor', 'outdoor', 'mixed', 'studio', 'client_home', 'corporate', 'public_space'
        ]);

        $spaceStyle = $this->faker->randomElement([
            'modern', 'traditional', 'rustic', 'industrial', 'garden', 'ballroom', 'casual', 'formal'
        ]);

        return [
            'service_location_id' => ServiceLocation::factory(),

            // Main venue characteristics
            'venue_type' => $venueType,
            'space_style' => $spaceStyle,

            // Physical specifications
            'ceiling_height_meters' => $this->faker->randomFloat(2, 2.4, 5.0),
            'floor_area_sqm' => $this->faker->randomFloat(2, 20, 500),
            'room_dimensions' => $this->getRoomDimensions(),
            'color_scheme' => $this->getColorScheme(),

            // Access and logistics
            'access_instructions' => $this->getAccessInstructions($venueType),
            'parking_information' => $this->getParkingInfo(),
            'loading_instructions' => $this->getLoadingInstructions($venueType),
            'lift_access' => $this->faker->boolean(30),
            'step_free_access' => $this->faker->boolean(70),
            'stairs_count' => $this->faker->numberBetween(0, 3),

            // Utilities and power
            'power_outlets' => $this->getPowerOutlets(),
            'has_adequate_lighting' => $this->faker->boolean(85),
            'lighting_notes' => $this->getLightingNotes(),
            'climate_controlled' => $this->faker->boolean(60),
            'typical_temperature' => $this->faker->randomFloat(1, 18.0, 24.0),

            // Setup considerations
            'setup_restrictions' => $this->getSetupRestrictions($venueType),
            'setup_time_minutes' => $this->faker->numberBetween(15, 120),
            'breakdown_time_minutes' => $this->faker->numberBetween(10, 60),
            'noise_restrictions' => $this->getNoiseRestrictions($venueType),
            'prohibited_items' => $this->getProhibitedItems($venueType),

            // Contacts and instructions
            'venue_contacts' => $this->getVenueContacts(),
            'special_instructions' => $this->getSpecialInstructions($venueType),

            // Photo/event restrictions
            'photography_allowed' => $this->faker->boolean(90),
            'photography_restrictions' => $this->getPhotographyRestrictions(),
            'social_media_allowed' => $this->faker->boolean(85),
        ];
    }

    // State methods for specific venue types
    public function studio(): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_type' => 'studio',
            'space_style' => 'modern',
            'ceiling_height_meters' => $this->faker->randomFloat(2, 3.0, 4.5),
            'floor_area_sqm' => $this->faker->randomFloat(2, 80, 200),
            'climate_controlled' => true,
            'has_adequate_lighting' => true,
            'lighting_notes' => 'Professional studio lighting with adjustable tracks and LED panels',
            'setup_restrictions' => ['No setup during client sessions', 'Equipment must be stored in designated areas'],
            'prohibited_items' => ['open_flames', 'liquids_near_equipment', 'outside_food'],
        ]);
    }

    public function corporate(): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_type' => 'corporate',
            'space_style' => 'modern',
            'climate_controlled' => true,
            'lift_access' => $this->faker->boolean(80),
            'step_free_access' => true,
            'photography_allowed' => true,
            'photography_restrictions' => 'Business appropriate content only, no confidential areas',
            'social_media_allowed' => false,
        ]);
    }

    public function clientHome(): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_type' => 'client_home',
            'space_style' => $this->faker->randomElement(['traditional', 'modern', 'casual']),
            'setup_restrictions' => ['Respect homeowner property', 'Remove shoes if requested', 'Parking coordination required'],
            'prohibited_items' => ['damage_risk_items', 'excessive_noise_equipment'],
            'venue_contacts' => [
                'homeowner' => ['name' => 'Property Owner', 'phone' => $this->faker->phoneNumber()],
            ],
        ]);
    }

    // Helper methods for generating realistic data
    private function getRoomDimensions(): array
    {
        $rooms = [];
        $roomTypes = ['main_area', 'reception', 'prep_area', 'storage'];

        $numRooms = $this->faker->numberBetween(1, 3);
        for ($i = 0; $i < $numRooms; $i++) {
            $roomType = $roomTypes[$i] ?? 'additional_space_' . ($i + 1);
            $rooms[$roomType] = [
                'length' => $this->faker->randomFloat(1, 3.0, 15.0),
                'width' => $this->faker->randomFloat(1, 3.0, 12.0),
                'height' => $this->faker->randomFloat(1, 2.4, 4.5),
            ];
        }

        return $rooms;
    }

    private function getColorScheme(): array
    {
        $colorOptions = [
            ['white', 'cream', 'light_grey'],
            ['beige', 'brown', 'gold'],
            ['blue', 'white', 'silver'],
            ['green', 'cream', 'brown'],
            ['pink', 'white', 'gold'],
            ['purple', 'silver', 'white'],
            ['red', 'gold', 'cream'],
            ['black', 'white', 'grey'],
        ];

        return $this->faker->randomElement($colorOptions);
    }

    private function getAccessInstructions(string $venueType): string
    {
        $instructions = [
            'studio' => 'Enter through main entrance, studio access on ground floor. Keypad code provided.',
            'corporate' => 'Check in at reception, visitor badges required. Escort to venue area.',
            'client_home' => 'Ring doorbell, homeowner will provide access. Please be respectful of property.',
            'indoor' => 'Main entrance access, follow signage to event area.',
            'outdoor' => 'Gate access provided, follow path markings to setup area.',
            'mixed' => 'Multiple access points available, main entrance recommended for equipment.',
            'public_space' => 'Public access, coordinate with local authorities if required.',
        ];

        return $instructions[$venueType] ?? 'Standard access procedures apply, contact for details.';
    }

    private function getParkingInfo(): string
    {
        $options = [
            'Free on-site parking for up to 10 vehicles',
            'Limited street parking, arrive early or use public transport',
            'Paid parking available, £5 per session',
            'Free parking with advance booking required',
            'Valet parking service available for premium events',
            'No on-site parking, nearest car park 5 minutes walk',
            'Loading zone available for 30 minutes, then relocate',
        ];

        return $this->faker->randomElement($options);
    }

    private function getLoadingInstructions(string $venueType): string
    {
        $instructions = [
            'studio' => 'Rear entrance loading bay, suitable for equipment trolleys and cases.',
            'corporate' => 'Loading dock access via security, coordinate with facilities team.',
            'client_home' => 'Front door access only, homeowner to assist with access.',
            'indoor' => 'Loading area at main entrance, 15-minute loading window.',
            'outdoor' => 'Vehicle access to within 50m of setup area.',
            'mixed' => 'Multiple loading options available depending on event area.',
            'public_space' => 'Temporary loading permitted, follow local parking regulations.',
        ];

        return $instructions[$venueType] ?? 'Standard loading procedures, coordinate in advance.';
    }

    private function getPowerOutlets(): array
    {
        return [
            'main_area' => [
                'type' => '13A UK standard',
                'quantity' => $this->faker->numberBetween(4, 12),
                'location' => 'Wall mounted and floor boxes'
            ],
            'additional_areas' => [
                'type' => '13A UK standard',
                'quantity' => $this->faker->numberBetween(2, 6),
                'location' => 'Wall mounted'
            ],
        ];
    }

    private function getLightingNotes(): string
    {
        $options = [
            'Natural lighting supplemented with LED track lighting',
            'Professional event lighting system with dimmer controls',
            'Basic overhead lighting, additional lighting may be required',
            'Large windows provide excellent natural light during day',
            'Adjustable lighting zones for different areas and moods',
            'Chandelier and accent lighting create elegant atmosphere',
        ];

        return $this->faker->randomElement($options);
    }

    private function getSetupRestrictions(string $venueType): array
    {
        $baseRestrictions = ['Advance coordination required'];

        $specific = [
            'studio' => ['No setup during client sessions', 'Equipment storage in designated areas only'],
            'corporate' => ['Business hours setup only', 'Security clearance required'],
            'client_home' => ['Homeowner approval for setup plan', 'Respect property and neighbors'],
            'indoor' => ['Weather protection not required', 'Consider noise levels'],
            'outdoor' => ['Weather contingency plan required', 'Ground protection needed'],
            'mixed' => ['Coordinate indoor/outdoor elements', 'Weather backup plan'],
            'public_space' => ['Permits may be required', 'Public access considerations'],
        ];

        return array_merge($baseRestrictions, $specific[$venueType] ?? []);
    }

    private function getNoiseRestrictions(string $venueType): string
    {
        $restrictions = [
            'studio' => 'Quiet setup during business hours, no loud equipment after 6pm',
            'corporate' => 'Business appropriate noise levels, no disruption to offices',
            'client_home' => 'Respectful of neighbors, avoid early morning or late evening noise',
            'indoor' => 'Standard venue noise policies apply',
            'outdoor' => 'Local noise ordinances apply, typically quiet after 8pm',
            'mixed' => 'Indoor and outdoor noise restrictions both apply',
            'public_space' => 'Public space noise regulations apply',
        ];

        return $restrictions[$venueType] ?? 'Standard noise restrictions apply';
    }

    private function getProhibitedItems(string $venueType): array
    {
        $baseProhibited = ['illegal_substances', 'weapons'];

        $specific = [
            'studio' => ['open_flames', 'liquids_near_equipment', 'excessive_confetti'],
            'corporate' => ['inappropriate_content', 'alcohol_without_permission'],
            'client_home' => ['anything_damaging_property', 'items_upsetting_neighbors'],
            'indoor' => ['open_flames_without_permission', 'messy_materials'],
            'outdoor' => ['items_harmful_to_environment', 'excessive_waste'],
            'mixed' => ['weather_sensitive_items_outdoors'],
            'public_space' => ['items_violating_public_use', 'permanent_installations'],
        ];

        return array_merge($baseProhibited, $specific[$venueType] ?? []);
    }

    private function getVenueContacts(): array
    {
        return [
            'manager' => [
                'name' => $this->faker->name(),
                'phone' => $this->faker->phoneNumber(),
                'email' => $this->faker->email(),
            ],
            'emergency' => [
                'contact' => $this->faker->phoneNumber(),
            ],
            'facilities' => [
                'name' => $this->faker->name(),
                'phone' => $this->faker->phoneNumber(),
            ],
        ];
    }

    private function getSpecialInstructions(string $venueType): string
    {
        $instructions = [
            'studio' => 'Arrive 15 minutes early for equipment briefing and safety overview.',
            'corporate' => 'Check in at reception and wait for escort to venue area.',
            'client_home' => 'Please remove shoes if requested and be respectful of family property.',
            'indoor' => 'Follow venue guidelines and emergency procedures posted at entrance.',
            'outdoor' => 'Bring weather-appropriate clothing and backup plans for equipment.',
            'mixed' => 'Coordinate between indoor and outdoor setups, weather contingency ready.',
            'public_space' => 'Respect public use of space and follow all local regulations.',
        ];

        return $instructions[$venueType] ?? 'Follow all venue guidelines and contact us with questions.';
    }

    private function getPhotographyRestrictions(): string
    {
        $options = [
            'No flash photography near decorative elements, client permission required for social media',
            'Photography allowed during event only, no venue documentation without permission',
            'Professional photography welcome, coordinate with venue for any restrictions',
            'Photography allowed but be respectful of other guests and venue property',
            'No photography in restricted areas, follow venue staff guidance',
            'Social media friendly, tag venue if sharing photos online',
        ];

        return $this->faker->randomElement($options);
    }
}

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
            'studio', 'hall', 'garden', 'ballroom', 'restaurant', 'hotel',
            'church', 'outdoor', 'home', 'client_location', 'office',
            'warehouse', 'general'
        ]);

        return [
            'service_location_id' => ServiceLocation::factory(),
            'venue_type' => $venueType,
            'setup_requirements' => $this->getSetupRequirements($venueType),
            'equipment_available' => $this->getEquipmentAvailable($venueType),
            'accessibility_info' => $this->getAccessibilityInfo(),
            'parking_info' => $this->getParkingInfo(),
            'catering_options' => $this->getCateringOptions($venueType),
            'max_capacity' => $this->faker->numberBetween(10, 500),
            'setup_time_minutes' => $this->faker->numberBetween(15, 120),
            'breakdown_time_minutes' => $this->faker->numberBetween(10, 60),
            'additional_fee' => $this->faker->randomFloat(2, 0, 100),
            'amenities' => $this->getAmenities($venueType),
            'restrictions' => $this->getRestrictions($venueType),
            'contact_info' => [
                'manager' => $this->faker->name(),
                'phone' => $this->faker->phoneNumber(),
                'email' => $this->faker->email(),
                'emergency_contact' => $this->faker->phoneNumber(),
            ],
            'operating_hours' => $this->getOperatingHours(),
            'cancellation_policy' => $this->getCancellationPolicy(),
            'special_instructions' => $this->getSpecialInstructions($venueType),
            'metadata' => [
                'created_by' => 'factory',
                'venue_rating' => $this->faker->randomFloat(1, 3.0, 5.0),
                'last_inspection' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            ],
        ];
    }

    public function studio(): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_type' => 'studio',
            'setup_requirements' => 'Professional photography studio with controlled lighting and backdrop systems.',
            'equipment_available' => 'Professional lighting, backdrop stands, balloon equipment, air conditioning',
            'max_capacity' => $this->faker->numberBetween(15, 50),
            'amenities' => ['Professional lighting', 'Backdrop systems', 'Climate control', 'Equipment storage'],
        ]);
    }

    public function hall(): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_type' => 'hall',
            'setup_requirements' => 'Large event hall suitable for weddings and corporate events.',
            'equipment_available' => 'Sound system, stage area, tables and chairs, basic lighting',
            'max_capacity' => $this->faker->numberBetween(100, 500),
            'amenities' => ['Sound system', 'Stage area', 'Kitchen facilities', 'Dance floor'],
        ]);
    }

    public function clientLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_type' => 'client_location',
            'setup_requirements' => 'Setup at client-specified location. Requirements vary by venue.',
            'equipment_available' => 'Mobile equipment brought by service team',
            'max_capacity' => 999, // Depends on client venue
            'additional_fee' => $this->faker->randomFloat(2, 20, 50),
            'amenities' => ['Mobile service', 'Flexible setup'],
            'restrictions' => [
                'Client must provide adequate space',
                'Parking space required for service vehicle',
                'Setup area must be accessible',
            ],
        ]);
    }

    private function getSetupRequirements(string $venueType): string
    {
        $requirements = [
            'studio' => 'Professional design studio with adequate workspace and equipment storage.',
            'hall' => 'Large event space with high ceilings and open floor plan.',
            'garden' => 'Outdoor space with weather protection and electrical access.',
            'ballroom' => 'Elegant ballroom with dance floor and staging area.',
            'restaurant' => 'Restaurant space with table setup and service access.',
            'hotel' => 'Hotel event space with professional amenities.',
            'church' => 'Religious venue with ceremonial space and decorative guidelines.',
            'outdoor' => 'Outdoor venue with weather contingency and power access.',
            'home' => 'Private residence with suitable space for decoration setup.',
            'client_location' => 'Client-specified venue with variable requirements.',
            'office' => 'Corporate office space with professional environment.',
            'warehouse' => 'Industrial space with high ceilings and open layout.',
            'general' => 'General venue space with basic facility requirements.',
        ];

        return $requirements[$venueType] ?? 'Standard venue setup requirements apply.';
    }

    private function getEquipmentAvailable(string $venueType): string
    {
        $equipment = [
            'studio' => 'Professional balloon equipment, design tools, consultation furniture',
            'hall' => 'Sound system, lighting, tables, chairs, staging equipment',
            'garden' => 'Outdoor equipment, weather protection, extension leads',
            'ballroom' => 'Professional sound and lighting, dance floor, staging',
            'restaurant' => 'Tables, chairs, service equipment, kitchen access',
            'hotel' => 'Full hotel amenities, professional equipment, catering facilities',
            'church' => 'Basic sound system, seating, altar area access',
            'outdoor' => 'Weather protection, portable equipment, power access',
            'home' => 'Basic household facilities, flexible space usage',
            'client_location' => 'Mobile equipment provided by service team',
            'office' => 'Professional meeting facilities, presentation equipment',
            'warehouse' => 'Industrial space, high ceiling access, loading facilities',
            'general' => 'Basic venue facilities and equipment',
        ];

        return $equipment[$venueType] ?? 'Standard equipment available upon request.';
    }

    private function getAccessibilityInfo(): string
    {
        $options = [
            'Fully wheelchair accessible with ramps and accessible toilets',
            'Ground floor access with accessible parking available',
            'Wheelchair accessible entrance with elevator access to all floors',
            'Limited accessibility - please contact for specific requirements',
            'Accessible parking and toilets, step-free access throughout',
            'Hearing loop installed, wheelchair accessible facilities available',
        ];

        return $this->faker->randomElement($options);
    }

    private function getParkingInfo(): string
    {
        $options = [
            'Free on-site parking for 20 vehicles',
            'Limited street parking, public transport recommended',
            'Paid parking available, £5 per hour',
            'Free parking for 2 hours, then £2 per hour',
            'Valet parking service available for events',
            'No parking on-site, nearest car park 2 minutes walk',
            'Free parking with advance booking required',
        ];

        return $this->faker->randomElement($options);
    }

    private function getCateringOptions(string $venueType): ?string
    {
        if (in_array($venueType, ['client_location', 'outdoor'])) {
            return null;
        }

        $options = [
            'Full catering kitchen with professional chef available',
            'Basic refreshments and light snacks available',
            'External catering can be arranged with approved suppliers',
            'Tea, coffee, and biscuits provided for consultations',
            'Full bar service and catering menu available',
            'Kitchen facilities available for external caterers',
        ];

        return $this->faker->randomElement($options);
    }

    private function getAmenities(string $venueType): array
    {
        $baseAmenities = ['WiFi', 'Toilets', 'Basic refreshments'];

        $specificAmenities = [
            'studio' => ['Professional lighting', 'Climate control', 'Equipment storage'],
            'hall' => ['Sound system', 'Dance floor', 'Stage area', 'Kitchen'],
            'garden' => ['Outdoor space', 'Weather protection', 'Natural setting'],
            'ballroom' => ['Elegant decor', 'Professional lighting', 'Dance floor'],
            'restaurant' => ['Full kitchen', 'Table service', 'Bar facilities'],
            'hotel' => ['Full hotel services', 'Concierge', 'Room service'],
            'church' => ['Ceremonial space', 'Organ', 'Seating'],
            'outdoor' => ['Natural setting', 'Fresh air', 'Scenic views'],
            'home' => ['Intimate setting', 'Flexible space', 'Personal touch'],
            'client_location' => ['Flexible location', 'Personalized service'],
            'office' => ['Professional environment', 'Meeting facilities', 'Presentation equipment'],
            'warehouse' => ['High ceilings', 'Open space', 'Loading access'],
            'general' => ['Flexible space', 'Basic facilities'],
        ];

        return array_merge($baseAmenities, $specificAmenities[$venueType] ?? []);
    }

    private function getRestrictions(string $venueType): array
    {
        $baseRestrictions = ['No smoking', 'Advance booking required'];

        $specificRestrictions = [
            'studio' => ['No food near equipment', 'Maximum capacity limits'],
            'hall' => ['No confetti', 'Music curfew at 11 PM'],
            'garden' => ['Weather dependent', 'No loud music after 8 PM'],
            'ballroom' => ['Formal dress code', 'No outside catering'],
            'restaurant' => ['Minimum spend required', 'No outside food'],
            'hotel' => ['Hotel policies apply', 'Noise restrictions'],
            'church' => ['Religious guidelines', 'Appropriate content only'],
            'outdoor' => ['Weather dependent', 'Backup plan required'],
            'home' => ['Respect property', 'Parking limitations'],
            'client_location' => ['Client venue rules apply', 'Setup access required'],
            'office' => ['Business hours only', 'Professional conduct'],
            'warehouse' => ['Safety regulations', 'Industrial guidelines'],
            'general' => ['Standard terms apply', 'Follow venue rules'],
        ];

        return array_merge($baseRestrictions, $specificRestrictions[$venueType] ?? []);
    }

    private function getOperatingHours(): array
    {
        return [
            'monday' => ['09:00', '18:00'],
            'tuesday' => ['09:00', '18:00'],
            'wednesday' => ['09:00', '18:00'],
            'thursday' => ['09:00', '18:00'],
            'friday' => ['09:00', '18:00'],
            'saturday' => ['10:00', '16:00'],
            'sunday' => $this->faker->boolean(30) ? ['12:00', '16:00'] : 'closed',
        ];
    }

    private function getCancellationPolicy(): string
    {
        $policies = [
            '48 hours notice required for all cancellations',
            '24 hours notice required, 50% charge for late cancellations',
            '72 hours notice required for weekend bookings',
            'Standard cancellation policy applies as per terms and conditions',
            '48 hours notice required, full refund for earlier cancellations',
        ];

        return $this->faker->randomElement($policies);
    }

    private function getSpecialInstructions(string $venueType): string
    {
        $instructions = [
            'studio' => 'Please arrive 15 minutes early for equipment briefing.',
            'hall' => 'Loading access available via rear entrance during setup.',
            'garden' => 'Weather backup plan will be discussed during consultation.',
            'ballroom' => 'Formal attire recommended for all events.',
            'restaurant' => 'Coordinate with restaurant manager for service timing.',
            'hotel' => 'Check in with hotel concierge upon arrival.',
            'church' => 'Please respect religious guidelines and venue policies.',
            'outdoor' => 'Bring weather-appropriate clothing and backup plans.',
            'home' => 'Please ensure clear access for equipment and setup.',
            'client_location' => 'Coordinate with venue management for access and requirements.',
            'office' => 'Maintain professional standards throughout the event.',
            'warehouse' => 'Safety equipment required, coordinate with facilities team.',
            'general' => 'Follow all venue guidelines and safety requirements.',
        ];

        return $instructions[$venueType] ?? 'Please follow all venue guidelines and contact us with any questions.';
    }
}

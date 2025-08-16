<?php

namespace Database\Factories;

use App\Models\ServiceLocation;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceLocationFactory extends Factory
{
    protected $model = ServiceLocation::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['business_premises', 'client_location', 'virtual', 'outdoor']);

        $baseData = [
            'service_id' => Service::factory(),
            'name' => $this->generateLocationName($type),
            'description' => $this->faker->optional(0.7)->paragraph(),
            'type' => $type,
            'max_capacity' => $this->faker->numberBetween(1, 5),
            'travel_time_minutes' => $this->generateTravelTime($type),
            'additional_charge' => $this->generateAdditionalCharge($type),
            'is_active' => $this->faker->boolean(90), // 90% active
        ];

        return array_merge($baseData, $this->generateTypeSpecificData($type));
    }

    private function generateLocationName(string $type): string
    {
        return match($type) {
            'business_premises' => $this->faker->randomElement([
                'Main Studio', 'Studio A', 'Treatment Room 1', 'Conference Room',
                'Office Suite', 'Consultation Room', 'Workshop Space'
            ]),
            'client_location' => $this->faker->randomElement([
                'Client\'s Home', 'Client\'s Office', 'On-Site Location', 'Customer Premises'
            ]),
            'virtual' => $this->faker->randomElement([
                'Zoom Meeting', 'Teams Conference', 'Online Session', 'Virtual Consultation'
            ]),
            'outdoor' => $this->faker->randomElement([
                'Park Location', 'Beach Session', 'Outdoor Studio', 'Garden Setting'
            ]),
            default => 'Location'
        };
    }

    private function generateTravelTime(string $type): int
    {
        return match($type) {
            'business_premises' => 0,
            'client_location' => $this->faker->numberBetween(15, 60),
            'virtual' => 0,
            'outdoor' => $this->faker->numberBetween(10, 45),
            default => 0
        };
    }

    private function generateAdditionalCharge(string $type): int
    {
        return match($type) {
            'business_premises' => 0,
            'client_location' => $this->faker->boolean(60) ? $this->faker->numberBetween(1000, 5000) : 0, // £10-£50
            'virtual' => 0,
            'outdoor' => $this->faker->boolean(30) ? $this->faker->numberBetween(500, 2000) : 0, // £5-£20
            default => 0
        };
    }

    private function generateTypeSpecificData(string $type): array
    {
        switch ($type) {
            case 'business_premises':
                return [
                    'address_line_1' => $this->faker->streetAddress(),
                    'address_line_2' => $this->faker->optional(0.3)->secondaryAddress(),
                    'city' => $this->faker->city(),
                    'county' => $this->faker->county(),
                    'postcode' => $this->faker->postcode(),
                    'country' => 'GB',
                    'latitude' => $this->faker->latitude(50.0, 60.0), // UK roughly
                    'longitude' => $this->faker->longitude(-8.0, 2.0),
                    'equipment_available' => $this->faker->optional(0.6)->randomElements([
                        'Professional lighting', 'Sound system', 'Projector', 'Whiteboard',
                        'Coffee machine', 'WiFi', 'Parking', 'Reception area'
                    ], $this->faker->numberBetween(1, 4)),
                    'facilities' => $this->faker->optional(0.5)->randomElements([
                        'Wheelchair accessible', 'Parking available', 'Public transport nearby',
                        'Refreshments available', 'Waiting area', 'Restroom facilities'
                    ], $this->faker->numberBetween(1, 3)),
                ];

            case 'client_location':
                return [
                    'availability_notes' => [
                        'travel_required' => true,
                        'equipment_limitations' => $this->faker->optional(0.4)->sentence(),
                        'special_instructions' => $this->faker->optional(0.3)->sentence(),
                    ],
                ];

            case 'virtual':
                return [
                    'virtual_platform' => $this->faker->randomElement(['Zoom', 'Microsoft Teams', 'Google Meet', 'Skype']),
                    'virtual_instructions' => $this->faker->paragraph(),
                    'equipment_available' => ['High-speed internet', 'HD camera', 'Professional microphone'],
                ];

            case 'outdoor':
                return [
                    'address_line_1' => $this->faker->optional(0.7)->streetAddress(),
                    'city' => $this->faker->city(),
                    'county' => $this->faker->county(),
                    'postcode' => $this->faker->optional(0.6)->postcode(),
                    'country' => 'GB',
                    'latitude' => $this->faker->optional(0.8)->latitude(50.0, 60.0),
                    'longitude' => $this->faker->optional(0.8)->longitude(-8.0, 2.0),
                    'availability_notes' => [
                        'weather_dependent' => true,
                        'backup_indoor_location' => $this->faker->optional(0.5)->sentence(),
                    ],
                    'facilities' => $this->faker->optional(0.4)->randomElements([
                        'Parking available', 'Public toilets nearby', 'Shelter available',
                        'Equipment storage', 'Power supply'
                    ], $this->faker->numberBetween(1, 3)),
                ];

            default:
                return [];
        }
    }

    public function businessPremises(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'business_premises',
            'name' => $this->faker->randomElement(['Main Studio', 'Treatment Room 1', 'Conference Room A']),
            'travel_time_minutes' => 0,
            'additional_charge' => 0,
        ]);
    }

    public function clientLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'client_location',
            'name' => 'Client\'s Location',
            'travel_time_minutes' => $this->faker->numberBetween(15, 60),
            'additional_charge' => $this->faker->numberBetween(1000, 5000),
        ]);
    }

    public function virtual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'virtual',
            'name' => 'Online Session',
            'travel_time_minutes' => 0,
            'additional_charge' => 0,
            'virtual_platform' => $this->faker->randomElement(['Zoom', 'Microsoft Teams', 'Google Meet']),
            'virtual_instructions' => 'Meeting link will be sent 24 hours before the session.',
        ]);
    }

    public function outdoor(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'outdoor',
            'name' => $this->faker->randomElement(['Park Location', 'Beach Session', 'Garden Setting']),
            'travel_time_minutes' => $this->faker->numberBetween(10, 45),
            'additional_charge' => $this->faker->numberBetween(500, 2000),
        ]);
    }
}

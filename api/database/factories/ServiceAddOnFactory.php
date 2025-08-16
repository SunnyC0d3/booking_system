<?php

namespace Database\Factories;

use App\Models\ServiceAddOn;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceAddOnFactory extends Factory
{
    protected $model = ServiceAddOn::class;

    public function definition(): array
    {
        $category = $this->faker->randomElement(['equipment', 'service_enhancement', 'location', 'other']);

        return [
            'service_id' => Service::factory(),
            'name' => $this->generateAddOnName($category),
            'description' => $this->faker->sentence(8),
            'price' => $this->generatePrice($category),
            'duration_minutes' => $this->generateDuration($category),
            'is_active' => $this->faker->boolean(90),
            'is_required' => $this->faker->boolean(15), // 15% chance of being required
            'max_quantity' => $this->faker->randomElement([1, 1, 1, 2, 3, 5]), // Weighted towards 1
            'sort_order' => $this->faker->numberBetween(0, 100),
            'category' => $category,
        ];
    }

    private function generateAddOnName(string $category): string
    {
        return match($category) {
            'equipment' => $this->faker->randomElement([
                'Professional Lighting Setup',
                'Wireless Microphone',
                'HD Camera Equipment',
                'Tripod and Stands',
                'Backdrop Setup',
                'Extra Equipment Kit',
                'Specialized Tools',
                'Premium Audio System'
            ]),
            'service_enhancement' => $this->faker->randomElement([
                'Extended Session (+30min)',
                'Rush Delivery',
                'Premium Package',
                'Additional Consultation',
                'Follow-up Session',
                'Detailed Report',
                'Priority Booking',
                'Express Service'
            ]),
            'location' => $this->faker->randomElement([
                'Travel to Location',
                'Premium Venue',
                'Additional Room',
                'Outdoor Setup',
                'Extended Area Coverage',
                'Multiple Location Setup',
                'Remote Access',
                'Venue Preparation'
            ]),
            'other' => $this->faker->randomElement([
                'Digital Copies',
                'Printed Materials',
                'Certificate',
                'Take-home Kit',
                'Additional Guest',
                'Refreshments',
                'Parking Pass',
                'Insurance Coverage'
            ]),
            default => 'Additional Service'
        };
    }

    private function generatePrice(string $category): int
    {
        return match($category) {
            'equipment' => $this->faker->numberBetween(1500, 8000), // £15-£80
            'service_enhancement' => $this->faker->numberBetween(2000, 12000), // £20-£120
            'location' => $this->faker->numberBetween(1000, 5000), // £10-£50
            'other' => $this->faker->numberBetween(500, 3000), // £5-£30
            default => $this->faker->numberBetween(1000, 5000)
        };
    }

    private function generateDuration(string $category): int
    {
        return match($category) {
            'equipment' => $this->faker->randomElement([0, 15, 30]), // Setup time
            'service_enhancement' => $this->faker->randomElement([15, 30, 60]), // Additional time
            'location' => $this->faker->randomElement([0, 30, 60]), // Travel/setup time
            'other' => 0, // Usually no additional time
            default => 0
        };
    }

    public function equipment(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'equipment',
            'name' => $this->faker->randomElement([
                'Professional Lighting Setup',
                'Wireless Microphone',
                'HD Camera Equipment',
                'Specialized Tools'
            ]),
            'price' => $this->faker->numberBetween(1500, 8000),
            'duration_minutes' => $this->faker->randomElement([0, 15, 30]),
        ]);
    }

    public function serviceEnhancement(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'service_enhancement',
            'name' => $this->faker->randomElement([
                'Extended Session (+30min)',
                'Rush Delivery',
                'Premium Package',
                'Priority Booking'
            ]),
            'price' => $this->faker->numberBetween(2000, 12000),
            'duration_minutes' => $this->faker->randomElement([15, 30, 60]),
        ]);
    }

    public function location(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'location',
            'name' => $this->faker->randomElement([
                'Travel to Location',
                'Premium Venue',
                'Outdoor Setup',
                'Multiple Location Setup'
            ]),
            'price' => $this->faker->numberBetween(1000, 5000),
            'duration_minutes' => $this->faker->randomElement([0, 30, 60]),
        ]);
    }

    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
            'name' => $this->faker->randomElement([
                'Basic Setup Fee',
                'Consultation Required',
                'Safety Equipment',
                'Initial Assessment'
            ]),
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    public function multipleQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_quantity' => $this->faker->numberBetween(2, 10),
            'name' => $this->faker->randomElement([
                'Additional Guest',
                'Extra Hour',
                'Additional Copy',
                'Extra Session'
            ]),
        ]);
    }

    public function extendedTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Extended Session (+30 minutes)',
            'duration_minutes' => 30,
            'price' => $this->faker->numberBetween(2000, 5000),
            'category' => 'service_enhancement',
            'max_quantity' => 4, // Up to 2 hours extra
        ]);
    }
}

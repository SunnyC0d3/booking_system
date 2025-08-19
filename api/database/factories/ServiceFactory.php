<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $basePrice = $this->faker->numberBetween(2500, 50000); // £25.00 to £500.00
        $requiresDeposit = $this->faker->boolean(70); // 70% chance of requiring deposit

        return [
            'name' => $this->faker->randomElement([
                'Personal Training Session',
                'Business Consultation',
                'Hair Cut & Style',
                'Massage Therapy',
                'Legal Consultation',
                'Photography Session',
                'Website Design Consultation',
                'Interior Design Meeting',
                'Fitness Assessment',
                'Nutritional Consultation',
                'Dog Grooming Service',
                'Cleaning Service',
                'Tax Preparation',
                'Music Lesson',
                'Language Tutoring',
            ]),
            'description' => $this->faker->paragraphs(3, true),
            'short_description' => $this->faker->sentence(10),
            'base_price' => $basePrice,
            'duration_minutes' => $this->faker->randomElement([30, 45, 60, 90, 120, 180]),
            'buffer_minutes' => $this->faker->randomElement([0, 15, 30]),
            'max_advance_booking_days' => $this->faker->randomElement([7, 14, 30, 60, 90]),
            'min_advance_booking_hours' => $this->faker->randomElement([2, 24, 48, 72]),
            'requires_deposit' => $requiresDeposit,
            'deposit_percentage' => $requiresDeposit && $this->faker->boolean(60)
                ? $this->faker->randomFloat(2, 10, 50)
                : null,
            'deposit_amount' => $requiresDeposit && $this->faker->boolean(40)
                ? $this->faker->numberBetween(1000, 5000) // £10-£50 fixed deposit
                : null,
            'status' => $this->faker->randomElement(['active', 'active', 'active', 'inactive', 'draft']), // Weighted towards active
            'metadata' => $this->faker->boolean(30) ? [
                'special_requirements' => $this->faker->sentence(),
                'preparation_notes' => $this->faker->sentence(),
                'equipment_needed' => $this->faker->words(3),
            ] : null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function withDeposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_deposit' => true,
            'deposit_percentage' => $this->faker->randomFloat(2, 20, 50),
        ]);
    }

    public function withFixedDeposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_deposit' => true,
            'deposit_amount' => $this->faker->numberBetween(1000, 5000),
            'deposit_percentage' => null,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\ServicePackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicePackageFactory extends Factory
{
    protected $model = ServicePackage::class;

    public function definition(): array
    {
        $packages = [
            [
                'name' => 'Wedding Complete Package',
                'description' => 'Everything you need for your perfect wedding day. Includes balloon arches, table centerpieces, photo backdrop, and venue decoration.',
                'short_description' => 'Complete wedding decoration package with balloon arches and centerpieces',
                'requires_consultation' => true,
                'consultation_duration_minutes' => 60,
            ],
            [
                'name' => 'Birthday Party Deluxe',
                'description' => 'Make your birthday celebration unforgettable with our deluxe package including balloon arrangements, party backdrop, and custom decorations.',
                'short_description' => 'Deluxe birthday party decoration package',
                'requires_consultation' => false,
            ],
            [
                'name' => 'Corporate Event Package',
                'description' => 'Professional event decoration suitable for corporate gatherings, conferences, and business celebrations.',
                'short_description' => 'Professional corporate event decorations',
                'requires_consultation' => true,
                'consultation_duration_minutes' => 45,
            ],
            [
                'name' => 'Baby Shower Bliss',
                'description' => 'Celebrate the upcoming arrival with our beautiful baby shower decoration package featuring soft pastels and themed arrangements.',
                'short_description' => 'Adorable baby shower decoration package',
                'requires_consultation' => false,
            ],
            [
                'name' => 'Anniversary Elegance',
                'description' => 'Romantic and elegant decorations perfect for anniversary celebrations, featuring sophisticated balloon arrangements and ambient lighting.',
                'short_description' => 'Elegant anniversary celebration decorations',
                'requires_consultation' => false,
            ],
        ];

        $package = $this->faker->randomElement($packages);

        return [
            'name' => $package['name'],
            'description' => $package['description'],
            'short_description' => $package['short_description'],
            'total_price' => 0, // Will be calculated after services are added
            'discount_amount' => 0,
            'discount_percentage' => null,
            'individual_price_total' => 0,
            'total_duration_minutes' => 0,
            'is_active' => $this->faker->boolean(85),
            'requires_consultation' => $package['requires_consultation'],
            'consultation_duration_minutes' => $package['consultation_duration_minutes'] ?? null,
            'max_advance_booking_days' => $this->faker->randomElement([30, 60, 90, 180]),
            'min_advance_booking_hours' => $this->faker->randomElement([24, 48, 72]),
            'cancellation_policy' => 'Cancellations must be made at least 48 hours in advance for a full refund. Cancellations within 48 hours will incur a 50% cancellation fee.',
            'terms_and_conditions' => 'All decorations remain the property of the service provider and must be returned in good condition. Setup and breakdown times are included in the service duration.',
            'sort_order' => $this->faker->numberBetween(1, 10),
            'metadata' => [
                'features' => $this->faker->randomElements([
                    'Premium balloons',
                    'Custom color schemes',
                    'Professional setup',
                    'Breakdown included',
                    'Photo opportunities',
                    'Themed decorations',
                    'Lighting effects',
                    'Backdrop options'
                ], $this->faker->numberBetween(3, 6)),
                'suitable_for' => $this->faker->randomElements([
                    'Indoor venues',
                    'Outdoor venues',
                    'Small gatherings',
                    'Large events',
                    'Photography sessions',
                    'Corporate events'
                ], $this->faker->numberBetween(2, 4)),
            ],
        ];
    }

    public function withDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $discountType = $this->faker->randomElement(['amount', 'percentage']);

            if ($discountType === 'percentage') {
                return [
                    'discount_percentage' => $this->faker->randomFloat(1, 5, 25), // 5-25% discount
                    'discount_amount' => 0,
                ];
            } else {
                return [
                    'discount_amount' => $this->faker->numberBetween(1000, 5000), // £10-50 discount
                    'discount_percentage' => null,
                ];
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withConsultation(): static
    {
        return $this->state([
            'requires_consultation' => true,
            'consultation_duration_minutes' => $this->faker->randomElement([30, 45, 60, 90]),
        ]);
    }
}

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
                'description' => 'Everything you need for your perfect wedding day. Our most comprehensive package includes stunning balloon arches, elegant table centerpieces, romantic photo backdrop, and complete venue decoration. Professional setup and breakdown included.',
                'short_description' => 'Complete wedding decoration package with everything included',
                'requires_consultation' => true,
                'consultation_duration_minutes' => 60,
                'category' => 'wedding',
            ],
            [
                'name' => 'Birthday Party Deluxe',
                'description' => 'Make your birthday celebration unforgettable! Includes colorful balloon arrangements, themed party backdrop, and custom decorations to match your party theme.',
                'short_description' => 'Deluxe birthday party decoration package',
                'requires_consultation' => false,
                'category' => 'birthday',
            ],
            [
                'name' => 'Corporate Event Package',
                'description' => 'Professional and elegant decoration solution for corporate gatherings, conferences, and business celebrations. Sophisticated designs that reflect your brand.',
                'short_description' => 'Professional corporate event decorations',
                'requires_consultation' => true,
                'consultation_duration_minutes' => 45,
                'category' => 'corporate',
            ],
            [
                'name' => 'Baby Shower Bliss',
                'description' => 'Celebrate the upcoming arrival with our beautiful baby shower decoration package. Featuring soft pastels, themed arrangements, and adorable details perfect for this special occasion.',
                'short_description' => 'Adorable baby shower decoration package',
                'requires_consultation' => false,
                'category' => 'baby_shower',
            ],
            [
                'name' => 'Anniversary Elegance',
                'description' => 'Romantic and elegant decorations perfect for anniversary celebrations. Features sophisticated balloon arrangements, ambient lighting effects, and intimate table settings.',
                'short_description' => 'Elegant anniversary celebration decorations',
                'requires_consultation' => false,
                'category' => 'anniversary',
            ],
            [
                'name' => 'Graduation Celebration',
                'description' => 'Honor this momentous achievement with our graduation celebration package. Features school colors, diploma-themed decorations, and celebratory balloon arrangements.',
                'short_description' => 'Academic achievement celebration decorations',
                'requires_consultation' => false,
                'category' => 'graduation',
            ],
            [
                'name' => 'Holiday Spectacular',
                'description' => 'Transform your space for the holidays with seasonal decorations, festive balloon arrangements, and themed centerpieces that capture the spirit of the season.',
                'short_description' => 'Seasonal holiday decoration package',
                'requires_consultation' => false,
                'category' => 'holiday',
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
            'cancellation_policy' => $this->generateCancellationPolicy($package['category']),
            'terms_and_conditions' => $this->generateTermsAndConditions($package['category']),
            'sort_order' => $this->faker->numberBetween(1, 10),
            'metadata' => $this->generateMetadata($package['category']),
        ];
    }

    private function generateCancellationPolicy(string $category): string
    {
        $basePolicies = [
            'wedding' => 'Wedding packages require 72 hours notice for cancellation. Cancellations within 72 hours will incur a 25% fee. Cancellations within 24 hours will incur a 50% fee.',
            'corporate' => 'Corporate bookings require 48 hours notice for full refund. Cancellations within 48 hours will incur a 30% fee.',
            'birthday' => 'Birthday party cancellations must be made at least 24 hours in advance for a full refund. Same-day cancellations will incur a 25% fee.',
            'baby_shower' => 'Baby shower packages require 48 hours notice for cancellation. Late cancellations will incur a 20% fee.',
            'anniversary' => 'Anniversary celebration cancellations require 24 hours notice for full refund. Late cancellations will incur a 25% fee.',
            'graduation' => 'Graduation packages require 48 hours notice for cancellation. Late cancellations will incur a 30% fee.',
            'holiday' => 'Holiday decoration cancellations require 72 hours notice due to seasonal demand. Late cancellations will incur a 35% fee.',
        ];

        return $basePolicies[$category] ?? 'Cancellations must be made at least 48 hours in advance for a full refund. Cancellations within 48 hours will incur a 50% cancellation fee.';
    }

    private function generateTermsAndConditions(string $category): string
    {
        return 'All decorations remain the property of the service provider and must be returned in good condition. Setup and breakdown times are included in the service duration. Client must provide adequate access to the venue and suitable working conditions. Weather conditions may affect outdoor setups and alternatives will be discussed if needed.';
    }

    private function generateMetadata(string $category): array
    {
        $baseMetadata = [
            'category' => $category,
            'features' => $this->faker->randomElements([
                'Premium balloons',
                'Custom color schemes',
                'Professional setup',
                'Breakdown included',
                'Photo opportunities',
                'Themed decorations',
                'Lighting effects',
                'Backdrop options',
                'Table decorations',
                'Arch installations',
            ], $this->faker->numberBetween(3, 6)),
            'suitable_for' => $this->faker->randomElements([
                'Indoor venues',
                'Outdoor venues',
                'Small gatherings',
                'Large events',
                'Photography sessions',
                'Corporate events',
                'Private celebrations',
                'Public events',
            ], $this->faker->numberBetween(2, 4)),
        ];

        // Add category-specific metadata
        switch ($category) {
            case 'wedding':
                $baseMetadata['wedding_styles'] = $this->faker->randomElements([
                    'Traditional', 'Modern', 'Rustic', 'Vintage', 'Romantic', 'Minimalist'
                ], 2);
                $baseMetadata['includes'] = [
                    'Bridal arch',
                    'Table centerpieces (up to 10 tables)',
                    'Photo backdrop',
                    'Aisle decorations',
                    'Professional setup',
                    'Breakdown service',
                    'Consultation included'
                ];
                break;

            case 'corporate':
                $baseMetadata['corporate_types'] = $this->faker->randomElements([
                    'conferences', 'product launches', 'corporate parties', 'award ceremonies', 'team building'
                ], 3);
                $baseMetadata['customizable'] = true;
                $baseMetadata['brand_colors'] = true;
                break;

            case 'birthday':
                $baseMetadata['age_groups'] = $this->faker->randomElements(['kids', 'teens', 'adults'], 2);
                $baseMetadata['themes_available'] = $this->faker->randomElements([
                    'cartoon', 'superhero', 'princess', 'sports', 'custom', 'unicorn', 'dinosaur'
                ], 3);
                break;

            case 'baby_shower':
                $baseMetadata['themes'] = $this->faker->randomElements([
                    'boy', 'girl', 'neutral', 'safari', 'cloud', 'floral', 'storybook'
                ], 3);
                $baseMetadata['color_schemes'] = $this->faker->randomElements([
                    'pink/gold', 'blue/silver', 'mint/gold', 'neutral/gold', 'pastel rainbow'
                ], 2);
                break;

            case 'anniversary':
                $baseMetadata['romantic_themes'] = $this->faker->randomElements([
                    'classic', 'vintage', 'modern', 'garden', 'elegant'
                ], 2);
                $baseMetadata['anniversary_milestones'] = $this->faker->randomElements([
                    '1st', '5th', '10th', '25th', '50th'
                ], 2);
                $baseMetadata['special_touches'] = ['candles', 'flowers', 'personalized elements'];
                break;

            case 'graduation':
                $baseMetadata['education_levels'] = $this->faker->randomElements([
                    'high_school', 'college', 'university', 'graduate', 'doctorate'
                ], 2);
                $baseMetadata['school_colors'] = true;
                break;

            case 'holiday':
                $baseMetadata['seasonal_themes'] = $this->faker->randomElements([
                    'christmas', 'new_year', 'easter', 'halloween', 'thanksgiving'
                ], 2);
                $baseMetadata['seasonal_only'] = true;
                break;
        }

        return $baseMetadata;
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

    public function wedding(): static
    {
        return $this->state([
            'name' => 'Wedding Complete Package',
            'description' => 'Everything you need for your perfect wedding day. Our most comprehensive package includes stunning balloon arches, elegant table centerpieces, romantic photo backdrop, and complete venue decoration. Professional setup and breakdown included.',
            'short_description' => 'Complete wedding decoration package with everything included',
            'requires_consultation' => true,
            'consultation_duration_minutes' => 60,
            'max_advance_booking_days' => 180,
            'min_advance_booking_hours' => 72,
        ]);
    }

    public function corporate(): static
    {
        return $this->state([
            'name' => 'Corporate Event Package',
            'description' => 'Professional and elegant decoration solution for corporate gatherings, conferences, and business celebrations. Sophisticated designs that reflect your brand.',
            'short_description' => 'Professional corporate event decorations',
            'requires_consultation' => true,
            'consultation_duration_minutes' => 45,
            'max_advance_booking_days' => 90,
            'min_advance_booking_hours' => 48,
        ]);
    }

    public function birthday(): static
    {
        return $this->state([
            'name' => 'Birthday Party Deluxe',
            'description' => 'Make your birthday celebration unforgettable! Includes colorful balloon arrangements, themed party backdrop, and custom decorations to match your party theme.',
            'short_description' => 'Deluxe birthday party decoration package',
            'requires_consultation' => false,
            'max_advance_booking_days' => 60,
            'min_advance_booking_hours' => 24,
        ]);
    }
}


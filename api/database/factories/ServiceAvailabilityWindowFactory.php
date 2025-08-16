<?php

namespace Database\Factories;

use App\Models\ServiceAvailabilityWindow;
use App\Models\Service;
use App\Models\ServiceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ServiceAvailabilityWindowFactory extends Factory
{
    protected $model = ServiceAvailabilityWindow::class;

    public function definition(): array
    {
        $pattern = $this->faker->randomElement(['weekly', 'specific_date', 'date_range']);
        $type = $this->faker->randomElement(['regular', 'exception', 'special_hours']);

        $startTime = $this->faker->time('H:i:s', '18:00:00');
        $endTime = $this->faker->time('H:i:s', '23:59:59');

        // Ensure end time is after start time
        while (strtotime($endTime) <= strtotime($startTime)) {
            $endTime = $this->faker->time('H:i:s', '23:59:59');
        }

        $baseData = [
            'service_id' => Service::factory(),
            'service_location_id' => $this->faker->boolean(60) ? ServiceLocation::factory() : null,
            'type' => $type,
            'pattern' => $pattern,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'max_bookings' => $this->faker->numberBetween(1, 3),
            'slot_duration_minutes' => $this->faker->optional(0.3)->randomElement([30, 45, 60, 90, 120]),
            'break_duration_minutes' => $this->faker->randomElement([0, 15, 30]),
            'is_active' => $this->faker->boolean(95),
            'is_bookable' => $type !== 'blocked',
            'title' => $this->generateTitle($type, $pattern),
            'description' => $this->faker->optional(0.6)->sentence(),
        ];

        return array_merge($baseData, $this->generatePatternSpecificData($pattern));
    }

    private function generateTitle(string $type, string $pattern): ?string
    {
        if ($type === 'regular') {
            return match($pattern) {
                'weekly' => $this->faker->randomElement(['Business Hours', 'Standard Hours', 'Regular Availability']),
                'date_range' => $this->faker->randomElement(['Holiday Hours', 'Special Period', 'Extended Hours']),
                'specific_date' => $this->faker->randomElement(['Special Day', 'Extended Hours', 'Holiday Schedule']),
                default => null
            };
        }

        return match($type) {
            'exception' => $this->faker->randomElement(['Holiday Exception', 'Special Event', 'Reduced Hours']),
            'special_hours' => $this->faker->randomElement(['Weekend Hours', 'Evening Sessions', 'Extended Hours']),
            'blocked' => $this->faker->randomElement(['Maintenance Time', 'Private Booking', 'Unavailable']),
            default => null
        };
    }

    private function generatePatternSpecificData(string $pattern): array
    {
        switch ($pattern) {
            case 'weekly':
                return [
                    'day_of_week' => $this->faker->numberBetween(1, 6), // Monday to Saturday
                    'start_date' => null,
                    'end_date' => null,
                ];

            case 'specific_date':
                $date = $this->faker->dateTimeBetween('now', '+90 days');
                return [
                    'day_of_week' => null,
                    'start_date' => $date,
                    'end_date' => null,
                ];

            case 'date_range':
                $startDate = $this->faker->dateTimeBetween('now', '+60 days');
                $endDate = $this->faker->dateTimeBetween($startDate, '+30 days');
                return [
                    'day_of_week' => null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ];

            default:
                return [
                    'day_of_week' => null,
                    'start_date' => null,
                    'end_date' => null,
                ];
        }
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pattern' => 'weekly',
            'day_of_week' => $this->faker->numberBetween(1, 6),
            'start_date' => null,
            'end_date' => null,
        ]);
    }

    public function monday(): static
    {
        return $this->weekly()->state(fn (array $attributes) => [
            'day_of_week' => 1,
        ]);
    }

    public function tuesday(): static
    {
        return $this->weekly()->state(fn (array $attributes) => [
            'day_of_week' => 2,
        ]);
    }

    public function wednesday(): static
    {
        return $this->weekly()->state(fn (array $attributes) => [
            'day_of_week' => 3,
        ]);
    }

    public function thursday(): static
    {
        return $this->weekly()->state(fn (array $attributes) => [
            'day_of_week' => 4,
        ]);
    }

    public function friday(): static
    {
        return $this->weekly()->state(fn (array $attributes) => [
            'day_of_week' => 5,
        ]);
    }

    public function saturday(): static
    {
        return $this->weekly()->state(fn (array $attributes) => [
            'day_of_week' => 6,
        ]);
    }

    public function sunday(): static
    {
        return $this->weekly()->state(fn (array $attributes) => [
            'day_of_week' => 0,
        ]);
    }

    public function businessHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'regular',
            'pattern' => 'weekly',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'title' => 'Business Hours',
        ]);
    }

    public function eveningHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'special_hours',
            'pattern' => 'weekly',
            'start_time' => '18:00:00',
            'end_time' => '21:00:00',
            'title' => 'Evening Sessions',
            'price_modifier' => 1000, // £10 extra
            'price_modifier_type' => 'fixed',
        ]);
    }

    public function weekendHours(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'special_hours',
            'pattern' => 'weekly',
            'day_of_week' => $this->faker->randomElement([0, 6]), // Sunday or Saturday
            'start_time' => '10:00:00',
            'end_time' => '16:00:00',
            'title' => 'Weekend Hours',
            'price_modifier' => 500, // £5 extra
            'price_modifier_type' => 'fixed',
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'blocked',
            'is_bookable' => false,
            'title' => 'Unavailable',
        ]);
    }

    public function holiday(): static
    {
        $startDate = $this->faker->dateTimeBetween('+7 days', '+60 days');
        $endDate = Carbon::instance($startDate)->addDays($this->faker->numberBetween(1, 7));

        return $this->state(fn (array $attributes) => [
            'type' => 'exception',
            'pattern' => 'date_range',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_bookable' => false,
            'title' => 'Holiday Period',
        ]);
    }

    public function specialEvent(): static
    {
        $date = $this->faker->dateTimeBetween('+1 day', '+30 days');

        return $this->state(fn (array $attributes) => [
            'type' => 'special_hours',
            'pattern' => 'specific_date',
            'start_date' => $date,
            'end_date' => null,
            'start_time' => '10:00:00',
            'end_time' => '20:00:00',
            'title' => 'Special Event Day',
            'max_bookings' => 5,
        ]);
    }

    public function withPriceModifier(int $amount, string $type = 'fixed'): static
    {
        return $this->state(fn (array $attributes) => [
            'price_modifier' => $amount,
            'price_modifier_type' => $type,
        ]);
    }
}

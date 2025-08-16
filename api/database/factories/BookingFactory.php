<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $service = Service::factory()->create();
        $user = User::factory()->create();

        // Generate a random date within the next 30 days
        $scheduledAt = $this->faker->dateTimeBetween('now', '+30 days');
        $scheduledAt = Carbon::instance($scheduledAt);

        // Calculate end time based on service duration
        $durationMinutes = $service->duration_minutes + $this->faker->randomElement([0, 15, 30]); // Some variation
        $endsAt = $scheduledAt->clone()->addMinutes($durationMinutes);

        // Calculate pricing
        $basePrice = $service->base_price;
        $addOnsTotal = $this->faker->boolean(30) ? $this->faker->numberBetween(500, 2000) : 0; // 30% chance of add-ons
        $totalAmount = $basePrice + $addOnsTotal;

        // Calculate deposit if service requires it
        $depositAmount = null;
        $remainingAmount = null;
        if ($service->requires_deposit) {
            $depositAmount = $service->getDepositAmountAttribute();
            $remainingAmount = $totalAmount - $depositAmount;
        }

        $clientName = $this->faker->name();

        return [
            'user_id' => $user->id,
            'service_id' => $service->id,
            'service_location_id' => $this->faker->boolean(70) ? ServiceLocation::factory()->create(['service_id' => $service->id])->id : null,
            'scheduled_at' => $scheduledAt,
            'ends_at' => $endsAt,
            'duration_minutes' => $durationMinutes,
            'base_price' => $basePrice,
            'addons_total' => $addOnsTotal,
            'total_amount' => $totalAmount,
            'deposit_amount' => $depositAmount,
            'remaining_amount' => $remainingAmount,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'confirmed', 'confirmed', 'in_progress', 'completed', 'cancelled']), // Weighted towards confirmed
            'payment_status' => $this->faker->randomElement(['pending', 'deposit_paid', 'fully_paid', 'fully_paid', 'refunded']), // Weighted towards paid
            'client_name' => $clientName,
            'client_email' => $this->faker->safeEmail(),
            'client_phone' => $this->faker->boolean(80) ? $this->faker->phoneNumber() : null,
            'notes' => $this->faker->boolean(40) ? $this->faker->paragraph() : null,
            'special_requirements' => $this->faker->boolean(20) ? $this->faker->sentence() : null,
            'requires_consultation' => $this->faker->boolean(30),
            'consultation_completed_at' => $this->faker->boolean(50) ? $this->faker->dateTimeBetween('-7 days', 'now') : null,
            'consultation_notes' => $this->faker->boolean(30) ? $this->faker->paragraph() : null,
            'metadata' => $this->faker->boolean(20) ? [
                'source' => $this->faker->randomElement(['website', 'phone', 'email', 'referral']),
                'preferences' => $this->faker->words(3),
            ] : null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'payment_status' => $this->faker->randomElement(['deposit_paid', 'fully_paid']),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'payment_status' => 'fully_paid',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'cancellation_reason' => $this->faker->randomElement([
                'Client requested cancellation',
                'Rescheduled by client',
                'Emergency cancellation',
                'Weather conditions',
                'Client no-show'
            ]),
        ]);
    }

    public function requiresConsultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_consultation' => true,
            'consultation_completed_at' => null,
        ]);
    }

    public function consultationCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_consultation' => true,
            'consultation_completed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'consultation_notes' => $this->faker->paragraph(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $this->faker->dateTimeBetween('+1 day', '+30 days'),
            'status' => $this->faker->randomElement(['pending', 'confirmed']),
        ]);
    }

    public function today(): static
    {
        $today = Carbon::today();
        $scheduledAt = $today->clone()->addHours($this->faker->numberBetween(9, 17));

        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt->clone()->addMinutes($attributes['duration_minutes'] ?? 60),
            'status' => $this->faker->randomElement(['confirmed', 'in_progress']),
        ]);
    }
}

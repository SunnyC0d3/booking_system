<?php

namespace Database\Factories;

use App\Models\LicenseKey;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LicenseKeyFactory extends Factory
{
    protected $model = LicenseKey::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['single_use', 'multi_use', 'subscription', 'trial']);
        $activationLimit = $this->getActivationLimit($type);

        return [
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'order_id' => Order::factory(),
            'license_key' => $this->generateLicenseKey(),
            'type' => $type,
            'status' => $this->faker->randomElement(['active', 'expired', 'revoked', 'suspended']),
            'activation_limit' => $activationLimit,
            'activations_used' => $this->faker->numberBetween(0, $activationLimit),
            'expires_at' => $this->getExpiryDate($type),
            'first_activated_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'last_activated_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 week', 'now'),
            'activated_devices' => $this->faker->optional(0.5)->randomElements([
                [
                    'device_id' => Str::uuid(),
                    'device_name' => $this->faker->randomElement(['John\'s Laptop', 'Office PC', 'MacBook Pro']),
                    'platform' => $this->faker->randomElement(['Windows', 'macOS', 'Linux']),
                    'activated_at' => $this->faker->dateTimeBetween('-1 month', 'now')->toISOString(),
                    'ip_address' => $this->faker->ipv4(),
                ],
                [
                    'device_id' => Str::uuid(),
                    'device_name' => $this->faker->randomElement(['Work Computer', 'Home Desktop', 'iPad']),
                    'platform' => $this->faker->randomElement(['Windows', 'macOS', 'iOS']),
                    'activated_at' => $this->faker->dateTimeBetween('-2 weeks', 'now')->toISOString(),
                    'ip_address' => $this->faker->ipv4(),
                ],
            ], $this->faker->numberBetween(1, 2)),
            'metadata' => [
                'purchase_ip' => $this->faker->ipv4(),
                'created_by_order' => true,
            ],
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function singleUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'single_use',
            'activation_limit' => 1,
            'activations_used' => $this->faker->numberBetween(0, 1),
        ]);
    }

    public function multiUse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'multi_use',
            'activation_limit' => $this->faker->numberBetween(3, 10),
        ]);
    }

    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'subscription',
            'activation_limit' => $this->faker->numberBetween(5, 15),
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
        ]);
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'trial',
            'activation_limit' => 1,
            'expires_at' => $this->faker->dateTimeBetween('now', '+30 days'),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
            'notes' => 'Revoked: ' . $this->faker->sentence(),
        ]);
    }

    public function fullyActivated(): static
    {
        return $this->state(function (array $attributes) {
            $limit = $attributes['activation_limit'] ?? 1;
            return [
                'activations_used' => $limit,
                'first_activated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
                'last_activated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'activations_used' => 0,
            'first_activated_at' => null,
            'last_activated_at' => null,
            'activated_devices' => null,
        ]);
    }

    private function generateLicenseKey(): string
    {
        $prefix = $this->faker->randomElement(['PROD', 'SOFT', 'APP', 'TOOL']);
        $segments = [
            $prefix,
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4)),
            strtoupper(Str::random(4))
        ];

        return implode('-', $segments);
    }

    private function getActivationLimit(string $type): int
    {
        return match($type) {
            'single_use' => 1,
            'multi_use' => $this->faker->numberBetween(3, 10),
            'subscription' => $this->faker->numberBetween(5, 15),
            'trial' => 1,
            default => 1
        };
    }

    private function getExpiryDate(string $type): ?\Carbon\Carbon
    {
        return match($type) {
            'trial' => $this->faker->dateTimeBetween('now', '+30 days'),
            'subscription' => $this->faker->dateTimeBetween('now', '+1 year'),
            default => $this->faker->optional(0.3)->dateTimeBetween('now', '+2 years')
        };
    }
}

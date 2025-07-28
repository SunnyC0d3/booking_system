<?php

namespace Database\Factories;

use App\Models\DownloadAccess;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Models\ProductFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DownloadAccessFactory extends Factory
{
    protected $model = DownloadAccess::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'product_id' => Product::factory(),
            'order_id' => Order::factory(),
            'product_file_id' => ProductFile::factory(),
            'access_token' => Str::random(64),
            'status' => $this->faker->randomElement(['active', 'expired', 'revoked', 'suspended']),
            'download_limit' => $this->faker->numberBetween(1, 10),
            'downloads_used' => $this->faker->numberBetween(0, 5),
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
            'first_downloaded_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 month', 'now'),
            'last_downloaded_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 week', 'now'),
            'allowed_ips' => $this->faker->optional(0.3)->randomElements([
                $this->faker->ipv4(),
                $this->faker->ipv4(),
            ], $this->faker->numberBetween(1, 2)),
            'metadata' => [
                'created_by_order' => true,
                'original_ip' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
            ],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => $this->faker->dateTimeBetween('now', '+1 year'),
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
        ]);
    }

    public function withDownloads(): static
    {
        return $this->state(fn (array $attributes) => [
            'downloads_used' => $this->faker->numberBetween(1, $attributes['download_limit'] ?? 5),
            'first_downloaded_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'last_downloaded_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    public function unused(): static
    {
        return $this->state(fn (array $attributes) => [
            'downloads_used' => 0,
            'first_downloaded_at' => null,
            'last_downloaded_at' => null,
        ]);
    }

    public function limitedDownloads(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'download_limit' => $limit,
            'downloads_used' => $this->faker->numberBetween(0, $limit),
        ]);
    }

    public function ipRestricted(array $ips = null): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_ips' => $ips ?? [$this->faker->ipv4(), $this->faker->ipv4()],
        ]);
    }
}

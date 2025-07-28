<?php

namespace Database\Factories;

use App\Models\DownloadAttempt;
use App\Models\DownloadAccess;
use App\Models\User;
use App\Models\ProductFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class DownloadAttemptFactory extends Factory
{
    protected $model = DownloadAttempt::class;

    public function definition(): array
    {
        $status = $this->faker->randomElement(['started', 'completed', 'failed', 'interrupted']);
        $startedAt = $this->faker->dateTimeBetween('-1 month', 'now');
        $fileSize = $this->faker->numberBetween(1048576, 104857600); // 1MB to 100MB
        $bytesDownloaded = $status === 'completed' ? $fileSize : $this->faker->numberBetween(0, $fileSize);

        return [
            'download_access_id' => DownloadAccess::factory(),
            'user_id' => User::factory(),
            'product_file_id' => ProductFile::factory(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'status' => $status,
            'bytes_downloaded' => $bytesDownloaded,
            'total_file_size' => $fileSize,
            'download_speed_kbps' => $this->faker->optional(0.8)->randomFloat(2, 50, 10000),
            'duration_seconds' => $this->faker->optional(0.8)->numberBetween(1, 3600),
            'failure_reason' => $status === 'failed' ? $this->faker->randomElement([
                'Connection timeout',
                'User cancelled download',
                'Insufficient storage space',
                'Network error',
                'Server error'
            ]) : null,
            'headers' => [
                'Accept' => 'application/octet-stream',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Range' => $this->faker->optional(0.3)->randomElement([
                    'bytes=0-1023',
                    'bytes=1024-2047',
                    'bytes=2048-'
                ]),
            ],
            'started_at' => $startedAt,
            'completed_at' => in_array($status, ['completed', 'failed', 'interrupted'])
                ? $this->faker->dateTimeBetween($startedAt, 'now')
                : null,
        ];
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $attributes['started_at'] ?? $this->faker->dateTimeBetween('-1 month', 'now');
            return [
                'status' => 'completed',
                'bytes_downloaded' => $attributes['total_file_size'],
                'completed_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
                'download_speed_kbps' => $this->faker->randomFloat(2, 100, 5000),
                'duration_seconds' => $this->faker->numberBetween(10, 1800),
                'failure_reason' => null,
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $attributes['started_at'] ?? $this->faker->dateTimeBetween('-1 month', 'now');
            return [
                'status' => 'failed',
                'bytes_downloaded' => $this->faker->numberBetween(0, intval($attributes['total_file_size'] * 0.5)),
                'completed_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
                'failure_reason' => $this->faker->randomElement([
                    'Connection timeout',
                    'Network error',
                    'Server error',
                    'File not found',
                    'Access denied'
                ]),
            ];
        });
    }

    public function interrupted(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $attributes['started_at'] ?? $this->faker->dateTimeBetween('-1 month', 'now');
            return [
                'status' => 'interrupted',
                'bytes_downloaded' => $this->faker->numberBetween(0, intval($attributes['total_file_size'] * 0.8)),
                'completed_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
                'failure_reason' => 'User cancelled download',
            ];
        });
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'started',
            'bytes_downloaded' => $this->faker->numberBetween(0, intval($attributes['total_file_size'] * 0.7)),
            'completed_at' => null,
            'failure_reason' => null,
        ]);
    }

    public function fastDownload(): static
    {
        return $this->state(fn (array $attributes) => [
            'download_speed_kbps' => $this->faker->randomFloat(2, 5000, 50000),
            'duration_seconds' => $this->faker->numberBetween(1, 60),
        ]);
    }

    public function slowDownload(): static
    {
        return $this->state(fn (array $attributes) => [
            'download_speed_kbps' => $this->faker->randomFloat(2, 10, 100),
            'duration_seconds' => $this->faker->numberBetween(300, 3600),
        ]);
    }
}

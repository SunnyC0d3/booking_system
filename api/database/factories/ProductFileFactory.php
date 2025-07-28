<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFileFactory extends Factory
{
    protected $model = ProductFile::class;

    public function definition(): array
    {
        $fileTypes = ['pdf', 'zip', 'exe', 'dmg', 'txt', 'mp3', 'mp4', 'jpg', 'png'];
        $fileType = $this->faker->randomElement($fileTypes);
        $filename = $this->faker->slug(3) . '.' . $fileType;

        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->sentence(3),
            'original_filename' => $filename,
            'file_path' => 'vendor_' . $this->faker->numberBetween(1, 10) . '/product_' . $this->faker->numberBetween(1, 100) . '/' . $this->faker->uuid() . '.' . $fileType,
            'file_type' => $fileType,
            'mime_type' => $this->getMimeType($fileType),
            'file_size' => $this->faker->numberBetween(1024, 104857600), // 1KB to 100MB
            'file_hash' => hash('sha256', $this->faker->text()),
            'is_primary' => false,
            'is_active' => true,
            'download_limit' => $this->faker->optional(0.3)->numberBetween(1, 10),
            'download_count' => $this->faker->numberBetween(0, 100),
            'metadata' => [
                'upload_ip' => $this->faker->ipv4(),
                'created_by_factory' => true,
            ],
            'version' => $this->faker->randomElement(['1.0.0', '1.1.0', '2.0.0', '2.1.0', '3.0.0']),
            'description' => $this->faker->optional(0.7)->sentence(),
            'expires_at' => $this->faker->optional(0.1)->dateTimeBetween('now', '+1 year'),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withDownloadLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'download_limit' => $limit,
        ]);
    }

    public function software(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_type' => $this->faker->randomElement(['exe', 'msi', 'dmg', 'deb']),
            'mime_type' => 'application/octet-stream',
            'file_size' => $this->faker->numberBetween(10485760, 1073741824), // 10MB to 1GB
            'name' => $this->faker->randomElement(['Installer', 'Setup', 'Application']) . ' v' . $this->faker->randomFloat(1, 1, 5),
        ]);
    }

    public function document(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_type' => $this->faker->randomElement(['pdf', 'doc', 'docx', 'txt']),
            'mime_type' => $this->faker->randomElement(['application/pdf', 'application/msword', 'text/plain']),
            'file_size' => $this->faker->numberBetween(102400, 10485760), // 100KB to 10MB
            'name' => $this->faker->randomElement(['Manual', 'Guide', 'Documentation', 'eBook']) . ' - ' . $this->faker->words(2, true),
        ]);
    }

    public function media(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_type' => $this->faker->randomElement(['mp3', 'wav', 'mp4', 'avi', 'jpg', 'png']),
            'mime_type' => $this->faker->randomElement(['audio/mpeg', 'video/mp4', 'image/jpeg', 'image/png']),
            'file_size' => $this->faker->numberBetween(1048576, 104857600), // 1MB to 100MB
            'name' => $this->faker->randomElement(['Audio Track', 'Video File', 'Image']) . ' ' . $this->faker->numberBetween(1, 50),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    private function getMimeType(string $fileType): string
    {
        return match($fileType) {
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'exe', 'msi', 'dmg' => 'application/octet-stream',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'application/octet-stream'
        };
    }
}

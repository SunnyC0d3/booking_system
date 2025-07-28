<?php

namespace App\Services\V1\DigitalProducts;

use App\Models\LicenseKey;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use App\Models\ProductUpdate;
use App\Models\DownloadAccess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class LicenseKeyService
{
    /**
     * Generate a new license key for a product
     */
    public function generateLicense(Product $product, User $user, Order $order, string $type = 'single_use'): LicenseKey
    {
        if (!$product->requiresLicense()) {
            throw new Exception('This product does not require a license key');
        }

        return DB::transaction(function () use ($product, $user, $order, $type) {
            $licenseKey = LicenseKey::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'license_key' => $this->generateLicenseKeyString($product),
                'type' => $type,
                'status' => 'active',
                'activation_limit' => $this->getActivationLimit($product, $type),
                'expires_at' => $this->getLicenseExpiry($product, $type),
                'metadata' => [
                    'generated_at' => now()->toISOString(),
                    'generator_ip' => request()?->ip(),
                    'product_version' => $product->latest_version,
                ],
            ]);

            Log::info('License key generated', [
                'license_id' => $licenseKey->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'type' => $type,
                'expires_at' => $licenseKey->expires_at
            ]);

            return $licenseKey;
        });
    }

    /**
     * Validate a license key
     */
    public function validateLicense(string $licenseKey, ?int $productId = null): LicenseKey
    {
        $license = LicenseKey::where('license_key', $licenseKey)->first();

        if (!$license) {
            throw new Exception('License key not found.', 404);
        }

        if ($productId && $license->product_id !== $productId) {
            throw new Exception('License key is not valid for this product.', 400);
        }

        if ($license->status !== 'active') {
            throw new Exception("License key has been {$license->status}.", 400);
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            $license->update(['status' => 'expired']);
            throw new Exception('License key has expired.', 400);
        }

        return $license;
    }

    /**
     * Activate a license key on a device
     */
    public function activateLicense(
        string $licenseKey,
        string $deviceName,
        string $deviceId,
        array $deviceInfo = [],
        ?string $productVersion = null
    ): array {
        return DB::transaction(function () use ($licenseKey, $deviceName, $deviceId, $deviceInfo, $productVersion) {
            $license = $this->validateLicense($licenseKey);

            // Check if already activated on this device
            $activatedDevices = $license->activated_devices ?? [];
            foreach ($activatedDevices as $device) {
                if ($device['device_id'] === $deviceId) {
                    // Update existing activation
                    $this->updateDeviceActivation($license, $deviceId, $deviceInfo, $productVersion);

                    Log::info('License re-activated on existing device', [
                        'license_id' => $license->id,
                        'device_id' => $deviceId,
                        'device_name' => $deviceName
                    ]);

                    return [
                        'license' => $license->fresh(),
                        'activation_id' => $device['activation_id'],
                        'is_new_activation' => false
                    ];
                }
            }

            // Check activation limit
            if ($license->activations_used >= $license->activation_limit) {
                throw new Exception('License key activation limit exceeded.', 400);
            }

            // Create new activation
            $activationId = 'act_' . Str::random(12);
            $activation = [
                'activation_id' => $activationId,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'activated_at' => now()->toISOString(),
                'last_seen' => now()->toISOString(),
                'product_version' => $productVersion ?? $license->product->latest_version,
                'device_info' => $deviceInfo,
                'ip_address' => request()?->ip(),
                'activation_count' => 1,
            ];

            $activatedDevices[] = $activation;

            // Update license
            $license->update([
                'activated_devices' => $activatedDevices,
                'activations_used' => $license->activations_used + 1,
                'first_activated_at' => $license->first_activated_at ?? now(),
                'last_activated_at' => now(),
            ]);

            Log::info('License activated on new device', [
                'license_id' => $license->id,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'activations_used' => $license->activations_used,
                'activations_remaining' => $license->getRemainingActivations()
            ]);

            return [
                'license' => $license->fresh(),
                'activation_id' => $activationId,
                'is_new_activation' => true
            ];
        });
    }

    /**
     * Deactivate a license key from a device
     */
    public function deactivateLicense(string $licenseKey, string $deviceId, string $reason = 'Manual deactivation'): array
    {
        return DB::transaction(function () use ($licenseKey, $deviceId, $reason) {
            $license = LicenseKey::where('license_key', $licenseKey)->firstOrFail();

            $activatedDevices = $license->activated_devices ?? [];
            $deviceFound = false;

            foreach ($activatedDevices as $index => $device) {
                if ($device['device_id'] === $deviceId) {
                    // Remove device from activated devices
                    array_splice($activatedDevices, $index, 1);
                    $deviceFound = true;
                    break;
                }
            }

            if (!$deviceFound) {
                throw new Exception('License activation not found for this device.', 404);
            }

            // Update license
            $license->update([
                'activated_devices' => $activatedDevices,
                'activations_used' => max(0, $license->activations_used - 1),
                'metadata' => array_merge($license->metadata ?? [], [
                    'last_deactivation' => [
                        'device_id' => $deviceId,
                        'reason' => $reason,
                        'deactivated_at' => now()->toISOString(),
                        'deactivated_by_ip' => request()?->ip(),
                    ]
                ]),
            ]);

            Log::info('License deactivated from device', [
                'license_id' => $license->id,
                'device_id' => $deviceId,
                'reason' => $reason,
                'activations_remaining' => $license->getRemainingActivations()
            ]);

            return [
                'license' => $license->fresh(),
                'deactivated_device_id' => $deviceId,
                'reason' => $reason
            ];
        });
    }

    /**
     * Get license information
     */
    public function getLicenseInfo(string $licenseKey): LicenseKey
    {
        $license = LicenseKey::where('license_key', $licenseKey)
            ->with(['product', 'user', 'order'])
            ->firstOrFail();

        // Update last checked timestamp
        $license->update([
            'metadata' => array_merge($license->metadata ?? [], [
                'last_checked_at' => now()->toISOString(),
                'last_check_ip' => request()?->ip(),
            ])
        ]);

        return $license;
    }

    /**
     * Check for product updates
     */
    public function checkProductUpdates(string $licenseKey, string $currentVersion, string $deviceId): array
    {
        $license = $this->validateLicense($licenseKey);
        $product = $license->product;

        // Verify device is activated
        if (!$this->isDeviceActivated($license, $deviceId)) {
            throw new Exception('License is not activated on this device.', 403);
        }

        // Get available updates
        $updates = ProductUpdate::where('product_id', $product->id)
            ->where('released_at', '<=', now())
            ->orderBy('released_at', 'desc')
            ->get();

        $availableUpdates = [];
        $hasUpdates = false;
        $latestVersion = $product->latest_version ?? $currentVersion;

        foreach ($updates as $update) {
            if (version_compare($update->version, $currentVersion, '>')) {
                $hasUpdates = true;
                $availableUpdates[] = [
                    'version' => $update->version,
                    'title' => $update->title,
                    'description' => $update->description,
                    'changelog' => $update->changelog,
                    'update_type' => $update->update_type,
                    'priority' => $update->priority,
                    'released_at' => $update->released_at,
                    'download_size' => $update->productFile?->file_size_formatted ?? 'Unknown',
                    'is_security_update' => $update->is_security_update,
                    'force_update' => $update->force_update,
                ];

                if (version_compare($update->version, $latestVersion, '>')) {
                    $latestVersion = $update->version;
                }
            }
        }

        $result = [
            'has_updates' => $hasUpdates,
            'latest_version' => $latestVersion,
            'current_version' => $currentVersion,
            'updates' => $availableUpdates,
        ];

        // Add download access if updates are available
        if ($hasUpdates) {
            $downloadAccess = $this->createUpdateDownloadAccess($license, $deviceId);
            $result['download_access'] = [
                'has_access' => true,
                'download_token' => $downloadAccess->access_token,
                'expires_at' => $downloadAccess->expires_at,
            ];
        }

        // Log update check
        Log::info('Product update check', [
            'license_id' => $license->id,
            'product_id' => $product->id,
            'device_id' => $deviceId,
            'current_version' => $currentVersion,
            'has_updates' => $hasUpdates,
            'latest_version' => $latestVersion
        ]);

        return $result;
    }

    /**
     * Record usage analytics
     */
    public function recordUsageAnalytics(string $licenseKey, string $deviceId, array $usageData, string $ipAddress): array
    {
        $license = $this->validateLicense($licenseKey);

        // Verify device is activated
        if (!$this->isDeviceActivated($license, $deviceId)) {
            throw new Exception('License is not activated on this device.', 403);
        }

        $sessionId = 'sess_' . Str::random(16);

        // Store analytics data
        $analyticsRecord = [
            'session_id' => $sessionId,
            'license_id' => $license->id,
            'product_id' => $license->product_id,
            'user_id' => $license->user_id,
            'device_id' => $deviceId,
            'recorded_at' => now()->toISOString(),
            'ip_address' => $ipAddress,
            'usage_data' => $usageData,
        ];

        // Update device last seen
        $this->updateDeviceLastSeen($license, $deviceId, $usageData);

        // Store in cache for real-time analytics (optional)
        cache()->put(
            "license_analytics:{$license->id}:{$sessionId}",
            $analyticsRecord,
            now()->addDays(30)
        );

        Log::info('Usage analytics recorded', [
            'license_id' => $license->id,
            'device_id' => $deviceId,
            'session_id' => $sessionId,
            'session_duration' => $usageData['session_duration'] ?? 0,
            'features_used_count' => count($usageData['features_used'] ?? [])
        ]);

        return [
            'session_id' => $sessionId,
            'recorded_at' => now(),
        ];
    }

    /**
     * Revoke a license key
     */
    public function revokeLicense(LicenseKey $license, string $reason, ?User $revokedBy = null): LicenseKey
    {
        return DB::transaction(function () use ($license, $reason, $revokedBy) {
            $license->update([
                'status' => 'revoked',
                'metadata' => array_merge($license->metadata ?? [], [
                    'revoked_at' => now()->toISOString(),
                    'revoked_reason' => $reason,
                    'revoked_by_user_id' => $revokedBy?->id,
                    'revoked_by_ip' => request()?->ip(),
                ]),
            ]);

            Log::warning('License key revoked', [
                'license_id' => $license->id,
                'product_id' => $license->product_id,
                'user_id' => $license->user_id,
                'reason' => $reason,
                'revoked_by' => $revokedBy?->id
            ]);

            return $license;
        });
    }

    /**
     * Generate a license key string
     */
    protected function generateLicenseKeyString(Product $product): string
    {
        do {
            $prefix = $this->getProductPrefix($product);
            $segments = [
                $prefix,
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4)),
                strtoupper(Str::random(4)),
            ];

            $licenseKey = implode('-', $segments);
        } while (LicenseKey::where('license_key', $licenseKey)->exists());

        return $licenseKey;
    }

    /**
     * Get product prefix for license key
     */
    protected function getProductPrefix(Product $product): string
    {
        // Extract uppercase letters from product name
        $name = preg_replace('/[^A-Z]/', '', strtoupper($product->name));

        if (strlen($name) >= 4) {
            return substr($name, 0, 4);
        }

        // Fallback: use first letters of words
        $words = explode(' ', $product->name);
        $prefix = '';
        foreach ($words as $word) {
            $prefix .= strtoupper(substr($word, 0, 1));
            if (strlen($prefix) >= 4) break;
        }

        // Pad with random letters if needed
        while (strlen($prefix) < 4) {
            $prefix .= chr(65 + rand(0, 25)); // A-Z
        }

        return substr($prefix, 0, 4);
    }

    /**
     * Get activation limit based on product and license type
     */
    protected function getActivationLimit(Product $product, string $type): int
    {
        return match ($type) {
            'single_use' => 1,
            'multi_use' => 3,
            'subscription' => 5,
            'trial' => 1,
            default => 1,
        };
    }

    /**
     * Get license expiry date
     */
    protected function getLicenseExpiry(Product $product, string $type): ?Carbon
    {
        return match ($type) {
            'single_use' => null, // No expiry
            'multi_use' => null, // No expiry
            'subscription' => now()->addYear(), // 1 year
            'trial' => now()->addDays(30), // 30 days
            default => null,
        };
    }

    /**
     * Check if device is activated for license
     */
    protected function isDeviceActivated(LicenseKey $license, string $deviceId): bool
    {
        $activatedDevices = $license->activated_devices ?? [];

        foreach ($activatedDevices as $device) {
            if ($device['device_id'] === $deviceId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Update device activation info
     */
    protected function updateDeviceActivation(LicenseKey $license, string $deviceId, array $deviceInfo, ?string $productVersion): void
    {
        $activatedDevices = $license->activated_devices ?? [];

        foreach ($activatedDevices as $index => $device) {
            if ($device['device_id'] === $deviceId) {
                $activatedDevices[$index] = array_merge($device, [
                    'last_seen' => now()->toISOString(),
                    'device_info' => array_merge($device['device_info'] ?? [], $deviceInfo),
                    'product_version' => $productVersion ?? $device['product_version'],
                    'activation_count' => ($device['activation_count'] ?? 1) + 1,
                ]);
                break;
            }
        }

        $license->update([
            'activated_devices' => $activatedDevices,
            'last_activated_at' => now(),
        ]);
    }

    /**
     * Update device last seen timestamp
     */
    protected function updateDeviceLastSeen(LicenseKey $license, string $deviceId, array $usageData): void
    {
        $activatedDevices = $license->activated_devices ?? [];

        foreach ($activatedDevices as $index => $device) {
            if ($device['device_id'] === $deviceId) {
                $activatedDevices[$index]['last_seen'] = now()->toISOString();
                $activatedDevices[$index]['last_usage'] = [
                    'session_duration' => $usageData['session_duration'] ?? 0,
                    'features_used' => $usageData['features_used'] ?? [],
                    'performance_metrics' => $usageData['performance_metrics'] ?? [],
                ];
                break;
            }
        }

        $license->update(['activated_devices' => $activatedDevices]);
    }

    /**
     * Create download access for product updates
     */
    protected function createUpdateDownloadAccess(LicenseKey $license, string $deviceId): DownloadAccess
    {
        return DownloadAccess::create([
            'user_id' => $license->user_id,
            'product_id' => $license->product_id,
            'order_id' => $license->order_id,
            'access_token' => DownloadAccess::generateToken(),
            'download_limit' => 3, // Limited downloads for updates
            'expires_at' => now()->addDays(7), // 7 days to download update
            'status' => 'active',
            'metadata' => [
                'created_for_update' => true,
                'license_id' => $license->id,
                'device_id' => $deviceId,
                'update_check_ip' => request()?->ip(),
            ],
        ]);
    }
}

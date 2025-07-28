<?php

namespace App\Services\V1\DigitalProducts;

use App\Models\LicenseKey;
use App\Models\Product;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

class LicenseKeyService
{
    public function generateLicense(Product $product, User $user, Order $order, string $type = 'single_use'): LicenseKey
    {
        if (!$product->requiresLicense()) {
            throw new Exception('Product does not require a license');
        }

        $licenseKey = LicenseKey::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'license_key' => LicenseKey::generateKey($this->getProductPrefix($product)),
            'type' => $type,
            'status' => 'active',
            'activation_limit' => $this->getActivationLimit($type, $product),
            'expires_at' => $this->getLicenseExpiry($type, $product),
            'metadata' => [
                'generated_by_purchase' => true,
                'original_ip' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ],
        ]);

        Log::info('License key generated', [
            'license_id' => $licenseKey->id,
            'product_id' => $product->id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => $type
        ]);

        return $licenseKey;
    }

    public function validateLicense(string $licenseKey): LicenseKey
    {
        $license = LicenseKey::where('license_key', $licenseKey)
            ->with(['product', 'user'])
            ->first();

        if (!$license) {
            throw new Exception('License key not found', 404);
        }

        if (!$license->isValid()) {
            $reason = $this->getInvalidReason($license);
            throw new Exception("License invalid: {$reason}", 403);
        }

        return $license;
    }

    public function activateLicense(string $licenseKey, array $deviceInfo, Request $request): array
    {
        $license = $this->validateLicense($licenseKey);

        if (!$license->canActivate()) {
            throw new Exception('License activation limit reached', 403);
        }

        $activationData = array_merge($deviceInfo, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'activated_at' => now()->toISOString(),
        ]);

        $success = $license->activate($activationData);

        if (!$success) {
            throw new Exception('Failed to activate license', 500);
        }

        Log::info('License activated successfully', [
            'license_id' => $license->id,
            'device_info' => $deviceInfo,
            'ip' => $request->ip(),
            'remaining_activations' => $license->remaining_activations
        ]);

        return [
            'license_key' => $license->license_key,
            'product_name' => $license->product->name,
            'activation_successful' => true,
            'activations_used' => $license->activations_used,
            'activations_remaining' => $license->remaining_activations,
            'expires_at' => $license->expires_at,
            'activated_at' => now(),
        ];
    }

    public function deactivateLicense(string $licenseKey, string $deviceId = null): bool
    {
        $license = LicenseKey::where('license_key', $licenseKey)->first();

        if (!$license) {
            throw new Exception('License key not found', 404);
        }

        $success = $license->deactivate($deviceId);

        Log::info('License deactivated', [
            'license_id' => $license->id,
            'device_id' => $deviceId,
            'remaining_activations' => $license->remaining_activations
        ]);

        return $success;
    }

    public function revokeLicense(LicenseKey $license, string $reason = null): void
    {
        $license->revoke($reason);

        Log::warning('License revoked', [
            'license_id' => $license->id,
            'reason' => $reason
        ]);
    }

    public function extendLicense(LicenseKey $license, int $additionalDays): void
    {
        $currentExpiry = $license->expires_at ?? now();
        $newExpiry = $currentExpiry->addDays($additionalDays);

        $license->update([
            'expires_at' => $newExpiry,
            'metadata' => array_merge($license->metadata ?? [], [
                'extended_at' => now()->toISOString(),
                'extended_days' => $additionalDays,
            ]),
        ]);

        Log::info('License extended', [
            'license_id' => $license->id,
            'additional_days' => $additionalDays,
            'new_expiry' => $newExpiry
        ]);
    }

    public function getLicenseInfo(string $licenseKey): array
    {
        $license = LicenseKey::where('license_key', $licenseKey)
            ->with(['product', 'user'])
            ->first();

        if (!$license) {
            throw new Exception('License key not found', 404);
        }

        return [
            'license_key' => $license->license_key,
            'type' => $license->type,
            'type_label' => $license->type_label,
            'status' => $license->status,
            'status_label' => $license->status_label,
            'product' => [
                'id' => $license->product->id,
                'name' => $license->product->name,
                'version' => $license->product->latest_version,
            ],
            'activation_limit' => $license->activation_limit,
            'activations_used' => $license->activations_used,
            'activations_remaining' => $license->remaining_activations,
            'expires_at' => $license->expires_at,
            'is_expired' => $license->isExpired(),
            'is_valid' => $license->isValid(),
            'can_activate' => $license->canActivate(),
            'activated_devices' => $license->activated_devices,
            'created_at' => $license->created_at,
        ];
    }

    protected function getProductPrefix(Product $product): string
    {
        $name = strtoupper(preg_replace('/[^A-Z]/', '', $product->name));
        return substr($name, 0, 4) ?: 'PROD';
    }

    protected function getActivationLimit(string $type, Product $product): int
    {
        return match($type) {
            'single_use' => 1,
            'multi_use' => 5,
            'subscription' => 10,
            'trial' => 1,
            default => 1
        };
    }

    protected function getLicenseExpiry(string $type, Product $product): ?\Carbon\Carbon
    {
        return match($type) {
            'trial' => now()->addDays(30),
            'subscription' => now()->addYear(),
            default => null
        };
    }

    protected function getInvalidReason(LicenseKey $license): string
    {
        if ($license->status !== 'active') {
            return "Status is {$license->status}";
        }

        if ($license->isExpired()) {
            return 'License has expired';
        }

        if (!$license->canActivate()) {
            return 'Activation limit reached';
        }

        return 'Unknown reason';
    }

    public function getLicenseAnalytics(Product $product = null): array
    {
        $query = $product
            ? LicenseKey::where('product_id', $product->id)
            : LicenseKey::query();

        $totalLicenses = $query->count();
        $activeLicenses = $query->where('status', 'active')->count();
        $expiredLicenses = $query->where('status', 'expired')->count();
        $revokedLicenses = $query->where('status', 'revoked')->count();

        $activationStats = $query->selectRaw('
            SUM(activations_used) as total_activations,
            AVG(activations_used) as avg_activations_per_license,
            SUM(CASE WHEN activations_used >= activation_limit THEN 1 ELSE 0 END) as fully_activated
        ')->first();

        $typeDistribution = $query->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        return [
            'licenses' => [
                'total' => $totalLicenses,
                'active' => $activeLicenses,
                'expired' => $expiredLicenses,
                'revoked' => $revokedLicenses,
            ],
            'activations' => [
                'total_activations' => $activationStats->total_activations ?? 0,
                'average_per_license' => round($activationStats->avg_activations_per_license ?? 0, 2),
                'fully_activated_licenses' => $activationStats->fully_activated ?? 0,
                'activation_rate' => $totalLicenses > 0
                    ? round(($activationStats->fully_activated / $totalLicenses) * 100, 2)
                    : 0,
            ],
            'type_distribution' => $typeDistribution,
            'recent_activity' => [
                'new_licenses_30_days' => $query->where('created_at', '>=', now()->subDays(30))->count(),
                'activations_30_days' => $query->where('first_activated_at', '>=', now()->subDays(30))->count(),
            ]
        ];
    }
}

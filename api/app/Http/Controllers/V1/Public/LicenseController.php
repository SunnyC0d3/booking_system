<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Models\LicenseKey;
use App\Services\V1\DigitalProducts\LicenseKeyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LicenseController extends Controller
{
    use ApiResponses;

    protected LicenseKeyService $licenseKeyService;

    public function __construct(LicenseKeyService $licenseKeyService)
    {
        $this->licenseKeyService = $licenseKeyService;
    }

    /**
     * Validate a license key
     *
     * Validate a license key without activating it. This endpoint can be used to check
     * if a license key is valid, active, and not expired before attempting activation.
     * This endpoint is unauthenticated to allow software applications to validate licenses.
     *
     * @group License Management
     * @unauthenticated
     *
     * @bodyParam license_key string required The license key to validate. Example: PROJ-ABCD-1234-EFGH-5678
     * @bodyParam product_id integer optional Product ID for additional validation. Example: 1
     *
     * @response 200 scenario="License key is valid" {
     *   "data": {
     *     "license_key": "PROJ-ABCD-1234-EFGH-5678",
     *     "status": "active",
     *     "type": "single_use",
     *     "product": {
     *       "id": 1,
     *       "name": "ProjectManager Pro",
     *       "version": "2.1.5"
     *     },
     *     "activation_limit": 1,
     *     "activations_used": 0,
     *     "activations_remaining": 1,
     *     "expires_at": "2025-01-15T23:59:59Z",
     *     "is_valid": true,
     *     "can_activate": true
     *   },
     *   "message": "License key is valid and ready for activation.",
     *   "status": 200
     * }
     *
     * @response 404 scenario="License key not found" {
     *   "message": "License key not found.",
     *   "status": 404
     * }
     *
     * @response 400 scenario="License key expired" {
     *   "message": "License key has expired.",
     *   "status": 400
     * }
     *
     * @response 400 scenario="License key revoked" {
     *   "message": "License key has been revoked.",
     *   "status": 400
     * }
     */
    public function validate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'license_key' => 'required|string|max:100',
                'product_id' => 'nullable|integer|exists:products,id',
            ]);

            $licenseKey = $request->input('license_key');
            $productId = $request->input('product_id');

            $license = $this->licenseKeyService->validateLicense($licenseKey, $productId);

            // Log license validation for security monitoring
            Log::info('License validation requested', [
                'license_key' => substr($licenseKey, 0, 8) . '***',
                'product_id' => $productId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => $license->status
            ]);

            $validationData = [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'type' => $license->type,
                'product' => [
                    'id' => $license->product->id,
                    'name' => $license->product->name,
                    'version' => $license->product->latest_version,
                ],
                'activation_limit' => $license->activation_limit,
                'activations_used' => $license->activations_used,
                'activations_remaining' => $license->getRemainingActivations(),
                'expires_at' => $license->expires_at,
                'is_valid' => $license->isValid(),
                'can_activate' => $license->canActivate(),
            ];

            return $this->ok(
                'License key is valid and ready for activation.',
                $validationData
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Activate a license key
     *
     * Activate a license key for a specific device or installation. This records the activation
     * and decrements the available activation count. Device information is stored for tracking.
     *
     * @group License Management
     * @unauthenticated
     *
     * @bodyParam license_key string required The license key to activate. Example: PROJ-ABCD-1234-EFGH-5678
     * @bodyParam device_name string required Name/identifier of the device. Example: "John's MacBook Pro"
     * @bodyParam device_id string required Unique device identifier. Example: "MAC-12345-ABCDE"
     * @bodyParam device_info object optional Additional device information.
     * @bodyParam device_info.os string optional Operating system. Example: "macOS 13.2"
     * @bodyParam device_info.hardware string optional Hardware information. Example: "MacBook Pro 16-inch 2023"
     * @bodyParam device_info.ip_address string optional Device IP address. Example: "192.168.1.100"
     * @bodyParam product_version string optional Version of the product being activated. Example: "2.1.5"
     *
     * @response 200 scenario="License activated successfully" {
     *   "data": {
     *     "license_key": "PROJ-ABCD-1234-EFGH-5678",
     *     "activation_id": "act_789xyz",
     *     "device_name": "John's MacBook Pro",
     *     "device_id": "MAC-12345-ABCDE",
     *     "activated_at": "2024-01-20T10:30:00Z",
     *     "activations_remaining": 0,
     *     "expires_at": "2025-01-15T23:59:59Z",
     *     "product": {
     *       "id": 1,
     *       "name": "ProjectManager Pro",
     *       "version": "2.1.5"
     *     }
     *   },
     *   "message": "License key activated successfully.",
     *   "status": 200
     * }
     *
     * @response 400 scenario="Activation limit exceeded" {
     *   "message": "License key activation limit exceeded.",
     *   "status": 400
     * }
     *
     * @response 409 scenario="Device already activated" {
     *   "message": "License key is already activated on this device.",
     *   "status": 409
     * }
     */
    public function activate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'license_key' => 'required|string|max:100',
                'device_name' => 'required|string|max:255',
                'device_id' => 'required|string|max:255',
                'device_info' => 'nullable|array',
                'device_info.os' => 'nullable|string|max:100',
                'device_info.hardware' => 'nullable|string|max:255',
                'device_info.ip_address' => 'nullable|ip',
                'product_version' => 'nullable|string|max:50',
            ]);

            $licenseKey = $request->input('license_key');
            $deviceName = $request->input('device_name');
            $deviceId = $request->input('device_id');
            $deviceInfo = $request->input('device_info', []);
            $productVersion = $request->input('product_version');

            // Add request IP if not provided
            if (!isset($deviceInfo['ip_address'])) {
                $deviceInfo['ip_address'] = $request->ip();
            }

            $activation = $this->licenseKeyService->activateLicense(
                $licenseKey,
                $deviceName,
                $deviceId,
                $deviceInfo,
                $productVersion
            );

            $activationData = [
                'license_key' => $activation['license']->license_key,
                'activation_id' => $activation['activation_id'],
                'device_name' => $deviceName,
                'device_id' => $deviceId,
                'activated_at' => now(),
                'activations_remaining' => $activation['license']->getRemainingActivations(),
                'expires_at' => $activation['license']->expires_at,
                'product' => [
                    'id' => $activation['license']->product->id,
                    'name' => $activation['license']->product->name,
                    'version' => $activation['license']->product->latest_version,
                ],
            ];

            return $this->ok(
                'License key activated successfully.',
                $activationData
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Deactivate a license key
     *
     * Deactivate a license key from a specific device. This frees up an activation slot
     * for use on another device. Useful for software transfers or device replacements.
     *
     * @group License Management
     * @unauthenticated
     *
     * @bodyParam license_key string required The license key to deactivate. Example: PROJ-ABCD-1234-EFGH-5678
     * @bodyParam device_id string required The device ID to deactivate. Example: "MAC-12345-ABCDE"
     * @bodyParam reason string optional Reason for deactivation. Example: "Device replacement"
     *
     * @response 200 scenario="License deactivated successfully" {
     *   "data": {
     *     "license_key": "PROJ-ABCD-1234-EFGH-5678",
     *     "device_id": "MAC-12345-ABCDE",
     *     "deactivated_at": "2024-01-20T15:45:00Z",
     *     "activations_remaining": 1,
     *     "reason": "Device replacement"
     *   },
     *   "message": "License key deactivated successfully.",
     *   "status": 200
     * }
     *
     * @response 404 scenario="Activation not found" {
     *   "message": "License activation not found for this device.",
     *   "status": 404
     * }
     */
    public function deactivate(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'license_key' => 'required|string|max:100',
                'device_id' => 'required|string|max:255',
                'reason' => 'nullable|string|max:500',
            ]);

            $licenseKey = $request->input('license_key');
            $deviceId = $request->input('device_id');
            $reason = $request->input('reason', 'Manual deactivation');

            $result = $this->licenseKeyService->deactivateLicense($licenseKey, $deviceId, $reason);

            $deactivationData = [
                'license_key' => $licenseKey,
                'device_id' => $deviceId,
                'deactivated_at' => now(),
                'activations_remaining' => $result['license']->getRemainingActivations(),
                'reason' => $reason,
            ];

            return $this->ok(
                'License key deactivated successfully.',
                $deactivationData
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Get license key information
     *
     * Retrieve detailed information about a license key including activation history,
     * device information, and usage statistics. Useful for license management portals.
     *
     * @group License Management
     * @unauthenticated
     *
     * @bodyParam license_key string required The license key to query. Example: PROJ-ABCD-1234-EFGH-5678
     *
     * @response 200 scenario="License information retrieved" {
     *   "data": {
     *     "license_key": "PROJ-ABCD-1234-EFGH-5678",
     *     "status": "active",
     *     "type": "multi_use",
     *     "product": {
     *       "id": 1,
     *       "name": "ProjectManager Pro",
     *       "version": "2.1.5"
     *     },
     *     "activation_limit": 3,
     *     "activations_used": 2,
     *     "activations_remaining": 1,
     *     "expires_at": "2025-01-15T23:59:59Z",
     *     "first_activated_at": "2024-01-15T10:30:00Z",
     *     "last_activated_at": "2024-01-18T14:20:00Z",
     *     "activated_devices": [
     *       {
     *         "device_id": "MAC-12345-ABCDE",
     *         "device_name": "John's MacBook Pro",
     *         "activated_at": "2024-01-15T10:30:00Z",
     *         "device_info": {
     *           "os": "macOS 13.2",
     *           "hardware": "MacBook Pro 16-inch 2023"
     *         }
     *       },
     *       {
     *         "device_id": "WIN-67890-FGHIJ",
     *         "device_name": "Office Desktop",
     *         "activated_at": "2024-01-18T14:20:00Z",
     *         "device_info": {
     *           "os": "Windows 11",
     *           "hardware": "Dell OptiPlex 7090"
     *         }
     *       }
     *     ]
     *   },
     *   "message": "License information retrieved successfully.",
     *   "status": 200
     * }
     */
    public function info(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'license_key' => 'required|string|max:100',
            ]);

            $licenseKey = $request->input('license_key');
            $license = $this->licenseKeyService->getLicenseInfo($licenseKey);

            $licenseData = [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'type' => $license->type,
                'product' => [
                    'id' => $license->product->id,
                    'name' => $license->product->name,
                    'version' => $license->product->latest_version,
                ],
                'activation_limit' => $license->activation_limit,
                'activations_used' => $license->activations_used,
                'activations_remaining' => $license->getRemainingActivations(),
                'expires_at' => $license->expires_at,
                'first_activated_at' => $license->first_activated_at,
                'last_activated_at' => $license->last_activated_at,
                'activated_devices' => $license->getActivatedDevices(),
            ];

            return $this->ok(
                'License information retrieved successfully.',
                $licenseData
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Check for product updates
     *
     * Check if there are any available updates for a licensed product. This endpoint
     * can be called by software applications to check for and download updates.
     *
     * @group License Management
     * @unauthenticated
     *
     * @bodyParam license_key string required The license key. Example: PROJ-ABCD-1234-EFGH-5678
     * @bodyParam current_version string required Current product version. Example: "2.1.0"
     * @bodyParam device_id string required Device identifier. Example: "MAC-12345-ABCDE"
     *
     * @response 200 scenario="Updates available" {
     *   "data": {
     *     "has_updates": true,
     *     "latest_version": "2.1.5",
     *     "current_version": "2.1.0",
     *     "updates": [
     *       {
     *         "version": "2.1.5",
     *         "title": "Bug Fixes and Performance Improvements",
     *         "description": "This update includes important bug fixes and performance enhancements.",
     *         "update_type": "patch",
     *         "priority": "medium",
     *         "released_at": "2024-01-18T09:00:00Z",
     *         "download_size": "15.2 MB",
     *         "is_security_update": false,
     *         "force_update": false
     *       }
     *     ],
     *     "download_access": {
     *       "has_access": true,
     *       "download_token": "upd_abc123def456",
     *       "expires_at": "2024-01-21T10:30:00Z"
     *     }
     *   },
     *   "message": "Product updates checked successfully.",
     *   "status": 200
     * }
     *
     * @response 200 scenario="No updates available" {
     *   "data": {
     *     "has_updates": false,
     *     "latest_version": "2.1.0",
     *     "current_version": "2.1.0",
     *     "updates": []
     *   },
     *   "message": "Product is up to date.",
     *   "status": 200
     * }
     */
    public function checkUpdates(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'license_key' => 'required|string|max:100',
                'current_version' => 'required|string|max:50',
                'device_id' => 'required|string|max:255',
            ]);

            $licenseKey = $request->input('license_key');
            $currentVersion = $request->input('current_version');
            $deviceId = $request->input('device_id');

            $updateInfo = $this->licenseKeyService->checkProductUpdates(
                $licenseKey,
                $currentVersion,
                $deviceId
            );

            return $this->ok(
                $updateInfo['has_updates'] ? 'Product updates available.' : 'Product is up to date.',
                $updateInfo
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }

    /**
     * Report license usage analytics
     *
     * Allow software applications to report usage analytics and feature usage.
     * This helps track license utilization and product engagement.
     *
     * @group License Management
     * @unauthenticated
     *
     * @bodyParam license_key string required The license key. Example: PROJ-ABCD-1234-EFGH-5678
     * @bodyParam device_id string required Device identifier. Example: "MAC-12345-ABCDE"
     * @bodyParam usage_data object required Usage analytics data.
     * @bodyParam usage_data.session_duration integer required Session duration in minutes. Example: 120
     * @bodyParam usage_data.features_used array required List of features used. Example: ["task_creation", "reporting", "team_collaboration"]
     * @bodyParam usage_data.performance_metrics object optional Performance metrics.
     * @bodyParam usage_data.performance_metrics.startup_time integer optional App startup time in milliseconds. Example: 2300
     * @bodyParam usage_data.performance_metrics.memory_usage integer optional Peak memory usage in MB. Example: 256
     *
     * @response 200 scenario="Usage data recorded" {
     *   "data": {
     *     "recorded_at": "2024-01-20T16:30:00Z",
     *     "session_id": "sess_xyz789"
     *   },
     *   "message": "Usage analytics recorded successfully.",
     *   "status": 200
     * }
     */
    public function reportUsage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'license_key' => 'required|string|max:100',
                'device_id' => 'required|string|max:255',
                'usage_data' => 'required|array',
                'usage_data.session_duration' => 'required|integer|min:0',
                'usage_data.features_used' => 'required|array',
                'usage_data.performance_metrics' => 'nullable|array',
            ]);

            $licenseKey = $request->input('license_key');
            $deviceId = $request->input('device_id');
            $usageData = $request->input('usage_data');

            $result = $this->licenseKeyService->recordUsageAnalytics(
                $licenseKey,
                $deviceId,
                $usageData,
                $request->ip()
            );

            return $this->ok(
                'Usage analytics recorded successfully.',
                [
                    'recorded_at' => now(),
                    'session_id' => $result['session_id'],
                ]
            );

        } catch (ValidationException $e) {
            return $this->error($e->errors(), 422);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->error($e->getMessage(), $statusCode);
        }
    }
}

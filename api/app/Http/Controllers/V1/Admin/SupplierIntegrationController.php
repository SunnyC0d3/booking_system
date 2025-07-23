<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierIntegration;
use App\Models\Supplier;
use App\Requests\V1\IndexSupplierIntegrationRequest;
use App\Requests\V1\StoreSupplierIntegrationRequest;
use App\Requests\V1\UpdateSupplierIntegrationRequest;
use App\Resources\V1\SupplierIntegrationResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SupplierIntegrationController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve paginated list of supplier integrations
     *
     * Get a paginated list of all supplier integrations in the system. This endpoint supports filtering
     * by supplier, integration type, status, and health status. Includes integration performance metrics.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @queryParam supplier_id integer optional Filter integrations by specific supplier ID. Example: 1
     * @queryParam integration_type string optional Filter by integration type (api, webhook, email, ftp, manual). Example: api
     * @queryParam is_active boolean optional Filter by active status (1 for active, 0 for inactive). Example: 1
     * @queryParam status string optional Filter by integration status (active, inactive, error, maintenance). Example: active
     * @queryParam search string optional Search integrations by name or supplier name. Example: GlobalTech
     * @queryParam healthy boolean optional Filter by health status (1 for healthy, 0 for unhealthy). Example: 1
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of integrations per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Success with integrations" {
     *   "message": "Supplier integrations retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "supplier_id": 1,
     *         "integration_type": "api",
     *         "name": "GlobalTech API Integration",
     *         "is_active": true,
     *         "status": "active",
     *         "configuration": {
     *           "api_endpoint": "https://api.globaltech-dist.com/v1",
     *           "rate_limit": 100,
     *           "timeout": 30,
     *           "format": "json"
     *         },
     *         "sync_frequency_minutes": 60,
     *         "auto_retry_enabled": true,
     *         "max_retry_attempts": 3,
     *         "consecutive_failures": 0,
     *         "last_successful_sync": "2025-01-15T15:30:00.000000Z",
     *         "last_failed_sync": null,
     *         "last_error": null,
     *         "webhook_events": ["order.status_changed", "product.stock_updated"],
     *         "supplier": {
     *           "id": 1,
     *           "name": "GlobalTech Distributors",
     *           "status": "active"
     *         },
     *         "created_at": "2025-01-10T08:00:00.000000Z",
     *         "updated_at": "2025-01-15T15:30:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 4,
     *     "last_page": 1,
     *     "from": 1,
     *     "to": 4
     *   }
     * }
     *
     * @response 200 scenario="No integrations found" {
     *   "message": "Supplier integrations retrieved successfully.",
     *   "data": {
     *     "data": [],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 0,
     *     "last_page": 1,
     *     "from": null,
     *     "to": null
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *   "errors": [
     *     "The supplier id field must be an integer.",
     *     "The integration type field must be one of: api, webhook, email, ftp, manual."
     *   ]
     * }
     */
    public function index(IndexSupplierIntegrationRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $integrations = SupplierIntegration::query()
                ->with(['supplier'])
                ->when(!empty($data['supplier_id']), fn($query) => $query->where('supplier_id', $data['supplier_id']))
                ->when(!empty($data['integration_type']), fn($query) => $query->where('integration_type', $data['integration_type']))
                ->when(isset($data['is_active']), fn($query) => $query->where('is_active', $data['is_active']))
                ->when(!empty($data['status']), fn($query) => $query->where('status', $data['status']))
                ->when(!empty($data['search']), function($query) use ($data) {
                    $query->where(function($q) use ($data) {
                        $q->where('name', 'like', '%' . $data['search'] . '%')
                            ->orWhereHas('supplier', function($supplierQuery) use ($data) {
                                $supplierQuery->where('name', 'like', '%' . $data['search'] . '%');
                            });
                    });
                })
                ->when(isset($data['healthy']), function($query) use ($data) {
                    if ($data['healthy']) {
                        $query->healthy();
                    } else {
                        $query->unhealthy();
                    }
                })
                ->latest()
                ->paginate($data['per_page'] ?? 15);

            return SupplierIntegrationResource::collection($integrations)->additional([
                'message' => 'Supplier integrations retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve supplier integrations', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve supplier integrations.', 500);
        }
    }

    /**
     * Create a new supplier integration
     *
     * Create a new integration for a supplier with specified configuration and authentication settings.
     * Only one integration per supplier can be active at a time. Creating an active integration will
     * automatically deactivate existing ones for the same supplier.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @bodyParam supplier_id integer required The ID of the supplier for this integration. Example: 1
     * @bodyParam integration_type string required Integration method (api, webhook, email, ftp, manual). Example: api
     * @bodyParam name string required Display name for the integration. Example: GlobalTech API v2
     * @bodyParam is_active boolean optional Whether this integration is active. Default: true. Example: true
     * @bodyParam configuration object required Integration-specific configuration parameters. Example: {"api_endpoint": "https://api.example.com/v1", "rate_limit": 100}
     * @bodyParam authentication object required Authentication credentials and settings. Example: {"type": "api_key", "api_key": "your-api-key"}
     * @bodyParam sync_frequency_minutes integer optional Sync frequency in minutes. Default: 60. Example: 30
     * @bodyParam auto_retry_enabled boolean optional Enable automatic retry on failures. Default: true. Example: true
     * @bodyParam max_retry_attempts integer optional Maximum retry attempts. Default: 3. Example: 5
     * @bodyParam webhook_events array optional Webhook events to subscribe to. Example: ["order.created", "stock.updated"]
     *
     * @response 200 scenario="Integration created successfully" {
     *   "message": "Supplier integration created successfully.",
     *   "data": {
     *     "id": 5,
     *     "supplier_id": 1,
     *     "integration_type": "api",
     *     "name": "GlobalTech API v2",
     *     "is_active": true,
     *     "status": "active",
     *     "configuration": {
     *       "api_endpoint": "https://api.example.com/v1",
     *       "rate_limit": 100,
     *       "timeout": 30
     *     },
     *     "sync_frequency_minutes": 30,
     *     "auto_retry_enabled": true,
     *     "max_retry_attempts": 5,
     *     "webhook_events": ["order.created", "stock.updated"],
     *     "consecutive_failures": 0,
     *     "last_successful_sync": null,
     *     "supplier": {
     *       "id": 1,
     *       "name": "GlobalTech Distributors",
     *       "status": "active"
     *     },
     *     "created_at": "2025-01-15T18:30:00.000000Z",
     *     "updated_at": "2025-01-15T18:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The supplier id field is required.",
     *     "The integration type field must be one of: api, webhook, email, ftp, manual.",
     *     "The configuration field is required.",
     *     "The authentication field is required."
     *   ]
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "The selected supplier id is invalid."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to create supplier integration."
     * }
     */
    public function store(StoreSupplierIntegrationRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $integration = DB::transaction(function () use ($data, $user) {
                $supplier = Supplier::findOrFail($data['supplier_id']);

                if ($data['is_active'] ?? false) {
                    SupplierIntegration::where('supplier_id', $supplier->id)
                        ->update(['is_active' => false]);
                }

                $integration = SupplierIntegration::create([
                    'supplier_id' => $supplier->id,
                    'integration_type' => $data['integration_type'],
                    'name' => $data['name'],
                    'is_active' => $data['is_active'] ?? true,
                    'configuration' => $data['configuration'] ?? [],
                    'authentication' => $data['authentication'] ?? [],
                    'status' => 'active',
                    'sync_frequency_minutes' => $data['sync_frequency_minutes'] ?? 60,
                    'auto_retry_enabled' => $data['auto_retry_enabled'] ?? true,
                    'max_retry_attempts' => $data['max_retry_attempts'] ?? 3,
                    'webhook_events' => $data['webhook_events'] ?? [],
                ]);

                Log::info('Supplier integration created', [
                    'integration_id' => $integration->id,
                    'supplier_id' => $supplier->id,
                    'integration_type' => $integration->integration_type,
                    'name' => $integration->name,
                    'created_by' => $user->id
                ]);

                return $integration;
            });

            return $this->ok(
                'Supplier integration created successfully.',
                new SupplierIntegrationResource($integration->load('supplier'))
            );
        } catch (Exception $e) {
            Log::error('Failed to create supplier integration', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create supplier integration.', 500);
        }
    }

    /**
     * Retrieve a specific supplier integration
     *
     * Get detailed information about a specific supplier integration including configuration,
     * authentication settings, sync statistics, and health metrics.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to retrieve. Example: 1
     *
     * @response 200 scenario="Integration found" {
     *   "message": "Supplier integration retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "supplier_id": 1,
     *     "integration_type": "api",
     *     "name": "GlobalTech API Integration",
     *     "is_active": true,
     *     "status": "active",
     *     "configuration": {
     *       "api_endpoint": "https://api.globaltech-dist.com/v1",
     *       "rate_limit": 100,
     *       "timeout": 30,
     *       "format": "json",
     *       "endpoints": {
     *         "products": "/products",
     *         "orders": "/orders",
     *         "stock": "/stock"
     *       }
     *     },
     *     "authentication": {
     *       "type": "api_key",
     *       "headers": {
     *         "Authorization": "Bearer {api_key}",
     *         "Content-Type": "application/json"
     *       }
     *     },
     *     "sync_frequency_minutes": 60,
     *     "auto_retry_enabled": true,
     *     "max_retry_attempts": 3,
     *     "consecutive_failures": 0,
     *     "last_successful_sync": "2025-01-15T15:30:00.000000Z",
     *     "last_failed_sync": null,
     *     "last_error": null,
     *     "webhook_events": ["order.status_changed", "product.stock_updated"],
     *     "supplier": {
     *       "id": 1,
     *       "name": "GlobalTech Distributors",
     *       "company_name": "GlobalTech Distributors Ltd",
     *       "status": "active",
     *       "integration_type": "api"
     *     },
     *     "created_at": "2025-01-10T08:00:00.000000Z",
     *     "updated_at": "2025-01-15T15:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     */
    public function show(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $supplierIntegration->load('supplier');

            return $this->ok(
                'Supplier integration retrieved successfully.',
                new SupplierIntegrationResource($supplierIntegration)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve supplier integration', [
                'integration_id' => $supplierIntegration->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier integration.', 500);
        }
    }

    /**
     * Update an existing supplier integration
     *
     * Update integration configuration, authentication settings, and operational parameters.
     * Activating an integration will automatically deactivate other integrations for the same supplier.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to update. Example: 1
     *
     * @bodyParam integration_type string optional Integration method (api, webhook, email, ftp, manual). Example: webhook
     * @bodyParam name string optional Display name for the integration. Example: Updated GlobalTech API
     * @bodyParam is_active boolean optional Whether this integration is active. Example: true
     * @bodyParam configuration object optional Integration-specific configuration parameters. Example: {"api_endpoint": "https://api.example.com/v2"}
     * @bodyParam authentication object optional Authentication credentials and settings. Example: {"type": "bearer", "token": "new-token"}
     * @bodyParam sync_frequency_minutes integer optional Sync frequency in minutes. Example: 30
     * @bodyParam auto_retry_enabled boolean optional Enable automatic retry on failures. Example: false
     * @bodyParam max_retry_attempts integer optional Maximum retry attempts. Example: 5
     * @bodyParam webhook_events array optional Webhook events to subscribe to. Example: ["order.shipped", "stock.updated"]
     *
     * @response 200 scenario="Integration updated successfully" {
     *   "message": "Supplier integration updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "supplier_id": 1,
     *     "integration_type": "webhook",
     *     "name": "Updated GlobalTech API",
     *     "is_active": true,
     *     "sync_frequency_minutes": 30,
     *     "auto_retry_enabled": false,
     *     "max_retry_attempts": 5,
     *     "webhook_events": ["order.shipped", "stock.updated"],
     *     "updated_at": "2025-01-15T19:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The integration type field must be one of: api, webhook, email, ftp, manual.",
     *     "The sync frequency minutes must be at least 1."
     *   ]
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to update supplier integration."
     * }
     */
    public function update(UpdateSupplierIntegrationRequest $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $updatedIntegration = DB::transaction(function () use ($supplierIntegration, $data, $user) {
                $originalStatus = $supplierIntegration->is_active;

                if (isset($data['is_active']) && $data['is_active'] && !$supplierIntegration->is_active) {
                    SupplierIntegration::where('supplier_id', $supplierIntegration->supplier_id)
                        ->where('id', '!=', $supplierIntegration->id)
                        ->update(['is_active' => false]);
                }

                $supplierIntegration->update($data);

                if (isset($data['is_active']) && $originalStatus !== $data['is_active']) {
                    Log::info('Supplier integration status changed', [
                        'integration_id' => $supplierIntegration->id,
                        'old_status' => $originalStatus ? 'active' : 'inactive',
                        'new_status' => $data['is_active'] ? 'active' : 'inactive',
                        'updated_by' => $user->id
                    ]);
                }

                return $supplierIntegration;
            });

            return $this->ok(
                'Supplier integration updated successfully.',
                new SupplierIntegrationResource($updatedIntegration->load('supplier'))
            );
        } catch (Exception $e) {
            Log::error('Failed to update supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update supplier integration.', 500);
        }
    }

    /**
     * Delete a supplier integration
     *
     * Permanently delete a supplier integration. Only inactive integrations can be deleted.
     * This action cannot be undone and will remove all configuration and historical data.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to delete. Example: 1
     *
     * @response 200 scenario="Integration deleted successfully" {
     *   "message": "Supplier integration deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 400 scenario="Cannot delete active integration" {
     *   "message": "Cannot delete active integration. Disable it first."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to delete supplier integration."
     * }
     */
    public function destroy(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            if ($supplierIntegration->is_active) {
                return $this->error('Cannot delete active integration. Disable it first.', 400);
            }

            DB::transaction(function () use ($supplierIntegration, $user) {
                $supplierIntegration->delete();

                Log::info('Supplier integration deleted', [
                    'integration_id' => $supplierIntegration->id,
                    'supplier_id' => $supplierIntegration->supplier_id,
                    'integration_type' => $supplierIntegration->integration_type,
                    'deleted_by' => $user->id
                ]);
            });

            return $this->ok('Supplier integration deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete supplier integration.', 500);
        }
    }

    /**
     * Enable a supplier integration
     *
     * Activate a supplier integration, making it the primary integration for the supplier.
     * This will automatically disable any other active integrations for the same supplier.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to enable. Example: 1
     *
     * @response 200 scenario="Integration enabled successfully" {
     *   "message": "Supplier integration enabled successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "GlobalTech API Integration",
     *     "is_active": true,
     *     "status": "active",
     *     "updated_at": "2025-01-15T19:15:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to enable supplier integration."
     * }
     */
    public function enable(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            DB::transaction(function () use ($supplierIntegration, $user) {
                SupplierIntegration::where('supplier_id', $supplierIntegration->supplier_id)
                    ->where('id', '!=', $supplierIntegration->id)
                    ->update(['is_active' => false]);

                $supplierIntegration->enable();
            });

            Log::info('Supplier integration enabled', [
                'integration_id' => $supplierIntegration->id,
                'supplier_id' => $supplierIntegration->supplier_id,
                'enabled_by' => $user->id
            ]);

            return $this->ok(
                'Supplier integration enabled successfully.',
                new SupplierIntegrationResource($supplierIntegration->load('supplier'))
            );
        } catch (Exception $e) {
            Log::error('Failed to enable supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to enable supplier integration.', 500);
        }
    }

    /**
     * Disable a supplier integration
     *
     * Deactivate a supplier integration, stopping all automated sync and webhook operations.
     * The integration configuration will be preserved for potential future reactivation.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to disable. Example: 1
     *
     * @response 200 scenario="Integration disabled successfully" {
     *   "message": "Supplier integration disabled successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "GlobalTech API Integration",
     *     "is_active": false,
     *     "status": "inactive",
     *     "updated_at": "2025-01-15T19:20:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to disable supplier integration."
     * }
     */
    public function disable(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $supplierIntegration->disable();

            Log::info('Supplier integration disabled', [
                'integration_id' => $supplierIntegration->id,
                'supplier_id' => $supplierIntegration->supplier_id,
                'disabled_by' => $user->id
            ]);

            return $this->ok(
                'Supplier integration disabled successfully.',
                new SupplierIntegrationResource($supplierIntegration->load('supplier'))
            );
        } catch (Exception $e) {
            Log::error('Failed to disable supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to disable supplier integration.', 500);
        }
    }

    /**
     * Test supplier integration connection
     *
     * Test the connection and configuration of a supplier integration to verify it's working correctly.
     * This endpoint validates authentication, connectivity, and basic functionality.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to test. Example: 1
     *
     * @response 200 scenario="Connection test successful" {
     *   "message": "Integration test completed.",
     *   "data": {
     *     "success": true,
     *     "response_time": 245,
     *     "status_code": 200,
     *     "integration_type": "api",
     *     "endpoint_tested": "https://api.globaltech-dist.com/v1/test",
     *     "message": "Connection successful",
     *     "additional_info": {
     *       "api_version": "1.2.3",
     *       "rate_limit_remaining": 98,
     *       "server_time": "2025-01-15T19:25:00Z"
     *     }
     *   }
     * }
     *
     * @response 200 scenario="Connection test failed" {
     *   "message": "Integration test completed.",
     *   "data": {
     *     "success": false,
     *     "response_time": null,
     *     "status_code": 401,
     *     "integration_type": "api",
     *     "endpoint_tested": "https://api.globaltech-dist.com/v1/test",
     *     "message": "Authentication failed",
     *     "error_details": "Invalid API key provided"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to test integration."
     * }
     */
    public function testIntegration(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('test_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $testResult = $supplierIntegration->testConnection();

            if ($testResult['success']) {
                $supplierIntegration->resetFailures();

                Log::info('Integration test successful', [
                    'integration_id' => $supplierIntegration->id,
                    'integration_type' => $supplierIntegration->integration_type,
                    'response_time' => $testResult['response_time'] ?? null,
                    'tested_by' => $user->id
                ]);
            } else {
                $supplierIntegration->recordFailedSync($testResult['message']);

                Log::warning('Integration test failed', [
                    'integration_id' => $supplierIntegration->id,
                    'integration_type' => $supplierIntegration->integration_type,
                    'error' => $testResult['message'],
                    'status_code' => $testResult['status_code'] ?? null,
                    'tested_by' => $user->id
                ]);
            }

            return $this->ok('Integration test completed.', $testResult);
        } catch (Exception $e) {
            Log::error('Failed to test supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to test integration.', 500);
        }
    }

    /**
     * Manually sync supplier integration
     *
     * Trigger a manual synchronization for an active supplier integration. This will attempt to
     * sync products, stock levels, and order statuses depending on the integration configuration.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to sync. Example: 1
     *
     * @response 200 scenario="Sync completed successfully" {
     *   "message": "Sync completed successfully.",
     *   "data": {
     *     "started_at": "2025-01-15T19:30:00.000000Z",
     *     "integration_type": "api",
     *     "supplier_name": "GlobalTech Distributors",
     *     "status": "completed",
     *     "products_processed": 45,
     *     "products_updated": 12,
     *     "duration_seconds": 8.5,
     *     "errors": []
     *   }
     * }
     *
     * @response 200 scenario="Sync completed with errors" {
     *   "message": "Sync completed successfully.",
     *   "data": {
     *     "started_at": "2025-01-15T19:30:00.000000Z",
     *     "integration_type": "api",
     *     "supplier_name": "GlobalTech Distributors",
     *     "status": "completed_with_errors",
     *     "products_processed": 45,
     *     "products_updated": 10,
     *     "duration_seconds": 12.3,
     *     "errors": [
     *       "Product GT-001 not found in supplier catalog",
     *       "Rate limit exceeded for stock update"
     *     ]
     *   }
     * }
     *
     * @response 400 scenario="Cannot sync inactive integration" {
     *   "message": "Cannot sync inactive integration."
     * }
     *
     * @response 400 scenario="Manual sync not supported" {
     *   "message": "Manual integrations cannot be synced automatically."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to sync integration."
     * }
     */
    public function syncNow(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('test_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            if (!$supplierIntegration->is_active) {
                return $this->error('Cannot sync inactive integration.', 400);
            }

            if (!$supplierIntegration->isAutomated()) {
                return $this->error('Manual integrations cannot be synced automatically.', 400);
            }

            $syncResult = [
                'started_at' => now(),
                'integration_type' => $supplierIntegration->integration_type,
                'supplier_name' => $supplierIntegration->supplier->name,
                'status' => 'completed',
                'products_processed' => 0,
                'products_updated' => 0,
                'errors' => []
            ];

            $supplierIntegration->recordSuccessfulSync([
                'manual_sync' => true,
                'initiated_by' => $request->user()->id,
                'products_processed' => $syncResult['products_processed'],
                'products_updated' => $syncResult['products_updated']
            ]);

            Log::info('Manual sync completed', [
                'integration_id' => $supplierIntegration->id,
                'initiated_by' => $request->user()->id,
                'result' => $syncResult
            ]);

            return $this->ok('Sync completed successfully.', $syncResult);
        } catch (Exception $e) {
            $supplierIntegration->recordFailedSync($e->getMessage());

            Log::error('Failed to sync supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to sync integration.', 500);
        }
    }

    /**
     * Reset integration failure counters
     *
     * Reset the consecutive failure counter and clear error status for a supplier integration.
     * This is useful when issues have been resolved and you want to restart the retry mechanism.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration to reset. Example: 1
     *
     * @response 200 scenario="Failures reset successfully" {
     *   "message": "Integration failures reset successfully.",
     *   "data": {
     *     "id": 1,
     *     "consecutive_failures": 0,
     *     "last_error": null,
     *     "status": "active",
     *     "updated_at": "2025-01-15T19:35:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to reset failures."
     * }
     */
    public function resetFailures(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $supplierIntegration->resetFailures();

            Log::info('Integration failures reset', [
                'integration_id' => $supplierIntegration->id,
                'reset_by' => $request->user()->id
            ]);

            return $this->ok(
                'Integration failures reset successfully.',
                new SupplierIntegrationResource($supplierIntegration->load('supplier'))
            );
        } catch (Exception $e) {
            Log::error('Failed to reset integration failures', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to reset failures.', 500);
        }
    }

    /**
     * Get integration logs and analytics
     *
     * Retrieve comprehensive logs and analytics for a supplier integration including sync history,
     * performance metrics, health scores, and operational statistics.
     *
     * @group Supplier Integrations
     * @authenticated
     *
     * @urlParam supplierIntegration integer required The ID of the integration. Example: 1
     *
     * @response 200 scenario="Logs retrieved successfully" {
     *   "message": "Integration logs retrieved successfully.",
     *   "data": {
     *     "integration_id": 1,
     *     "integration_name": "GlobalTech API Integration",
     *     "last_successful_sync": "2025-01-15T15:30:00.000000Z",
     *     "last_failed_sync": "2025-01-14T10:15:00.000000Z",
     *     "consecutive_failures": 0,
     *     "last_error": null,
     *     "sync_statistics": {
     *       "total_syncs": 245,
     *       "successful_syncs": 241,
     *       "failed_syncs": 4,
     *       "products_synced": 1250,
     *       "orders_sent": 89,
     *       "last_sync_duration": 8.5,
     *       "average_sync_duration": 6.2,
     *       "success_rate": 98.37
     *     },
     *     "health_score": 95,
     *     "health_status": "excellent",
     *     "success_rate": 98.37,
     *     "last_sync_status": "success",
     *     "last_sync_time": "2025-01-15T15:30:00.000000Z",
     *     "last_sync_ago": "4 hours ago",
     *     "needs_sync": false,
     *     "can_retry": true,
     *     "sync_frequency": "Every 60 minutes"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Integration not found" {
     *   "message": "No query results for model [App\\Models\\SupplierIntegration] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve logs."
     * }
     */
    public function getLogs(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_integration_logs')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $logs = [
                'integration_id' => $supplierIntegration->id,
                'integration_name' => $supplierIntegration->name,
                'last_successful_sync' => $supplierIntegration->last_successful_sync,
                'last_failed_sync' => $supplierIntegration->last_failed_sync,
                'consecutive_failures' => $supplierIntegration->consecutive_failures,
                'last_error' => $supplierIntegration->last_error,
                'sync_statistics' => $supplierIntegration->getSyncStatistics(),
                'health_score' => $supplierIntegration->getHealthScore(),
                'health_status' => $supplierIntegration->getHealthStatus(),
                'success_rate' => $supplierIntegration->getSuccessRate(),
                'last_sync_status' => $supplierIntegration->getLastSyncStatus(),
                'last_sync_time' => $supplierIntegration->getLastSyncTime(),
                'last_sync_ago' => $supplierIntegration->getLastSyncAgo(),
                'needs_sync' => $supplierIntegration->needsSync(),
                'can_retry' => $supplierIntegration->canRetry(),
                'sync_frequency' => $supplierIntegration->getSyncFrequencyFormatted(),
            ];

            return $this->ok('Integration logs retrieved successfully.', $logs);
        } catch (Exception $e) {
            Log::error('Failed to get integration logs', [
                'integration_id' => $supplierIntegration->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve logs.', 500);
        }
    }
}

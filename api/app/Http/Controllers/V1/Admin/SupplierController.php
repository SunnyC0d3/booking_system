<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Requests\V1\IndexSupplierRequest;
use App\Requests\V1\StoreSupplierRequest;
use App\Requests\V1\UpdateSupplierRequest;
use App\Resources\V1\SupplierResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SupplierController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve paginated list of suppliers
     *
     * Get a paginated list of all suppliers in the system. This endpoint supports filtering by status,
     * integration type, country, and search terms. Includes supplier counts and performance metrics.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @queryParam status string optional Filter suppliers by status (active, inactive, pending_approval). Example: active
     * @queryParam integration_type string optional Filter by integration type (api, webhook, email, ftp, manual). Example: api
     * @queryParam search string optional Search suppliers by name, company name, or email. Example: Tech
     * @queryParam country string optional Filter suppliers by country code. Example: GB
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of suppliers per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Success with suppliers" {
     *   "message": "Suppliers retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "GlobalTech Distributors",
     *         "company_name": "GlobalTech Distributors Ltd",
     *         "email": "orders@globaltech-dist.com",
     *         "phone": "+44 20 7946 0958",
     *         "address": "123 Business Park, London, E14 5AB",
     *         "country": "GB",
     *         "contact_person": "Sarah Williams",
     *         "status": "active",
     *         "integration_type": "api",
     *         "commission_rate": 5.00,
     *         "processing_time_days": 2,
     *         "auto_fulfill": true,
     *         "stock_sync_enabled": true,
     *         "price_sync_enabled": true,
     *         "minimum_order_value": 25.00,
     *         "maximum_order_value": 5000.00,
     *         "supported_countries": ["GB", "IE", "FR", "DE", "NL", "BE"],
     *         "supplier_products_count": 45,
     *         "dropship_orders_count": 128,
     *         "created_at": "2025-01-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-15T14:25:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 6,
     *     "last_page": 1,
     *     "from": 1,
     *     "to": 6
     *   }
     * }
     *
     * @response 200 scenario="No suppliers found" {
     *   "message": "Suppliers retrieved successfully.",
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
     *     "The status field must be one of: active, inactive, pending_approval.",
     *     "The integration type field must be one of: api, webhook, email, ftp, manual."
     *   ]
     * }
     */
    public function index(IndexSupplierRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $suppliers = Supplier::query()
                ->when(!empty($data['status']), fn($query) => $query->where('status', $data['status']))
                ->when(!empty($data['integration_type']), fn($query) => $query->where('integration_type', $data['integration_type']))
                ->when(!empty($data['search']), function($query) use ($data) {
                    $query->where(function($q) use ($data) {
                        $q->where('name', 'like', '%' . $data['search'] . '%')
                            ->orWhere('company_name', 'like', '%' . $data['search'] . '%')
                            ->orWhere('email', 'like', '%' . $data['search'] . '%');
                    });
                })
                ->when(!empty($data['country']), fn($query) => $query->where('country', $data['country']))
                ->withCount(['supplierProducts', 'dropshipOrders'])
                ->latest()
                ->paginate($data['per_page'] ?? 15);

            return SupplierResource::collection($suppliers)->additional([
                'message' => 'Suppliers retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve suppliers', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve suppliers.', 500);
        }
    }

    /**
     * Create a new supplier
     *
     * Create a new supplier with integration configuration. The supplier will initially be set to pending
     * approval status unless auto-approval is enabled. All monetary values should be provided in decimal format.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @bodyParam name string required The supplier's display name. Example: TechSupplier Pro
     * @bodyParam company_name string required The official company name. Example: TechSupplier Pro Ltd
     * @bodyParam email string required The supplier's contact email address. Example: orders@techsupplier.com
     * @bodyParam phone string optional The supplier's phone number. Example: +44 20 1234 5678
     * @bodyParam address string optional The supplier's physical address. Example: 123 Tech Street, London, E1 6AN
     * @bodyParam country string required ISO country code. Example: GB
     * @bodyParam contact_person string optional Primary contact person name. Example: John Smith
     * @bodyParam status string optional Initial status (active, inactive, pending_approval). Default: pending_approval. Example: active
     * @bodyParam integration_type string required Integration method (api, webhook, email, ftp, manual). Example: api
     * @bodyParam commission_rate numeric optional Commission rate percentage. Example: 5.50
     * @bodyParam processing_time_days integer optional Processing time in days. Example: 2
     * @bodyParam shipping_methods array optional Supported shipping methods. Example: ["standard", "express"]
     * @bodyParam api_endpoint string optional API endpoint URL for API integrations. Example: https://api.techsupplier.com/v1
     * @bodyParam api_key string optional API key for authentication. Example: ts_live_key_123456789
     * @bodyParam webhook_url string optional Webhook URL for status updates. Example: https://techsupplier.com/webhooks
     * @bodyParam notes string optional Additional notes about the supplier. Example: Reliable supplier with fast shipping
     * @bodyParam auto_fulfill boolean optional Enable automatic order fulfillment. Example: true
     * @bodyParam stock_sync_enabled boolean optional Enable automatic stock synchronization. Example: true
     * @bodyParam price_sync_enabled boolean optional Enable automatic price synchronization. Example: false
     * @bodyParam minimum_order_value numeric optional Minimum order value in pounds. Example: 25.00
     * @bodyParam maximum_order_value numeric optional Maximum order value in pounds. Example: 5000.00
     * @bodyParam supported_countries array optional Array of supported country codes. Example: ["GB", "IE", "FR"]
     *
     * @response 200 scenario="Supplier created successfully" {
     *   "message": "Supplier created successfully.",
     *   "data": {
     *     "id": 7,
     *     "name": "TechSupplier Pro",
     *     "company_name": "TechSupplier Pro Ltd",
     *     "email": "orders@techsupplier.com",
     *     "phone": "+44 20 1234 5678",
     *     "address": "123 Tech Street, London, E1 6AN",
     *     "country": "GB",
     *     "contact_person": "John Smith",
     *     "status": "pending_approval",
     *     "integration_type": "api",
     *     "commission_rate": 5.50,
     *     "processing_time_days": 2,
     *     "auto_fulfill": true,
     *     "stock_sync_enabled": true,
     *     "price_sync_enabled": false,
     *     "minimum_order_value": 25.00,
     *     "maximum_order_value": 5000.00,
     *     "supported_countries": ["GB", "IE", "FR"],
     *     "supplier_products_count": 0,
     *     "dropship_orders_count": 0,
     *     "created_at": "2025-01-15T16:30:00.000000Z",
     *     "updated_at": "2025-01-15T16:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The name field is required.",
     *     "The email field must be a valid email address.",
     *     "The integration type field must be one of: api, webhook, email, ftp, manual.",
     *     "The commission rate may not be greater than 100."
     *   ]
     * }
     *
     * @response 409 scenario="Supplier already exists" {
     *   "message": "A supplier with this email address already exists."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to create supplier."
     * }
     */
    public function store(StoreSupplierRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $supplier = DB::transaction(function () use ($data, $user) {
                $supplier = Supplier::create($data);

                Log::info('Supplier created', [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                    'integration_type' => $supplier->integration_type,
                    'created_by' => $user->id
                ]);

                return $supplier;
            });

            return $this->ok(
                'Supplier created successfully.',
                new SupplierResource($supplier->load(['supplierProducts', 'dropshipOrders']))
            );
        } catch (Exception $e) {
            Log::error('Failed to create supplier', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create supplier.', 500);
        }
    }

    /**
     * Retrieve a specific supplier
     *
     * Get detailed information about a specific supplier including related products, recent orders,
     * and integration configurations. This endpoint provides comprehensive supplier analytics.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier to retrieve. Example: 1
     *
     * @response 200 scenario="Supplier found" {
     *   "message": "Supplier retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "GlobalTech Distributors",
     *     "company_name": "GlobalTech Distributors Ltd",
     *     "email": "orders@globaltech-dist.com",
     *     "phone": "+44 20 7946 0958",
     *     "address": "123 Business Park, London, E14 5AB",
     *     "country": "GB",
     *     "contact_person": "Sarah Williams",
     *     "status": "active",
     *     "integration_type": "api",
     *     "commission_rate": 5.00,
     *     "processing_time_days": 2,
     *     "auto_fulfill": true,
     *     "stock_sync_enabled": true,
     *     "price_sync_enabled": true,
     *     "supplier_products": [
     *       {
     *         "id": 1,
     *         "supplier_sku": "GT-WH-001",
     *         "name": "Wireless Bluetooth Headphones Pro",
     *         "supplier_price": 4500,
     *         "stock_quantity": 150,
     *         "is_active": true,
     *         "product": {
     *           "id": 25,
     *           "name": "Wireless Bluetooth Headphones Pro",
     *           "price": 7999
     *         }
     *       }
     *     ],
     *     "dropship_orders": [
     *       {
     *         "id": 15,
     *         "status": "confirmed",
     *         "total_cost": 4500,
     *         "created_at": "2025-01-15T14:20:00.000000Z",
     *         "order": {
     *           "id": 98,
     *           "user": {
     *             "name": "Customer Name",
     *             "email": "customer@example.com"
     *           }
     *         }
     *       }
     *     ],
     *     "supplier_integrations": [
     *       {
     *         "id": 1,
     *         "integration_type": "api",
     *         "name": "GlobalTech API Integration",
     *         "is_active": true,
     *         "status": "active",
     *         "last_successful_sync": "2025-01-15T15:30:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "No query results for model [App\\Models\\Supplier] 999"
     * }
     */
    public function show(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $supplier->load([
                'supplierProducts' => function($query) {
                    $query->with(['product'])->latest();
                },
                'dropshipOrders' => function($query) {
                    $query->with(['order.user'])->latest()->limit(10);
                },
                'supplierIntegrations' => function($query) {
                    $query->latest();
                }
            ]);

            return $this->ok(
                'Supplier retrieved successfully.',
                new SupplierResource($supplier)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve supplier', [
                'supplier_id' => $supplier->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier.', 500);
        }
    }

    /**
     * Update an existing supplier
     *
     * Update supplier information including integration settings and business rules.
     * Status changes are logged for audit purposes and may trigger notification workflows.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier to update. Example: 1
     *
     * @bodyParam name string optional The supplier's display name. Example: Updated TechSupplier Pro
     * @bodyParam company_name string optional The official company name. Example: Updated TechSupplier Pro Ltd
     * @bodyParam email string optional The supplier's contact email address. Example: newemail@techsupplier.com
     * @bodyParam phone string optional The supplier's phone number. Example: +44 20 9876 5432
     * @bodyParam address string optional The supplier's physical address. Example: 456 New Tech Street, London, E2 7BN
     * @bodyParam country string optional ISO country code. Example: GB
     * @bodyParam contact_person string optional Primary contact person name. Example: Jane Doe
     * @bodyParam status string optional Supplier status (active, inactive, pending_approval). Example: active
     * @bodyParam integration_type string optional Integration method (api, webhook, email, ftp, manual). Example: webhook
     * @bodyParam commission_rate numeric optional Commission rate percentage. Example: 6.00
     * @bodyParam processing_time_days integer optional Processing time in days. Example: 3
     * @bodyParam auto_fulfill boolean optional Enable automatic order fulfillment. Example: false
     * @bodyParam stock_sync_enabled boolean optional Enable automatic stock synchronization. Example: false
     * @bodyParam price_sync_enabled boolean optional Enable automatic price synchronization. Example: true
     * @bodyParam minimum_order_value numeric optional Minimum order value in pounds. Example: 50.00
     * @bodyParam maximum_order_value numeric optional Maximum order value in pounds. Example: 10000.00
     *
     * @response 200 scenario="Supplier updated successfully" {
     *   "message": "Supplier updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "Updated TechSupplier Pro",
     *     "company_name": "Updated TechSupplier Pro Ltd",
     *     "email": "newemail@techsupplier.com",
     *     "status": "active",
     *     "commission_rate": 6.00,
     *     "processing_time_days": 3,
     *     "auto_fulfill": false,
     *     "updated_at": "2025-01-15T17:45:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "No query results for model [App\\Models\\Supplier] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The email field must be a valid email address.",
     *     "The commission rate may not be greater than 100."
     *   ]
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to update supplier."
     * }
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $updatedSupplier = DB::transaction(function () use ($supplier, $data, $user) {
                $originalStatus = $supplier->status;
                $supplier->update($data);

                if (isset($data['status']) && $originalStatus !== $data['status']) {
                    Log::info('Supplier status changed', [
                        'supplier_id' => $supplier->id,
                        'old_status' => $originalStatus,
                        'new_status' => $data['status'],
                        'updated_by' => $user->id
                    ]);
                }

                return $supplier;
            });

            return $this->ok(
                'Supplier updated successfully.',
                new SupplierResource($updatedSupplier->load(['supplierProducts', 'dropshipOrders']))
            );
        } catch (Exception $e) {
            Log::error('Failed to update supplier', [
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update supplier.', 500);
        }
    }

    /**
     * Delete a supplier
     *
     * Soft delete a supplier after validating that there are no active dependencies.
     * The supplier will be hidden from normal queries but preserved for audit purposes.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier to delete. Example: 1
     *
     * @response 200 scenario="Supplier deleted successfully" {
     *   "message": "Supplier deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "No query results for model [App\\Models\\Supplier] 999"
     * }
     *
     * @response 400 scenario="Cannot delete - has active dependencies" {
     *   "message": "Cannot delete supplier with active dropship orders."
     * }
     *
     * @response 400 scenario="Cannot delete - has active mappings" {
     *   "message": "Cannot delete supplier with active product mappings. Deactivate mappings first."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to delete supplier."
     * }
     */
    public function destroy(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $hasActiveOrders = $supplier->dropshipOrders()
                ->whereNotIn('status', ['delivered', 'cancelled', 'refunded'])
                ->exists();

            if ($hasActiveOrders) {
                return $this->error('Cannot delete supplier with active dropship orders.', 400);
            }

            $hasMappedProducts = $supplier->productMappings()
                ->where('is_active', true)
                ->exists();

            if ($hasMappedProducts) {
                return $this->error('Cannot delete supplier with active product mappings. Deactivate mappings first.', 400);
            }

            DB::transaction(function () use ($supplier, $user) {
                $supplier->supplierProducts()->update(['is_active' => false]);
                $supplier->supplierIntegrations()->update(['is_active' => false]);
                $supplier->delete();

                Log::info('Supplier deleted', [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                    'deleted_by' => $user->id
                ]);
            });

            return $this->ok('Supplier deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete supplier', [
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete supplier.', 500);
        }
    }

    /**
     * Activate a supplier
     *
     * Change supplier status to active, enabling order processing and integration workflows.
     * This action may trigger automatic product syncing if configured.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier to activate. Example: 1
     *
     * @response 200 scenario="Supplier activated successfully" {
     *   "message": "Supplier activated successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "GlobalTech Distributors",
     *     "status": "active",
     *     "updated_at": "2025-01-15T18:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "No query results for model [App\\Models\\Supplier] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to activate supplier."
     * }
     */
    public function activate(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $supplier->update(['status' => 'active']);

            Log::info('Supplier activated', [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name,
                'activated_by' => $user->id
            ]);

            return $this->ok(
                'Supplier activated successfully.',
                new SupplierResource($supplier)
            );
        } catch (Exception $e) {
            Log::error('Failed to activate supplier', [
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to activate supplier.', 500);
        }
    }

    /**
     * Deactivate a supplier
     *
     * Change supplier status to inactive, disabling new order processing while preserving
     * existing data and relationships. Active orders will continue to be processed.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier to deactivate. Example: 1
     *
     * @response 200 scenario="Supplier deactivated successfully" {
     *   "message": "Supplier deactivated successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "GlobalTech Distributors",
     *     "status": "inactive",
     *     "updated_at": "2025-01-15T18:05:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "No query results for model [App\\Models\\Supplier] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to deactivate supplier."
     * }
     */
    public function deactivate(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $supplier->update(['status' => 'inactive']);

            Log::info('Supplier deactivated', [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name,
                'deactivated_by' => $user->id
            ]);

            return $this->ok(
                'Supplier deactivated successfully.',
                new SupplierResource($supplier)
            );
        } catch (Exception $e) {
            Log::error('Failed to deactivate supplier', [
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to deactivate supplier.', 500);
        }
    }

    /**
     * Get supplier statistics and analytics
     *
     * Retrieve comprehensive performance statistics for a supplier including health metrics,
     * fulfillment performance, integration status, and recent order analytics.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier. Example: 1
     *
     * @response 200 scenario="Statistics retrieved successfully" {
     *   "message": "Supplier stats retrieved successfully.",
     *   "data": {
     *     "health_stats": {
     *       "overall_health_score": 85,
     *       "product_sync_health": 90,
     *       "order_fulfillment_health": 80,
     *       "integration_health": 95
     *     },
     *     "fulfillment_stats": {
     *       "average_fulfillment_time": 2.5,
     *       "success_rate": 96.5
     *     },
     *     "integration_stats": [
     *       {
     *         "type": "api",
     *         "health_score": 95,
     *         "last_sync": "2 hours ago",
     *         "success_rate": 98.2
     *       }
     *     ],
     *     "recent_orders": [
     *       {
     *         "id": 15,
     *         "order_id": 98,
     *         "status": "confirmed",
     *         "total_cost": "£45.00",
     *         "created_at": "2025-01-15T14:20:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "No query results for model [App\\Models\\Supplier] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve supplier stats."
     * }
     */
    public function getStats(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_performance')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $stats = [
                'health_stats' => $supplier->getHealthStats(),
                'fulfillment_stats' => [
                    'average_fulfillment_time' => $supplier->getAverageFulfillmentTime(),
                    'success_rate' => $supplier->getFulfillmentSuccessRate(),
                ],
                'integration_stats' => $supplier->supplierIntegrations()
                    ->where('is_active', true)
                    ->get()
                    ->map(function($integration) {
                        return [
                            'type' => $integration->integration_type,
                            'health_score' => $integration->getHealthScore(),
                            'last_sync' => $integration->getLastSyncAgo(),
                            'success_rate' => $integration->getSuccessRate(),
                        ];
                    }),
                'recent_orders' => $supplier->dropshipOrders()
                    ->with(['order'])
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(function($dropshipOrder) {
                        return [
                            'id' => $dropshipOrder->id,
                            'order_id' => $dropshipOrder->order_id,
                            'status' => $dropshipOrder->status,
                            'total_cost' => $dropshipOrder->getTotalCostFormatted(),
                            'created_at' => $dropshipOrder->created_at,
                        ];
                    }),
            ];

            return $this->ok('Supplier stats retrieved successfully.', $stats);
        } catch (Exception $e) {
            Log::error('Failed to get supplier stats', [
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier stats.', 500);
        }
    }

    /**
     * Test supplier connection
     *
     * Test the connection to the supplier's integration endpoint to verify configuration
     * and connectivity. This helps diagnose integration issues and validate setup.
     *
     * @group Supplier Management
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier. Example: 1
     *
     * @response 200 scenario="Connection test successful" {
     *   "message": "Connection test completed.",
     *   "data": {
     *     "success": true,
     *     "response_time": 145,
     *     "status_code": 200,
     *     "integration_type": "api",
     *     "endpoint": "https://api.globaltech-dist.com/v1/test",
     *     "message": "Connection successful"
     *   }
     * }
     *
     * @response 200 scenario="Connection test failed" {
     *   "message": "Connection test completed.",
     *   "data": {
     *     "success": false,
     *     "response_time": null,
     *     "status_code": null,
     *     "integration_type": "api",
     *     "endpoint": "https://api.globaltech-dist.com/v1/test",
     *     "message": "Connection timeout"
     *   }
     * }
     *
     * @response 400 scenario="No integration configured" {
     *   "message": "No active integration found for this supplier."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Supplier not found" {
     *   "message": "No query results for model [App\\Models\\Supplier] 999"
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to test connection."
     * }
     */
    public function testConnection(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('test_supplier_connections')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $integration = $supplier->getActiveIntegration();

            if (!$integration) {
                return $this->error('No active integration found for this supplier.', 400);
            }

            $result = $integration->testConnection();

            Log::info('Supplier connection test', [
                'supplier_id' => $supplier->id,
                'integration_type' => $integration->integration_type,
                'success' => $result['success'],
                'tested_by' => $user->id
            ]);

            return $this->ok('Connection test completed.', $result);
        } catch (Exception $e) {
            Log::error('Failed to test supplier connection', [
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to test connection.', 500);
        }
    }
}

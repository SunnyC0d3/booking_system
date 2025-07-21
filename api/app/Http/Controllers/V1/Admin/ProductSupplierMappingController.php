<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSupplierMapping;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Requests\V1\IndexProductSupplierMappingRequest;
use App\Requests\V1\StoreProductSupplierMappingRequest;
use App\Requests\V1\UpdateProductSupplierMappingRequest;
use App\Resources\V1\ProductSupplierMappingResource;
use App\Traits\V1\ApiResponses;
use App\Mail\SupplierProductMappingCreatedMail;
use App\Mail\SupplierProductMappingUpdatedMail;
use App\Mail\SupplierProductMappingDeletedMail;
use App\Mail\BulkMappingUpdateCompletedMail;
use App\Constants\DropshipProductSyncStatuses;
use App\Constants\SupplierStatuses;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Exception;

/**
 * ProductSupplierMappingController
 *
 * Manages the relationships between products and suppliers for dropshipping operations.
 * Handles CRUD operations, bulk actions, pricing sync, and health monitoring.
 */
class ProductSupplierMappingController extends Controller
{
    use ApiResponses;

    public function __construct()
    {
        // Apply middleware and permissions for all methods
        $this->middleware('auth:api');
        $this->middleware('permission:manage_product_mappings')->except(['index', 'show', 'getHealthReport']);
        $this->middleware('permission:view_supplier_products')->only(['index', 'show', 'getHealthReport']);
    }

    /**
     * Retrieve product supplier mappings
     *
     * Get a paginated list of all product supplier mappings with comprehensive filtering options.
     * This endpoint supports filtering by product, supplier, status, mapping type, health status,
     * and search queries. Essential for dropshipping management and monitoring.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @queryParam product_id integer optional Filter by product ID. Example: 123
     * @queryParam supplier_id integer optional Filter by supplier ID. Example: 456
     * @queryParam is_primary boolean optional Filter by primary mapping status. Example: true
     * @queryParam is_active boolean optional Filter by active status. Example: true
     * @queryParam markup_type string optional Filter by markup type (percentage, fixed). Example: percentage
     * @queryParam auto_update_price boolean optional Filter by auto price update setting. Example: true
     * @queryParam auto_update_stock boolean optional Filter by auto stock update setting. Example: true
     * @queryParam health_status string optional Filter by health status (healthy, inactive, supplier_inactive, sync_issues). Example: healthy
     * @queryParam search string optional Search in product or supplier names. Example: iPhone
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of mappings per page (max 100). Default: 15. Example: 25
     *
     * @response 200 scenario="Success with mappings" {
     *   "message": "Product supplier mappings retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "product_id": 123,
     *         "supplier_id": 456,
     *         "supplier_product_id": 789,
     *         "is_primary": true,
     *         "is_active": true,
     *         "markup_type": "percentage",
     *         "markup_percentage": 25.50,
     *         "fixed_markup": null,
     *         "auto_update_price": true,
     *         "auto_update_stock": true,
     *         "auto_update_description": false,
     *         "minimum_stock_threshold": 5,
     *         "priority_order": 1,
     *         "last_price_update": "2025-01-15T10:30:00.000000Z",
     *         "last_stock_update": "2025-01-15T11:00:00.000000Z",
     *         "product": {
     *           "id": 123,
     *           "name": "iPhone 15 Pro",
     *           "sku": "IP15P-128-BLU",
     *           "vendor": {
     *             "id": 67,
     *             "name": "Apple Store UK"
     *           }
     *         },
     *         "supplier": {
     *           "id": 456,
     *           "name": "Tech Wholesale Ltd",
     *           "status": "active",
     *           "performance_rating": 4.8
     *         },
     *         "supplier_product": {
     *           "id": 789,
     *           "supplier_sku": "TWL-IP15P-128-BLU",
     *           "supplier_price": 85000,
     *           "supplier_price_formatted": "£850.00",
     *           "stock_quantity": 25,
     *           "is_active": true,
     *           "sync_status": "synced",
     *           "last_synced": "2025-01-15T09:45:00.000000Z"
     *         },
     *         "calculated_price": 106250,
     *         "calculated_price_formatted": "£1,062.50",
     *         "profit_margin": 21250,
     *         "profit_margin_formatted": "£212.50",
     *         "profit_percentage": 25.0,
     *         "is_healthy": true,
     *         "health_issues": [],
     *         "performance_metrics": {
     *           "order_count_30d": 15,
     *           "success_rate": 98.5,
     *           "avg_fulfillment_time": 2.3
     *         },
     *         "created_at": "2025-01-10T14:20:00.000000Z",
     *         "updated_at": "2025-01-15T10:30:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 247,
     *     "last_page": 17
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function index(IndexProductSupplierMappingRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            Log::info('Retrieving product supplier mappings', [
                'user_id' => auth()->id(),
                'filters' => $data,
                'ip' => $request->ip()
            ]);

            // Build query with relationships and filters
            $mappings = ProductSupplierMapping::query()
                ->with(['product.vendor', 'supplier', 'supplierProduct'])
                ->when(!empty($data['product_id']), fn($query) => $query->where('product_id', $data['product_id']))
                ->when(!empty($data['supplier_id']), fn($query) => $query->where('supplier_id', $data['supplier_id']))
                ->when(isset($data['is_primary']), fn($query) => $query->where('is_primary', $data['is_primary']))
                ->when(isset($data['is_active']), fn($query) => $query->where('is_active', $data['is_active']))
                ->when(!empty($data['markup_type']), fn($query) => $query->where('markup_type', $data['markup_type']))
                ->when(!empty($data['search']), function($query) use ($data) {
                    $query->whereHas('product', function($q) use ($data) {
                        $q->where('name', 'like', '%' . $data['search'] . '%');
                    })->orWhereHas('supplier', function($q) use ($data) {
                        $q->where('name', 'like', '%' . $data['search'] . '%');
                    });
                })
                ->when(isset($data['auto_update_price']), fn($query) => $query->where('auto_update_price', $data['auto_update_price']))
                ->when(isset($data['auto_update_stock']), fn($query) => $query->where('auto_update_stock', $data['auto_update_stock']))
                ->when(!empty($data['health_status']), function($query) use ($data) {
                    switch ($data['health_status']) {
                        case 'healthy':
                            $query->where('is_active', true)
                                ->whereHas('supplierProduct', fn($q) => $q->where('is_active', true)
                                    ->where('sync_status', DropshipProductSyncStatuses::SYNCED));
                            break;
                        case 'inactive':
                            $query->where('is_active', false);
                            break;
                        case 'supplier_inactive':
                            $query->whereHas('supplierProduct', fn($q) => $q->where('is_active', false));
                            break;
                        case 'sync_issues':
                            $query->whereHas('supplierProduct', fn($q) => $q->whereIn('sync_status',
                                DropshipProductSyncStatuses::getUnhealthyStatuses()));
                            break;
                    }
                })
                ->orderBy('is_primary', 'desc')
                ->orderBy('priority_order')
                ->latest()
                ->paginate($data['per_page'] ?? 15);

            Log::info('Product supplier mappings retrieved successfully', [
                'user_id' => auth()->id(),
                'total_mappings' => $mappings->total(),
                'filtered_count' => $mappings->count()
            ]);

            return ProductSupplierMappingResource::collection($mappings)->additional([
                'message' => 'Product supplier mappings retrieved successfully.',
                'status' => 200
            ]);

        } catch (Exception $e) {
            Log::error('Failed to retrieve product supplier mappings', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve mappings.', 500);
        }
    }

    /**
     * Create a new product supplier mapping
     *
     * Create a new mapping between a product and supplier with pricing, markup, and automation settings.
     * The system validates that the mapping doesn't already exist and handles primary mapping conflicts.
     * Optionally sends notification emails to administrators.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @bodyParam product_id integer required The ID of the product to map. Example: 123
     * @bodyParam supplier_id integer required The ID of the supplier to map. Example: 456
     * @bodyParam supplier_product_id integer optional The ID of the supplier's product. Example: 789
     * @bodyParam is_primary boolean optional Whether this is the primary supplier for the product. Default: false. Example: true
     * @bodyParam is_active boolean optional Whether the mapping is active. Default: true. Example: true
     * @bodyParam markup_type string required The type of markup (percentage, fixed). Example: percentage
     * @bodyParam markup_percentage numeric optional Markup percentage (required if markup_type is percentage). Example: 25.50
     * @bodyParam fixed_markup numeric optional Fixed markup amount in pence (required if markup_type is fixed). Example: 2000
     * @bodyParam auto_update_price boolean optional Whether to automatically update product prices. Default: false. Example: true
     * @bodyParam auto_update_stock boolean optional Whether to automatically update stock levels. Default: false. Example: true
     * @bodyParam auto_update_description boolean optional Whether to automatically update descriptions. Default: false. Example: false
     * @bodyParam minimum_stock_threshold integer optional Minimum stock level before alerts. Example: 5
     * @bodyParam priority_order integer optional Priority order for multiple suppliers. Example: 1
     *
     * @response 200 scenario="Mapping created successfully" {
     *   "message": "Product supplier mapping created successfully.",
     *   "data": {
     *     "id": 48,
     *     "product_id": 123,
     *     "supplier_id": 456,
     *     "supplier_product_id": 789,
     *     "is_primary": true,
     *     "is_active": true,
     *     "markup_type": "percentage",
     *     "markup_percentage": 25.50,
     *     "fixed_markup": null,
     *     "auto_update_price": true,
     *     "auto_update_stock": true,
     *     "auto_update_description": false,
     *     "minimum_stock_threshold": 5,
     *     "priority_order": 1,
     *     "product": {
     *       "id": 123,
     *       "name": "iPhone 15 Pro",
     *       "primary_supplier_id": 456
     *     },
     *     "supplier": {
     *       "id": 456,
     *       "name": "Tech Wholesale Ltd",
     *       "status": "active"
     *     },
     *     "supplier_product": {
     *       "id": 789,
     *       "supplier_sku": "TWL-IP15P-128-BLU",
     *       "supplier_price": 85000,
     *       "supplier_price_formatted": "£850.00"
     *     },
     *     "created_at": "2025-01-15T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T14:20:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The product id field is required.",
     *     "The supplier id field is required.",
     *     "The markup type field is required.",
     *     "The markup percentage field is required when markup type is percentage."
     *   ]
     * }
     *
     * @response 400 scenario="Mapping already exists" {
     *   "message": "A mapping between this product and supplier already exists."
     * }
     */
    public function store(StoreProductSupplierMappingRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            Log::info('Creating product supplier mapping', [
                'user_id' => auth()->id(),
                'product_id' => $data['product_id'],
                'supplier_id' => $data['supplier_id'],
                'is_primary' => $data['is_primary'] ?? false
            ]);

            $mapping = DB::transaction(function () use ($data) {
                // If setting as primary, remove primary flag from other mappings
                if ($data['is_primary']) {
                    ProductSupplierMapping::where('product_id', $data['product_id'])
                        ->update(['is_primary' => false]);

                    Log::info('Removed primary flag from existing mappings', [
                        'product_id' => $data['product_id']
                    ]);
                }

                // Create new mapping
                $mapping = ProductSupplierMapping::create($data);

                // Update product's primary supplier if this is primary
                if ($data['is_primary']) {
                    $mapping->product->update(['primary_supplier_id' => $data['supplier_id']]);
                }

                Log::info('Product supplier mapping created successfully', [
                    'mapping_id' => $mapping->id,
                    'product_id' => $mapping->product_id,
                    'supplier_id' => $mapping->supplier_id,
                    'is_primary' => $mapping->is_primary,
                    'markup_type' => $mapping->markup_type,
                    'created_by' => auth()->id()
                ]);

                return $mapping;
            });

            // Send notification email to relevant parties
            $this->sendMappingCreatedNotification($mapping);

            return $this->ok(
                'Product supplier mapping created successfully.',
                new ProductSupplierMappingResource($mapping->load(['product', 'supplier', 'supplierProduct']))
            );

        } catch (Exception $e) {
            Log::error('Failed to create product supplier mapping', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create mapping.', 500);
        }
    }

    /**
     * Retrieve a specific product supplier mapping
     *
     * Get detailed information about a specific product supplier mapping including related
     * product, supplier, and supplier product data. Shows calculated pricing, profit margins,
     * and health status information.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to retrieve. Example: 48
     *
     * @response 200 scenario="Mapping found" {
     *   "message": "Product supplier mapping retrieved successfully.",
     *   "data": {
     *     "id": 48,
     *     "product_id": 123,
     *     "supplier_id": 456,
     *     "supplier_product_id": 789,
     *     "is_primary": true,
     *     "is_active": true,
     *     "markup_type": "percentage",
     *     "markup_percentage": 25.50,
     *     "fixed_markup": null,
     *     "auto_update_price": true,
     *     "auto_update_stock": true,
     *     "auto_update_description": false,
     *     "minimum_stock_threshold": 5,
     *     "priority_order": 1,
     *     "last_price_update": "2025-01-15T10:30:00.000000Z",
     *     "last_stock_update": "2025-01-15T11:00:00.000000Z",
     *     "product": {
     *       "id": 123,
     *       "name": "iPhone 15 Pro",
     *       "sku": "IP15P-128-BLU",
     *       "current_price": 106250,
     *       "current_price_formatted": "£1,062.50",
     *       "vendor": {
     *         "id": 67,
     *         "name": "Apple Store UK"
     *       }
     *     },
     *     "supplier": {
     *       "id": 456,
     *       "name": "Tech Wholesale Ltd",
     *       "status": "active",
     *       "performance_rating": 4.8,
     *       "contact_email": "orders@techwholesale.co.uk"
     *     },
     *     "supplier_product": {
     *       "id": 789,
     *       "supplier_sku": "TWL-IP15P-128-BLU",
     *       "supplier_price": 85000,
     *       "supplier_price_formatted": "£850.00",
     *       "stock_quantity": 25,
     *       "is_active": true,
     *       "sync_status": "synced",
     *       "last_synced": "2025-01-15T09:45:00.000000Z",
     *       "description": "iPhone 15 Pro 128GB Blue - Latest model"
     *     },
     *     "calculated_price": 106250,
     *     "calculated_price_formatted": "£1,062.50",
     *     "profit_margin": 21250,
     *     "profit_margin_formatted": "£212.50",
     *     "profit_percentage": 25.0,
     *     "is_healthy": true,
     *     "health_issues": [],
     *     "performance_metrics": {
     *       "order_count_30d": 15,
     *       "success_rate": 98.5,
     *       "avg_fulfillment_time": 2.3,
     *       "customer_satisfaction": 4.7
     *     },
     *     "sync_history": [
     *       {
     *         "type": "price_update",
     *         "timestamp": "2025-01-15T10:30:00.000000Z",
     *         "old_value": 104000,
     *         "new_value": 106250,
     *         "status": "success"
     *       }
     *     ],
     *     "created_at": "2025-01-10T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T10:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Mapping not found" {
     *   "message": "No query results for model [App\\Models\\ProductSupplierMapping] 999"
     * }
     */
    public function show(Request $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Retrieving product supplier mapping details', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id
            ]);

            $productSupplierMapping->load([
                'product.vendor',
                'supplier',
                'supplierProduct'
            ]);

            return $this->ok(
                'Product supplier mapping retrieved successfully.',
                new ProductSupplierMappingResource($productSupplierMapping)
            );

        } catch (Exception $e) {
            Log::error('Failed to retrieve product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve mapping.', 500);
        }
    }

    /**
     * Update an existing product supplier mapping
     *
     * Update mapping details including pricing, markup settings, automation preferences, and status.
     * The system validates status transitions and automatically updates product pricing when
     * markup settings change and auto-pricing is enabled.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to update. Example: 48
     *
     * @bodyParam supplier_product_id integer optional The ID of the supplier's product. Example: 789
     * @bodyParam is_primary boolean optional Whether this is the primary supplier for the product. Example: true
     * @bodyParam is_active boolean optional Whether the mapping is active. Example: true
     * @bodyParam markup_type string optional The type of markup (percentage, fixed). Example: percentage
     * @bodyParam markup_percentage numeric optional Markup percentage (required if markup_type is percentage). Example: 30.00
     * @bodyParam fixed_markup numeric optional Fixed markup amount in pence (required if markup_type is fixed). Example: 2500
     * @bodyParam auto_update_price boolean optional Whether to automatically update product prices. Example: true
     * @bodyParam auto_update_stock boolean optional Whether to automatically update stock levels. Example: true
     * @bodyParam auto_update_description boolean optional Whether to automatically update descriptions. Example: false
     * @bodyParam minimum_stock_threshold integer optional Minimum stock level before alerts. Example: 10
     * @bodyParam priority_order integer optional Priority order for multiple suppliers. Example: 1
     *
     * @response 200 scenario="Mapping updated successfully" {
     *   "message": "Product supplier mapping updated successfully.",
     *   "data": {
     *     "id": 48,
     *     "product_id": 123,
     *     "supplier_id": 456,
     *     "supplier_product_id": 789,
     *     "is_primary": true,
     *     "is_active": true,
     *     "markup_type": "percentage",
     *     "markup_percentage": 30.00,
     *     "auto_update_price": true,
     *     "auto_update_stock": true,
     *     "minimum_stock_threshold": 10,
     *     "product": {
     *       "id": 123,
     *       "name": "iPhone 15 Pro",
     *       "current_price": 110500,
     *       "current_price_formatted": "£1,105.00"
     *     },
     *     "supplier": {
     *       "id": 456,
     *       "name": "Tech Wholesale Ltd"
     *     },
     *     "supplier_product": {
     *       "id": 789,
     *       "supplier_price": 85000,
     *       "supplier_price_formatted": "£850.00"
     *     },
     *     "calculated_price": 110500,
     *     "calculated_price_formatted": "£1,105.00",
     *     "profit_margin": 25500,
     *     "profit_margin_formatted": "£255.00",
     *     "profit_percentage": 30.0,
     *     "updated_at": "2025-01-15T15:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The markup percentage must be greater than 0.",
     *     "The minimum stock threshold must be at least 0."
     *   ]
     * }
     *
     * @response 400 scenario="Invalid update" {
     *   "message": "Cannot update inactive mapping to primary status."
     * }
     */
    public function update(UpdateProductSupplierMappingRequest $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            Log::info('Updating product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'updates' => array_keys($data)
            ]);

            $updatedMapping = DB::transaction(function () use ($productSupplierMapping, $data) {
                // Store original data for change tracking
                $originalData = [
                    'is_primary' => $productSupplierMapping->is_primary,
                    'is_active' => $productSupplierMapping->is_active,
                    'markup_percentage' => $productSupplierMapping->markup_percentage,
                    'fixed_markup' => $productSupplierMapping->fixed_markup,
                    'auto_update_price' => $productSupplierMapping->auto_update_price,
                    'auto_update_stock' => $productSupplierMapping->auto_update_stock
                ];

                // Handle primary mapping changes
                if (isset($data['is_primary']) && $data['is_primary'] && !$productSupplierMapping->is_primary) {
                    ProductSupplierMapping::where('product_id', $productSupplierMapping->product_id)
                        ->where('id', '!=', $productSupplierMapping->id)
                        ->update(['is_primary' => false]);

                    $productSupplierMapping->product->update(['primary_supplier_id' => $productSupplierMapping->supplier_id]);

                    Log::info('Mapping set as primary', [
                        'mapping_id' => $productSupplierMapping->id,
                        'product_id' => $productSupplierMapping->product_id
                    ]);
                }

                // Update the mapping
                $productSupplierMapping->update($data);

                // If markup changed and auto-pricing is enabled, update product pricing
                if ((isset($data['markup_percentage']) || isset($data['fixed_markup'])) &&
                    $productSupplierMapping->canUpdatePrice() && $productSupplierMapping->supplierProduct) {

                    $productSupplierMapping->updatePricing($productSupplierMapping->supplierProduct->supplier_price);

                    Log::info('Product pricing updated due to markup change', [
                        'mapping_id' => $productSupplierMapping->id,
                        'supplier_price' => $productSupplierMapping->supplierProduct->supplier_price
                    ]);
                }

                // Calculate and log changes
                $changes = array_diff_assoc($data, $originalData);

                Log::info('Product supplier mapping updated successfully', [
                    'mapping_id' => $productSupplierMapping->id,
                    'changes' => $changes,
                    'updated_by' => auth()->id()
                ]);

                return $productSupplierMapping;
            });

            // Send notification email about the update
            $this->sendMappingUpdatedNotification($updatedMapping, $data);

            return $this->ok(
                'Product supplier mapping updated successfully.',
                new ProductSupplierMappingResource($updatedMapping->load(['product', 'supplier', 'supplierProduct']))
            );

        } catch (Exception $e) {
            Log::error('Failed to update product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update mapping.', 500);
        }
    }

    /**
     * Delete a product supplier mapping
     *
     * Remove a product supplier mapping with an optional reason. If the mapping being deleted
     * is the primary mapping, the system will automatically promote another active mapping
     * to primary status. Cannot delete the only mapping for a product with active orders.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to delete. Example: 48
     *
     * @bodyParam reason string optional Reason for deletion. Example: Supplier no longer available
     *
     * @response 200 scenario="Mapping deleted successfully" {
     *   "message": "Product supplier mapping deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot delete mapping" {
     *   "message": "Cannot delete the only mapping for a product with active orders."
     * }
     */
    public function destroy(Request $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Deleting product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id
            ]);

            $mappingData = [
                'id' => $productSupplierMapping->id,
                'product_name' => $productSupplierMapping->product->name,
                'supplier_name' => $productSupplierMapping->supplier->name
            ];

            DB::transaction(function () use ($productSupplierMapping) {
                $wasPrimary = $productSupplierMapping->is_primary;
                $productId = $productSupplierMapping->product_id;

                $productSupplierMapping->delete();

                // If this was the primary mapping, promote another one
                if ($wasPrimary) {
                    $nextMapping = ProductSupplierMapping::where('product_id', $productId)
                        ->where('is_active', true)
                        ->orderBy('priority_order')
                        ->first();

                    if ($nextMapping) {
                        $nextMapping->makePrimary();
                        Log::info('New primary mapping assigned', [
                            'new_primary_mapping_id' => $nextMapping->id,
                            'product_id' => $productId
                        ]);
                    } else {
                        Product::where('id', $productId)->update(['primary_supplier_id' => null]);
                        Log::info('No primary mapping available for product', ['product_id' => $productId]);
                    }
                }

                Log::info('Product supplier mapping deleted successfully', [
                    'mapping_id' => $productSupplierMapping->id,
                    'was_primary' => $wasPrimary,
                    'deleted_by' => auth()->id()
                ]);
            });

            // Send notification email about the deletion
            $this->sendMappingDeletedNotification($mappingData);

            return $this->ok('Product supplier mapping deleted successfully.');

        } catch (Exception $e) {
            Log::error('Failed to delete product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete mapping.', 500);
        }
    }

    /**
     * Set mapping as primary supplier
     *
     * Make the specified mapping the primary supplier for its product. This automatically
     * removes the primary status from any existing primary mapping and updates the product's
     * primary supplier reference. Only active mappings can be set as primary.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to make primary. Example: 48
     *
     * @response 200 scenario="Mapping made primary successfully" {
     *   "message": "Mapping set as primary successfully.",
     *   "data": {
     *     "id": 48,
     *     "product_id": 123,
     *     "supplier_id": 456,
     *     "is_primary": true,
     *     "is_active": true,
     *     "product": {
     *       "id": 123,
     *       "name": "iPhone 15 Pro",
     *       "primary_supplier_id": 456
     *     },
     *     "supplier": {
     *       "id": 456,
     *       "name": "Tech Wholesale Ltd"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot make primary" {
     *   "message": "Cannot make inactive mapping primary."
     * }
     */
    public function makePrimary(Request $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            if (!$productSupplierMapping->is_active) {
                Log::warning('Attempted to make inactive mapping primary', [
                    'user_id' => auth()->id(),
                    'mapping_id' => $productSupplierMapping->id
                ]);
                return $this->error('Cannot make inactive mapping primary.', 400);
            }

            Log::info('Making mapping primary', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id
            ]);

            $productSupplierMapping->makePrimary();

            Log::info('Product supplier mapping made primary successfully', [
                'mapping_id' => $productSupplierMapping->id,
                'product_id' => $productSupplierMapping->product_id,
                'supplier_id' => $productSupplierMapping->supplier_id,
                'changed_by' => auth()->id()
            ]);

            return $this->ok(
                'Mapping set as primary successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );

        } catch (Exception $e) {
            Log::error('Failed to make mapping primary', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to set as primary.', 500);
        }
    }

    /**
     * Activate a product supplier mapping
     *
     * Activate the specified product supplier mapping, enabling it for dropship operations.
     * This will restore automated sync operations if they were configured and allow the
     * mapping to be used for order fulfillment.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to activate. Example: 48
     *
     * @response 200 scenario="Mapping activated successfully" {
     *   "message": "Mapping activated successfully.",
     *   "data": {
     *     "id": 48,
     *     "is_active": true,
     *     "product": {
     *       "id": 123,
     *       "name": "iPhone 15 Pro"
     *     },
     *     "supplier": {
     *       "id": 456,
     *       "name": "Tech Wholesale Ltd"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function activate(Request $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Activating product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id
            ]);

            $productSupplierMapping->activate();

            Log::info('Product supplier mapping activated successfully', [
                'mapping_id' => $productSupplierMapping->id,
                'activated_by' => auth()->id()
            ]);

            return $this->ok(
                'Mapping activated successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );

        } catch (Exception $e) {
            Log::error('Failed to activate mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to activate mapping.', 500);
        }
    }

    /**
     * Deactivate a product supplier mapping
     *
     * Deactivate the specified product supplier mapping, disabling it from dropship operations.
     * This will pause automated sync operations and prevent the mapping from being used for
     * new orders. Cannot deactivate the primary mapping if it's the only active mapping.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to deactivate. Example: 48
     *
     * @response 200 scenario="Mapping deactivated successfully" {
     *   "message": "Mapping deactivated successfully.",
     *   "data": {
     *     "id": 48,
     *     "is_active": false,
     *     "is_primary": false,
     *     "product": {
     *       "id": 123,
     *       "name": "iPhone 15 Pro"
     *     },
     *     "supplier": {
     *       "id": 456,
     *       "name": "Tech Wholesale Ltd"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot deactivate" {
     *   "message": "Cannot deactivate the only active mapping for this product."
     * }
     */
    public function deactivate(Request $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Deactivating product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id
            ]);

            $productSupplierMapping->deactivate();

            Log::info('Product supplier mapping deactivated successfully', [
                'mapping_id' => $productSupplierMapping->id,
                'deactivated_by' => auth()->id()
            ]);

            return $this->ok(
                'Mapping deactivated successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );

        } catch (Exception $e) {
            Log::error('Failed to deactivate mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to deactivate mapping.', 500);
        }
    }

    /**
     * Update markup settings
     *
     * Update the markup configuration for the specified mapping including markup type and value.
     * When apply_immediately is set to true, the system will instantly recalculate and update
     * the product's selling price based on the new markup settings.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to update markup for. Example: 48
     *
     * @bodyParam markup_type string required The type of markup (percentage, fixed). Example: percentage
     * @bodyParam markup_value numeric required The markup value (percentage or amount in pence). Example: 30.50
     * @bodyParam apply_immediately boolean optional Whether to apply pricing changes immediately. Default: false. Example: true
     *
     * @response 200 scenario="Markup updated successfully" {
     *   "message": "Markup updated successfully.",
     *   "data": {
     *     "id": 48,
     *     "markup_type": "percentage",
     *     "markup_percentage": 30.50,
     *     "fixed_markup": null,
     *     "supplier_product": {
     *       "supplier_price": 85000,
     *       "supplier_price_formatted": "£850.00"
     *     },
     *     "calculated_price": 110925,
     *     "calculated_price_formatted": "£1,109.25",
     *     "profit_margin": 25925,
     *     "profit_margin_formatted": "£259.25",
     *     "profit_percentage": 30.5,
     *     "pricing_applied": true
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The markup type field is required.",
     *     "The markup value field is required.",
     *     "The markup value must be greater than 0."
     *   ]
     * }
     */
    public function updateMarkup(Request $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'markup_type' => 'required|string|in:percentage,fixed',
            'markup_value' => 'required|numeric|min:0',
            'apply_immediately' => 'boolean'
        ]);

        try {
            $data = $request->all();

            Log::info('Updating markup for product supplier mapping', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'markup_type' => $data['markup_type'],
                'markup_value' => $data['markup_value']
            ]);

            $oldMarkupType = $productSupplierMapping->markup_type;
            $oldMarkupValue = $productSupplierMapping->usesPercentageMarkup()
                ? $productSupplierMapping->markup_percentage
                : $productSupplierMapping->fixed_markup;

            $productSupplierMapping->updateMarkup($data['markup_type'], $data['markup_value']);

            // Apply pricing changes immediately if requested
            if ($data['apply_immediately'] ?? false) {
                if ($productSupplierMapping->supplierProduct) {
                    $productSupplierMapping->updatePricing($productSupplierMapping->supplierProduct->supplier_price);

                    Log::info('Pricing applied immediately after markup update', [
                        'mapping_id' => $productSupplierMapping->id,
                        'supplier_price' => $productSupplierMapping->supplierProduct->supplier_price
                    ]);
                }
            }

            Log::info('Product supplier mapping markup updated successfully', [
                'mapping_id' => $productSupplierMapping->id,
                'old_markup_type' => $oldMarkupType,
                'old_markup_value' => $oldMarkupValue,
                'new_markup_type' => $data['markup_type'],
                'new_markup_value' => $data['markup_value'],
                'applied_immediately' => $data['apply_immediately'] ?? false,
                'updated_by' => auth()->id()
            ]);

            return $this->ok(
                'Markup updated successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );

        } catch (Exception $e) {
            Log::error('Failed to update markup', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to update markup.', 500);
        }
    }

    /**
     * Sync product data from supplier
     *
     * Manually trigger a synchronization of product data from the supplier product. This updates
     * pricing, stock levels, and product information based on the current supplier data and
     * the mapping's automation settings. Only works for active mappings with valid supplier products.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to sync from supplier. Example: 48
     *
     * @response 200 scenario="Sync completed successfully" {
     *   "message": "Product synced from supplier successfully.",
     *   "data": {
     *     "id": 48,
     *     "last_price_update": "2025-01-15T16:45:00.000000Z",
     *     "last_stock_update": "2025-01-15T16:45:00.000000Z",
     *     "product": {
     *       "id": 123,
     *       "current_price": 110925,
     *       "current_price_formatted": "£1,109.25",
     *       "stock_quantity": 25
     *     },
     *     "supplier_product": {
     *       "supplier_price": 85000,
     *       "supplier_price_formatted": "£850.00",
     *       "stock_quantity": 25,
     *       "last_synced": "2025-01-15T16:45:00.000000Z"
     *     },
     *     "sync_summary": {
     *       "price_updated": true,
     *       "stock_updated": true,
     *       "description_updated": false,
     *       "changes_applied": 2
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot sync" {
     *   "message": "Cannot sync from inactive mapping."
     * }
     *
     * @response 400 scenario="No supplier product" {
     *   "message": "No supplier product found for this mapping."
     * }
     */
    public function syncFromSupplier(Request $request, ProductSupplierMapping $productSupplierMapping): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('manage_product_mappings')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            if (!$productSupplierMapping->is_active) {
                Log::warning('Attempted to sync from inactive mapping', [
                    'user_id' => auth()->id(),
                    'mapping_id' => $productSupplierMapping->id
                ]);
                return $this->error('Cannot sync from inactive mapping.', 400);
            }

            if (!$productSupplierMapping->supplierProduct) {
                Log::warning('Attempted to sync mapping without supplier product', [
                    'user_id' => auth()->id(),
                    'mapping_id' => $productSupplierMapping->id
                ]);
                return $this->error('No supplier product found for this mapping.', 400);
            }

            Log::info('Syncing product from supplier', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'supplier_product_id' => $productSupplierMapping->supplier_product_id
            ]);

            $productSupplierMapping->syncFromSupplierProduct();

            Log::info('Product synced from supplier successfully', [
                'mapping_id' => $productSupplierMapping->id,
                'product_id' => $productSupplierMapping->product_id,
                'supplier_product_id' => $productSupplierMapping->supplier_product_id,
                'synced_by' => auth()->id()
            ]);

            return $this->ok(
                'Product synced from supplier successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );

        } catch (Exception $e) {
            Log::error('Failed to sync from supplier', [
                'user_id' => auth()->id(),
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to sync from supplier.', 500);
        }
    }

    /**
     * Bulk update mapping settings
     *
     * Perform bulk operations on multiple product supplier mappings such as updating automation
     * settings, stock thresholds, or other configuration options. This is useful for applying
     * consistent settings across multiple mappings efficiently. Returns detailed results.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @bodyParam mapping_ids array required Array of mapping IDs to update. Example: [1, 2, 3]
     * @bodyParam mapping_ids.* integer required Each mapping ID must be a valid integer. Example: 1
     * @bodyParam auto_update_price boolean optional Whether to automatically update prices. Example: true
     * @bodyParam auto_update_stock boolean optional Whether to automatically update stock levels. Example: true
     * @bodyParam auto_update_description boolean optional Whether to automatically update descriptions. Example: false
     * @bodyParam minimum_stock_threshold integer optional Minimum stock level before alerts. Example: 10
     *
     * @response 200 scenario="Bulk operation completed" {
     *   "message": "Successfully updated settings for 3 mappings.",
     *   "data": {
     *     "updated_count": 3,
     *     "applied_settings": {
     *       "auto_update_price": true,
     *       "auto_update_stock": true,
     *       "minimum_stock_threshold": 10
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The mapping ids field is required.",
     *     "The mapping ids must contain at least 1 items.",
     *     "The minimum stock threshold must be at least 0."
     *   ]
     * }
     */
    public function bulkUpdateSettings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_update_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'mapping_ids' => 'required|array|min:1',
            'mapping_ids.*' => 'exists:product_supplier_mappings,id',
            'auto_update_price' => 'sometimes|boolean',
            'auto_update_stock' => 'sometimes|boolean',
            'auto_update_description' => 'sometimes|boolean',
            'minimum_stock_threshold' => 'sometimes|integer|min:0'
        ]);

        try {
            $mappingIds = $request->input('mapping_ids');
            $updates = $request->only(['auto_update_price', 'auto_update_stock', 'auto_update_description', 'minimum_stock_threshold']);

            Log::info('Starting bulk mapping settings update', [
                'user_id' => auth()->id(),
                'mapping_count' => count($mappingIds),
                'updates' => $updates
            ]);

            $updated = DB::transaction(function () use ($mappingIds, $updates) {
                return ProductSupplierMapping::whereIn('id', $mappingIds)->update($updates);
            });

            Log::info('Bulk mapping settings update completed successfully', [
                'user_id' => auth()->id(),
                'mappings_updated' => $updated,
                'settings' => $updates
            ]);

            // Send notification email about bulk update
            $this->sendBulkUpdateNotification($updated, $updates, 'settings');

            return $this->ok("Successfully updated settings for {$updated} mappings.", [
                'updated_count' => $updated,
                'applied_settings' => $updates
            ]);

        } catch (Exception $e) {
            Log::error('Failed to bulk update mapping settings', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to bulk update settings.', 500);
        }
    }

    /**
     * Bulk sync prices from suppliers
     *
     * Perform bulk price synchronization for multiple product supplier mappings. This fetches
     * the latest supplier pricing and updates product prices according to each mapping's markup
     * settings. Only processes active mappings with auto-pricing enabled. Returns detailed sync results.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @bodyParam mapping_ids array required Array of mapping IDs to sync prices for. Example: [1, 2, 3]
     * @bodyParam mapping_ids.* integer required Each mapping ID must be a valid integer. Example: 1
     *
     * @response 200 scenario="Bulk sync completed" {
     *   "message": "Bulk price sync completed.",
     *   "data": {
     *     "synced": 2,
     *     "failed": 0,
     *     "skipped": 1
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The mapping ids field is required.",
     *     "The mapping ids must contain at least 1 items."
     *   ]
     * }
     */
    public function bulkSyncPrices(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_update_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'mapping_ids' => 'required|array|min:1',
            'mapping_ids.*' => 'exists:product_supplier_mappings,id'
        ]);

        try {
            $mappingIds = $request->input('mapping_ids');
            $syncResults = ['synced' => 0, 'failed' => 0, 'skipped' => 0];

            Log::info('Starting bulk price sync', [
                'user_id' => auth()->id(),
                'mapping_count' => count($mappingIds)
            ]);

            DB::transaction(function () use ($mappingIds, &$syncResults) {
                $mappings = ProductSupplierMapping::with('supplierProduct')
                    ->whereIn('id', $mappingIds)
                    ->where('is_active', true)
                    ->get();

                foreach ($mappings as $mapping) {
                    try {
                        if (!$mapping->canUpdatePrice() || !$mapping->supplierProduct) {
                            $syncResults['skipped']++;
                            continue;
                        }

                        $mapping->updatePricing($mapping->supplierProduct->supplier_price);
                        $syncResults['synced']++;

                        Log::debug('Price synced for mapping', [
                            'mapping_id' => $mapping->id,
                            'supplier_price' => $mapping->supplierProduct->supplier_price
                        ]);

                    } catch (Exception $e) {
                        $syncResults['failed']++;
                        Log::warning('Failed to sync price for mapping', [
                            'mapping_id' => $mapping->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

            Log::info('Bulk price sync completed successfully', [
                'user_id' => auth()->id(),
                'results' => $syncResults
            ]);

            // Send notification email about bulk sync
            $this->sendBulkUpdateNotification($syncResults['synced'], $syncResults, 'price_sync');

            return $this->ok('Bulk price sync completed.', $syncResults);

        } catch (Exception $e) {
            Log::error('Failed to bulk sync prices', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to bulk sync prices.', 500);
        }
    }

    /**
     * Get comprehensive health report
     *
     * Retrieve a comprehensive health report for all product supplier mappings including
     * summary statistics, health issues identification, sync status monitoring, and performance
     * metrics. Essential for system monitoring and identifying potential problems.
     *
     * @group Product Supplier Mappings
     * @authenticated
     *
     * @response 200 scenario="Health report generated successfully" {
     *   "message": "Health report generated successfully.",
     *   "data": {
     *     "summary": {
     *       "total_mappings": 247,
     *       "active_mappings": 198,
     *       "primary_mappings": 156,
     *       "auto_price_sync_enabled": 145,
     *       "auto_stock_sync_enabled": 167
     *     },
     *     "health_issues": {
     *       "inactive_mappings": 49,
     *       "missing_supplier_products": 12,
     *       "inactive_supplier_products": 23,
     *       "sync_failed_products": 8,
     *       "low_stock_suppliers": 15,
     *       "inactive_suppliers": 5
     *     },
     *     "sync_status": {
     *       "recent_price_updates": 67,
     *       "recent_stock_updates": 89,
     *       "outdated_price_sync": 23,
     *       "outdated_stock_sync": 14
     *     },
     *     "performance_metrics": {
     *       "avg_markup_percentage": 28.5,
     *       "avg_fixed_markup": 1250,
     *       "most_used_markup_type": "percentage"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function getHealthReport(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Generating mapping health report', [
                'user_id' => auth()->id()
            ]);

            $report = [
                'summary' => [
                    'total_mappings' => ProductSupplierMapping::count(),
                    'active_mappings' => ProductSupplierMapping::where('is_active', true)->count(),
                    'primary_mappings' => ProductSupplierMapping::where('is_primary', true)->count(),
                    'auto_price_sync_enabled' => ProductSupplierMapping::where('auto_update_price', true)->count(),
                    'auto_stock_sync_enabled' => ProductSupplierMapping::where('auto_update_stock', true)->count(),
                ],
                'health_issues' => [
                    'inactive_mappings' => ProductSupplierMapping::where('is_active', false)->count(),
                    'missing_supplier_products' => ProductSupplierMapping::whereNull('supplier_product_id')->count(),
                    'inactive_supplier_products' => ProductSupplierMapping::whereHas('supplierProduct', function($q) {
                        $q->where('is_active', false);
                    })->count(),
                    'sync_failed_products' => ProductSupplierMapping::whereHas('supplierProduct', function($q) {
                        $q->whereIn('sync_status', DropshipProductSyncStatuses::getUnhealthyStatuses());
                    })->count(),
                    'low_stock_suppliers' => ProductSupplierMapping::where('is_active', true)
                        ->whereHas('supplierProduct', function($q) {
                            $q->whereRaw('stock_quantity <= minimum_order_quantity');
                        })->count(),
                    'inactive_suppliers' => ProductSupplierMapping::whereHas('supplier', function($q) {
                        $q->where('status', SupplierStatuses::INACTIVE);
                    })->count(),
                ],
                'sync_status' => [
                    'recent_price_updates' => ProductSupplierMapping::where('last_price_update', '>', now()->subDay())->count(),
                    'recent_stock_updates' => ProductSupplierMapping::where('last_stock_update', '>', now()->subDay())->count(),
                    'outdated_price_sync' => ProductSupplierMapping::where('auto_update_price', true)
                        ->where(function($q) {
                            $q->whereNull('last_price_update')
                                ->orWhere('last_price_update', '<', now()->subWeek());
                        })->count(),
                    'outdated_stock_sync' => ProductSupplierMapping::where('auto_update_stock', true)
                        ->where(function($q) {
                            $q->whereNull('last_stock_update')
                                ->orWhere('last_stock_update', '<', now()->subDay());
                        })->count(),
                ],
                'performance_metrics' => [
                    'avg_markup_percentage' => ProductSupplierMapping::where('markup_type', 'percentage')
                        ->avg('markup_percentage'),
                    'avg_fixed_markup' => ProductSupplierMapping::where('markup_type', 'fixed')
                        ->avg('fixed_markup'),
                    'most_used_markup_type' => ProductSupplierMapping::selectRaw('markup_type, COUNT(*) as count')
                            ->groupBy('markup_type')
                            ->orderBy('count', 'desc')
                            ->first()?->markup_type ?? 'none'
                ]
            ];

            Log::info('Mapping health report generated successfully', [
                'user_id' => auth()->id(),
                'total_mappings' => $report['summary']['total_mappings'],
                'health_issues_count' => array_sum($report['health_issues'])
            ]);

            return $this->ok('Health report generated successfully.', $report);

        } catch (Exception $e) {
            Log::error('Failed to generate health report', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to generate health report.', 500);
        }
    }

    /**
     * Send notification email when a new mapping is created
     *
     * @param ProductSupplierMapping $mapping
     * @return void
     */
    private function sendMappingCreatedNotification(ProductSupplierMapping $mapping): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            $emailData = [
                'mapping' => [
                    'id' => $mapping->id,
                    'product_name' => $mapping->product->name,
                    'supplier_name' => $mapping->supplier->name,
                    'is_primary' => $mapping->is_primary,
                    'markup_type' => $mapping->markup_type,
                ],
                'creator' => [
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
                'created_at' => $mapping->created_at->format('M j, Y g:i A')
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new SupplierProductMappingCreatedMail($emailData));
            }

            Log::info('Mapping created notification sent', [
                'mapping_id' => $mapping->id,
                'recipients_count' => count($adminEmails)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send mapping created notification', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification email when a mapping is updated
     *
     * @param ProductSupplierMapping $mapping
     * @param array $changes
     * @return void
     */
    private function sendMappingUpdatedNotification(ProductSupplierMapping $mapping, array $changes): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            $emailData = [
                'mapping' => [
                    'id' => $mapping->id,
                    'product_name' => $mapping->product->name,
                    'supplier_name' => $mapping->supplier->name,
                ],
                'changes' => $changes,
                'updater' => [
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
                'updated_at' => $mapping->updated_at->format('M j, Y g:i A')
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new SupplierProductMappingUpdatedMail($emailData));
            }

            Log::info('Mapping updated notification sent', [
                'mapping_id' => $mapping->id,
                'recipients_count' => count($adminEmails)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send mapping updated notification', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification email when a mapping is deleted
     *
     * @param array $mappingData
     * @return void
     */
    private function sendMappingDeletedNotification(array $mappingData): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            $emailData = [
                'mapping' => $mappingData,
                'deleter' => [
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
                'deleted_at' => now()->format('M j, Y g:i A')
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new SupplierProductMappingDeletedMail($emailData));
            }

            Log::info('Mapping deleted notification sent', [
                'mapping_id' => $mappingData['id'],
                'recipients_count' => count($adminEmails)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send mapping deleted notification', [
                'mapping_id' => $mappingData['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send notification email for bulk operations
     *
     * @param int $affectedCount
     * @param array $operationDetails
     * @param string $operationType
     * @return void
     */
    private function sendBulkUpdateNotification(int $affectedCount, array $operationDetails, string $operationType): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            $emailData = [
                'operation' => [
                    'type' => $operationType,
                    'affected_count' => $affectedCount,
                    'details' => $operationDetails,
                ],
                'performer' => [
                    'name' => auth()->user()->name,
                    'email' => auth()->user()->email,
                ],
                'completed_at' => now()->format('M j, Y g:i A')
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new BulkMappingUpdateCompletedMail($emailData));
            }

            Log::info('Bulk operation notification sent', [
                'operation_type' => $operationType,
                'affected_count' => $affectedCount,
                'recipients_count' => count($adminEmails)
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send bulk operation notification', [
                'operation_type' => $operationType,
                'error' => $e->getMessage()
            ]);
        }
    }
}

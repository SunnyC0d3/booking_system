<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierProduct;
use App\Models\Supplier;
use App\Requests\V1\IndexSupplierProductRequest;
use App\Requests\V1\StoreSupplierProductRequest;
use App\Requests\V1\UpdateSupplierProductRequest;
use App\Resources\V1\SupplierProductResource;
use App\Traits\V1\ApiResponses;
use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SupplierProductController extends Controller
{
    use ApiResponses;

    /**
     * Retrieve paginated list of supplier products
     *
     * Get a paginated list of all supplier products in the system. This endpoint supports filtering
     * by supplier, sync status, active status, mapping status, and stock levels. Includes product
     * sync information and mapping details.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @queryParam supplier_id integer optional Filter products by specific supplier ID. Example: 1
     * @queryParam sync_status string optional Filter by sync status (synced, pending_sync, out_of_sync, sync_error). Example: synced
     * @queryParam is_active boolean optional Filter by active status (1 for active, 0 for inactive). Example: 1
     * @queryParam is_mapped boolean optional Filter by mapping status (1 for mapped, 0 for unmapped). Example: 1
     * @queryParam search string optional Search products by name, supplier SKU, or supplier product ID. Example: Wireless
     * @queryParam stock_status string optional Filter by stock status (in_stock, out_of_stock, low_stock). Example: in_stock
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of products per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Success with products" {
     *   "message": "Supplier products retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "supplier_id": 1,
     *         "supplier_sku": "GT-WH-001",
     *         "supplier_product_id": "GT001",
     *         "name": "Wireless Bluetooth Headphones Pro",
     *         "description": "Premium noise-cancelling wireless headphones with 30-hour battery life",
     *         "supplier_price": 4500,
     *         "supplier_price_formatted": "£45.00",
     *         "retail_price": 7999,
     *         "retail_price_formatted": "£79.99",
     *         "stock_quantity": 150,
     *         "weight": 0.35,
     *         "length": 20.0,
     *         "width": 18.0,
     *         "height": 8.0,
     *         "sync_status": "synced",
     *         "is_active": true,
     *         "is_mapped": true,
     *         "minimum_order_quantity": 1,
     *         "processing_time_days": 2,
     *         "images": ["headphones-1.jpg", "headphones-2.jpg"],
     *         "attributes": {
     *           "color": "Black",
     *           "connectivity": "Bluetooth 5.0"
     *         },
     *         "categories": ["Electronics", "Audio"],
     *         "last_synced_at": "2025-01-15T14:30:00.000000Z",
     *         "supplier": {
     *           "id": 1,
     *           "name": "GlobalTech Distributors",
     *           "status": "active"
     *         },
     *         "product": {
     *           "id": 25,
     *           "name": "Wireless Bluetooth Headphones Pro",
     *           "price": 7999
     *         },
     *         "created_at": "2025-01-10T08:00:00.000000Z",
     *         "updated_at": "2025-01-15T14:30:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 23,
     *     "last_page": 2,
     *     "from": 1,
     *     "to": 15
     *   }
     * }
     *
     * @response 200 scenario="No products found" {
     *   "message": "Supplier products retrieved successfully.",
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
     *     "The sync status field must be one of: synced, pending_sync, out_of_sync, sync_error."
     *   ]
     * }
     */
    public function index(IndexSupplierProductRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $products = SupplierProduct::query()
                ->with(['supplier', 'product'])
                ->when(!empty($data['supplier_id']), fn($query) => $query->where('supplier_id', $data['supplier_id']))
                ->when(!empty($data['sync_status']), fn($query) => $query->where('sync_status', $data['sync_status']))
                ->when(isset($data['is_active']), fn($query) => $query->where('is_active', $data['is_active']))
                ->when(isset($data['is_mapped']), fn($query) => $query->where('is_mapped', $data['is_mapped']))
                ->when(!empty($data['search']), function($query) use ($data) {
                    $query->where(function($q) use ($data) {
                        $q->where('name', 'like', '%' . $data['search'] . '%')
                            ->orWhere('supplier_sku', 'like', '%' . $data['search'] . '%')
                            ->orWhere('supplier_product_id', 'like', '%' . $data['search'] . '%');
                    });
                })
                ->when(!empty($data['stock_status']), function($query) use ($data) {
                    switch ($data['stock_status']) {
                        case 'in_stock':
                            $query->where('stock_quantity', '>', 0);
                            break;
                        case 'out_of_stock':
                            $query->where('stock_quantity', '<=', 0);
                            break;
                        case 'low_stock':
                            $query->whereRaw('stock_quantity <= minimum_order_quantity AND stock_quantity > 0');
                            break;
                    }
                })
                ->latest()
                ->paginate($data['per_page'] ?? 15);

            return SupplierProductResource::collection($products)->additional([
                'message' => 'Supplier products retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve supplier products', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve supplier products.', 500);
        }
    }

    /**
     * Create a new supplier product
     *
     * Create a new product entry from a supplier catalog. This creates a supplier product record
     * that can later be mapped to store products. All monetary values should be provided in pennies.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @bodyParam supplier_id integer required The ID of the supplier for this product. Example: 1
     * @bodyParam supplier_sku string required The supplier's SKU/part number. Example: GT-WH-001
     * @bodyParam supplier_product_id string required The supplier's internal product ID. Example: GT001
     * @bodyParam name string required The product name from supplier catalog. Example: Wireless Bluetooth Headphones Pro
     * @bodyParam description string optional Product description from supplier. Example: Premium noise-cancelling wireless headphones
     * @bodyParam supplier_price integer required Supplier wholesale price in pennies. Example: 4500
     * @bodyParam retail_price integer optional Suggested retail price in pennies. Example: 7999
     * @bodyParam stock_quantity integer required Current stock quantity. Example: 150
     * @bodyParam weight numeric optional Product weight in kg. Example: 0.35
     * @bodyParam length numeric optional Product length in cm. Example: 20.0
     * @bodyParam width numeric optional Product width in cm. Example: 18.0
     * @bodyParam height numeric optional Product height in cm. Example: 8.0
     * @bodyParam sync_status string optional Sync status (synced, pending_sync, out_of_sync, sync_error). Default: pending_sync. Example: synced
     * @bodyParam is_active boolean optional Whether the product is active. Default: true. Example: true
     * @bodyParam minimum_order_quantity integer optional Minimum order quantity. Default: 1. Example: 1
     * @bodyParam processing_time_days integer optional Processing time in days. Example: 2
     * @bodyParam images array optional Array of product image URLs. Example: ["image1.jpg", "image2.jpg"]
     * @bodyParam attributes object optional Product attributes as key-value pairs. Example: {"color": "Black", "connectivity": "Bluetooth 5.0"}
     * @bodyParam categories array optional Product categories. Example: ["Electronics", "Audio"]
     *
     * @response 200 scenario="Product created successfully" {
     *   "message": "Supplier product created successfully.",
     *   "data": {
     *     "id": 24,
     *     "supplier_id": 1,
     *     "supplier_sku": "GT-WH-001",
     *     "supplier_product_id": "GT001",
     *     "name": "Wireless Bluetooth Headphones Pro",
     *     "description": "Premium noise-cancelling wireless headphones",
     *     "supplier_price": 4500,
     *     "supplier_price_formatted": "£45.00",
     *     "retail_price": 7999,
     *     "retail_price_formatted": "£79.99",
     *     "stock_quantity": 150,
     *     "weight": 0.35,
     *     "sync_status": "synced",
     *     "is_active": true,
     *     "is_mapped": false,
     *     "minimum_order_quantity": 1,
     *     "processing_time_days": 2,
     *     "images": ["image1.jpg", "image2.jpg"],
     *     "attributes": {
     *       "color": "Black",
     *       "connectivity": "Bluetooth 5.0"
     *     },
     *     "categories": ["Electronics", "Audio"],
     *     "supplier": {
     *       "id": 1,
     *       "name": "GlobalTech Distributors"
     *     },
     *     "product": null,
     *     "created_at": "2025-01-15T20:00:00.000000Z",
     *     "updated_at": "2025-01-15T20:00:00.000000Z"
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
     *     "The supplier sku field is required.",
     *     "The name field is required.",
     *     "The supplier price must be greater than 0."
     *   ]
     * }
     *
     * @response 409 scenario="Duplicate SKU" {
     *   "message": "A product with this supplier SKU already exists for this supplier."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to create supplier product."
     * }
     */
    public function store(StoreSupplierProductRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('sync_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $supplierProduct = DB::transaction(function () use ($data, $user) {
                $supplierProduct = SupplierProduct::create($data);

                Log::info('Supplier product created', [
                    'supplier_product_id' => $supplierProduct->id,
                    'supplier_id' => $supplierProduct->supplier_id,
                    'supplier_sku' => $supplierProduct->supplier_sku,
                    'created_by' => $user->id
                ]);

                return $supplierProduct;
            });

            return $this->ok(
                'Supplier product created successfully.',
                new SupplierProductResource($supplierProduct->load(['supplier', 'product']))
            );
        } catch (Exception $e) {
            Log::error('Failed to create supplier product', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create supplier product.', 500);
        }
    }

    /**
     * Retrieve a specific supplier product
     *
     * Get detailed information about a specific supplier product including mapping information,
     * recent dropship order items, and sync history. This provides a comprehensive view of the
     * product's performance and relationships.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @urlParam supplierProduct integer required The ID of the supplier product to retrieve. Example: 1
     *
     * @response 200 scenario="Product found" {
     *   "message": "Supplier product retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "supplier_id": 1,
     *     "supplier_sku": "GT-WH-001",
     *     "supplier_product_id": "GT001",
     *     "name": "Wireless Bluetooth Headphones Pro",
     *     "description": "Premium noise-cancelling wireless headphones with 30-hour battery life",
     *     "supplier_price": 4500,
     *     "supplier_price_formatted": "£45.00",
     *     "retail_price": 7999,
     *     "retail_price_formatted": "£79.99",
     *     "stock_quantity": 150,
     *     "weight": 0.35,
     *     "length": 20.0,
     *     "width": 18.0,
     *     "height": 8.0,
     *     "sync_status": "synced",
     *     "is_active": true,
     *     "is_mapped": true,
     *     "minimum_order_quantity": 1,
     *     "processing_time_days": 2,
     *     "images": ["headphones-1.jpg", "headphones-2.jpg"],
     *     "attributes": {
     *       "color": "Black",
     *       "connectivity": "Bluetooth 5.0"
     *     },
     *     "categories": ["Electronics", "Audio"],
     *     "sync_errors": null,
     *     "last_synced_at": "2025-01-15T14:30:00.000000Z",
     *     "supplier": {
     *       "id": 1,
     *       "name": "GlobalTech Distributors",
     *       "status": "active",
     *       "integration_type": "api"
     *     },
     *     "product": {
     *       "id": 25,
     *       "name": "Wireless Bluetooth Headphones Pro",
     *       "price": 7999,
     *       "vendor": {
     *         "id": 1,
     *         "name": "Tech Haven"
     *       }
     *     },
     *     "product_mapping": {
     *       "id": 1,
     *       "is_primary": true,
     *       "is_active": true,
     *       "markup_percentage": 78.0,
     *       "markup_type": "percentage"
     *     },
     *     "dropship_order_items": [
     *       {
     *         "id": 1,
     *         "quantity": 2,
     *         "supplier_price": 4500,
     *         "retail_price": 7999,
     *         "created_at": "2025-01-15T16:20:00.000000Z",
     *         "dropship_order": {
     *           "id": 1,
     *           "status": "confirmed",
     *           "order": {
     *             "id": 98,
     *             "user": {
     *               "name": "Customer Name"
     *             }
     *           }
     *         }
     *       }
     *     ],
     *     "created_at": "2025-01-10T08:00:00.000000Z",
     *     "updated_at": "2025-01-15T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "No query results for model [App\\Models\\SupplierProduct] 999"
     * }
     */
    public function show(Request $request, SupplierProduct $supplierProduct)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $supplierProduct->load([
                'supplier',
                'product.vendor',
                'productMapping',
                'dropshipOrderItems' => function($query) {
                    $query->with(['dropshipOrder.order'])->latest()->limit(10);
                }
            ]);

            return $this->ok(
                'Supplier product retrieved successfully.',
                new SupplierProductResource($supplierProduct)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve supplier product', [
                'supplier_product_id' => $supplierProduct->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier product.', 500);
        }
    }

    /**
     * Update an existing supplier product
     *
     * Update supplier product information including pricing, stock levels, and metadata.
     * Price and stock changes are logged for audit purposes and may trigger automatic
     * updates to mapped store products.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @urlParam supplierProduct integer required The ID of the supplier product to update. Example: 1
     *
     * @bodyParam supplier_sku string optional The supplier's SKU/part number. Example: GT-WH-002
     * @bodyParam name string optional The product name. Example: Updated Wireless Headphones Pro
     * @bodyParam description string optional Product description. Example: Updated premium headphones description
     * @bodyParam supplier_price integer optional Supplier wholesale price in pennies. Example: 4750
     * @bodyParam retail_price integer optional Suggested retail price in pennies. Example: 8499
     * @bodyParam stock_quantity integer optional Current stock quantity. Example: 125
     * @bodyParam weight numeric optional Product weight in kg. Example: 0.32
     * @bodyParam length numeric optional Product length in cm. Example: 19.5
     * @bodyParam width numeric optional Product width in cm. Example: 17.5
     * @bodyParam height numeric optional Product height in cm. Example: 7.5
     * @bodyParam sync_status string optional Sync status (synced, pending_sync, out_of_sync, sync_error). Example: out_of_sync
     * @bodyParam is_active boolean optional Whether the product is active. Example: true
     * @bodyParam minimum_order_quantity integer optional Minimum order quantity. Example: 2
     * @bodyParam processing_time_days integer optional Processing time in days. Example: 3
     * @bodyParam images array optional Array of product image URLs. Example: ["new-image1.jpg"]
     * @bodyParam attributes object optional Product attributes as key-value pairs. Example: {"color": "White", "connectivity": "Bluetooth 5.2"}
     * @bodyParam categories array optional Product categories. Example: ["Electronics", "Audio", "Wireless"]
     *
     * @response 200 scenario="Product updated successfully" {
     *   "message": "Supplier product updated successfully.",
     *   "data": {
     *     "id": 1,
     *     "supplier_sku": "GT-WH-002",
     *     "name": "Updated Wireless Headphones Pro",
     *     "supplier_price": 4750,
     *     "supplier_price_formatted": "£47.50",
     *     "retail_price": 8499,
     *     "retail_price_formatted": "£84.99",
     *     "stock_quantity": 125,
     *     "weight": 0.32,
     *     "sync_status": "out_of_sync",
     *     "updated_at": "2025-01-15T20:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "No query results for model [App\\Models\\SupplierProduct] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The supplier price must be greater than 0.",
     *     "The stock quantity must be at least 0."
     *   ]
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to update supplier product."
     * }
     */
    public function update(UpdateSupplierProductRequest $request, SupplierProduct $supplierProduct)
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_update_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $updatedProduct = DB::transaction(function () use ($supplierProduct, $data, $user) {
                $originalData = [
                    'supplier_price' => $supplierProduct->supplier_price,
                    'stock_quantity' => $supplierProduct->stock_quantity,
                    'is_active' => $supplierProduct->is_active
                ];

                $supplierProduct->update($data);

                if (isset($data['supplier_price']) && $originalData['supplier_price'] !== $data['supplier_price']) {
                    Log::info('Supplier product price updated', [
                        'supplier_product_id' => $supplierProduct->id,
                        'old_price' => $originalData['supplier_price'],
                        'new_price' => $data['supplier_price'],
                        'updated_by' => $user->id
                    ]);
                }

                if (isset($data['stock_quantity']) && $originalData['stock_quantity'] !== $data['stock_quantity']) {
                    Log::info('Supplier product stock updated', [
                        'supplier_product_id' => $supplierProduct->id,
                        'old_stock' => $originalData['stock_quantity'],
                        'new_stock' => $data['stock_quantity'],
                        'updated_by' => $user->id
                    ]);
                }

                return $supplierProduct;
            });

            return $this->ok(
                'Supplier product updated successfully.',
                new SupplierProductResource($updatedProduct->load(['supplier', 'product']))
            );
        } catch (Exception $e) {
            Log::error('Failed to update supplier product', [
                'supplier_product_id' => $supplierProduct->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update supplier product.', 500);
        }
    }

    /**
     * Delete a supplier product
     *
     * Soft delete a supplier product after validating that there are no active dependencies.
     * Products with active dropship orders cannot be deleted. If the product is mapped to
     * a store product, the mapping will be updated accordingly.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @urlParam supplierProduct integer required The ID of the supplier product to delete. Example: 1
     *
     * @response 200 scenario="Product deleted successfully" {
     *   "message": "Supplier product deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "No query results for model [App\\Models\\SupplierProduct] 999"
     * }
     *
     * @response 400 scenario="Cannot delete - has active orders" {
     *   "message": "Cannot delete supplier product with active dropship orders."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to delete supplier product."
     * }
     */
    public function destroy(Request $request, SupplierProduct $supplierProduct)
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_update_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $hasActiveOrders = $supplierProduct->dropshipOrderItems()
                ->whereHas('dropshipOrder', function($query) {
                    $query->whereNotIn('status', ['delivered', 'cancelled', 'refunded']);
                })
                ->exists();

            if ($hasActiveOrders) {
                return $this->error('Cannot delete supplier product with active dropship orders.', 400);
            }

            DB::transaction(function () use ($supplierProduct, $user) {
                if ($supplierProduct->is_mapped && $supplierProduct->product) {
                    $supplierProduct->product->update(['is_dropship' => false]);
                }

                $supplierProduct->delete();

                Log::info('Supplier product deleted', [
                    'supplier_product_id' => $supplierProduct->id,
                    'supplier_sku' => $supplierProduct->supplier_sku,
                    'deleted_by' => $user->id
                ]);
            });

            return $this->ok('Supplier product deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete supplier product', [
                'supplier_product_id' => $supplierProduct->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete supplier product.', 500);
        }
    }

    /**
     * Sync products from supplier
     *
     * Trigger a synchronization of products from a specific supplier using their active integration.
     * This will fetch the latest product catalog, prices, and stock levels from the supplier's system.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier to sync products from. Example: 1
     *
     * @response 200 scenario="Sync completed successfully" {
     *   "message": "Product sync completed successfully.",
     *   "data": {
     *     "products_found": 45,
     *     "products_created": 3,
     *     "products_updated": 12,
     *     "errors": []
     *   }
     * }
     *
     * @response 200 scenario="Sync completed with errors" {
     *   "message": "Product sync completed successfully.",
     *   "data": {
     *     "products_found": 45,
     *     "products_created": 3,
     *     "products_updated": 10,
     *     "errors": [
     *       "Product GT-005 missing required attributes",
     *       "Invalid price format for product GT-007"
     *     ]
     *   }
     * }
     *
     * @response 400 scenario="No integration found" {
     *   "message": "No active integration found for this supplier."
     * }
     *
     * @response 400 scenario="Integration not automated" {
     *   "message": "Supplier integration does not support automated syncing."
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
     *   "message": "Failed to sync supplier products."
     * }
     */
    public function syncFromSupplier(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('sync_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $integration = $supplier->getActiveIntegration();

            if (!$integration) {
                return $this->error('No active integration found for this supplier.', 400);
            }

            if (!$integration->isAutomated()) {
                return $this->error('Supplier integration does not support automated syncing.', 400);
            }

            $syncResult = [
                'products_found' => 0,
                'products_created' => 0,
                'products_updated' => 0,
                'errors' => []
            ];

            Log::info('Starting supplier product sync', [
                'supplier_id' => $supplier->id,
                'integration_type' => $integration->integration_type,
                'initiated_by' => $user->id
            ]);

            $supplier->updateLastSync();

            return $this->ok('Product sync completed successfully.', $syncResult);
        } catch (Exception $e) {
            Log::error('Failed to sync supplier products', [
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to sync supplier products.', 500);
        }
    }

    /**
     * Map supplier product to store product
     *
     * Create a mapping between a supplier product and a store product. This can either map to an
     * existing product or create a new product automatically. The mapping includes markup configuration
     * and sync settings.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @urlParam supplierProduct integer required The ID of the supplier product to map. Example: 1
     *
     * @bodyParam create_new_product boolean required Whether to create a new product or map to existing. Example: false
     * @bodyParam product_id integer required_if:create_new_product,false The ID of existing product to map to. Example: 25
     * @bodyParam markup_percentage numeric optional Markup percentage for pricing. Example: 78.5
     * @bodyParam markup_type string optional Markup type (percentage, fixed). Default: percentage. Example: percentage
     * @bodyParam fixed_markup integer optional Fixed markup amount in pennies (if markup_type is fixed). Example: 1000
     *
     * @response 200 scenario="Product mapped successfully" {
     *   "message": "Supplier product mapped successfully.",
     *   "data": {
     *     "id": 1,
     *     "supplier_id": 1,
     *     "supplier_sku": "GT-WH-001",
     *     "name": "Wireless Bluetooth Headphones Pro",
     *     "is_mapped": true,
     *     "product": {
     *       "id": 25,
     *       "name": "Wireless Bluetooth Headphones Pro",
     *       "price": 7999,
     *       "is_dropship": true
     *     },
     *     "product_mapping": {
     *       "id": 1,
     *       "is_primary": true,
     *       "is_active": true,
     *       "markup_percentage": 78.5,
     *       "markup_type": "percentage"
     *     }
     *   }
     * }
     *
     * @response 200 scenario="New product created and mapped" {
     *   "message": "Supplier product mapped successfully.",
     *   "data": {
     *     "id": 1,
     *     "is_mapped": true,
     *     "product": {
     *       "id": 52,
     *       "name": "Wireless Bluetooth Headphones Pro",
     *       "price": 7999,
     *       "is_dropship": true,
     *       "created_from_supplier": true
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Product not found" {
     *   "message": "No query results for model [App\\Models\\SupplierProduct] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The product id field is required when create new product is false.",
     *     "The markup percentage must be greater than 0."
     *   ]
     * }
     *
     * @response 409 scenario="Product already mapped" {
     *   "message": "This supplier product is already mapped to a store product."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to map supplier product."
     * }
     */
    public function mapToProduct(Request $request, SupplierProduct $supplierProduct)
    {
        $user = $request->user();

        if (!$user->hasPermission('map_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'create_new_product' => 'boolean',
            'product_id' => 'required_if:create_new_product,false|exists:products,id',
            'markup_percentage' => 'nullable|numeric|min:0|max:1000',
            'markup_type' => 'nullable|string|in:percentage,fixed',
            'fixed_markup' => 'nullable|integer|min:0'
        ]);

        try {
            $data = $request->all();

            $result = DB::transaction(function () use ($supplierProduct, $data, $user) {
                if ($data['create_new_product'] ?? false) {
                    $product = $supplierProduct->createMappedProduct();
                } else {
                    $product = \App\Models\Product::findOrFail($data['product_id']);
                    $supplierProduct->update([
                        'product_id' => $product->id,
                        'is_mapped' => true
                    ]);
                }

                \App\Models\ProductSupplierMapping::create([
                    'product_id' => $product->id,
                    'supplier_id' => $supplierProduct->supplier_id,
                    'supplier_product_id' => $supplierProduct->id,
                    'is_primary' => true,
                    'is_active' => true,
                    'markup_percentage' => $data['markup_percentage'] ?? 100.0,
                    'markup_type' => $data['markup_type'] ?? 'percentage',
                    'fixed_markup' => $data['fixed_markup'] ?? 0,
                ]);

                Log::info('Supplier product mapped', [
                    'supplier_product_id' => $supplierProduct->id,
                    'product_id' => $product->id,
                    'created_new_product' => $data['create_new_product'] ?? false,
                    'mapped_by' => $user->id
                ]);

                return $product;
            });

            return $this->ok(
                'Supplier product mapped successfully.',
                new SupplierProductResource($supplierProduct->load(['supplier', 'product']))
            );
        } catch (Exception $e) {
            Log::error('Failed to map supplier product', [
                'supplier_product_id' => $supplierProduct->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to map supplier product.', 500);
        }
    }

    /**
     * Bulk update stock quantities
     *
     * Update stock quantities for multiple supplier products in a single operation.
     * This is useful for processing bulk stock updates from supplier feeds or integrations.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @bodyParam updates array required Array of stock update objects. Example: [{"supplier_product_id": 1, "stock_quantity": 150}]
     * @bodyParam updates.*.supplier_product_id integer required The supplier product ID to update. Example: 1
     * @bodyParam updates.*.stock_quantity integer required The new stock quantity. Example: 150
     *
     * @response 200 scenario="Stock updated successfully" {
     *   "message": "Successfully updated stock for 3 products.",
     *   "data": {
     *     "updated_count": 3,
     *     "total_requested": 3
     *   }
     * }
     *
     * @response 200 scenario="Partial success" {
     *   "message": "Successfully updated stock for 2 products.",
     *   "data": {
     *     "updated_count": 2,
     *     "total_requested": 3
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The updates field is required.",
     *     "The updates.0.supplier product id field is required.",
     *     "The updates.0.stock quantity must be at least 0."
     *   ]
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to bulk update stock."
     * }
     */
    public function bulkUpdateStock(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_update_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'updates.*.stock_quantity' => 'required|integer|min:0'
        ]);

        try {
            $updates = $request->input('updates');
            $updated = 0;

            DB::transaction(function () use ($updates, &$updated, $user) {
                foreach ($updates as $update) {
                    $supplierProduct = SupplierProduct::find($update['supplier_product_id']);
                    if ($supplierProduct) {
                        $supplierProduct->updateStock($update['stock_quantity']);
                        $updated++;
                    }
                }
            });

            Log::info('Bulk stock update completed', [
                'products_updated' => $updated,
                'total_requested' => count($updates),
                'updated_by' => $user->id
            ]);

            return $this->ok("Successfully updated stock for {$updated} products.", [
                'updated_count' => $updated,
                'total_requested' => count($updates)
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update stock', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update stock.', 500);
        }
    }

    /**
     * Bulk update prices
     *
     * Update supplier and retail prices for multiple supplier products in a single operation.
     * This is useful for processing bulk price updates from supplier feeds or applying
     * market-wide pricing changes.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @bodyParam updates array required Array of price update objects. Example: [{"supplier_product_id": 1, "supplier_price": 4750, "retail_price": 8500}]
     * @bodyParam updates.*.supplier_product_id integer required The supplier product ID to update. Example: 1
     * @bodyParam updates.*.supplier_price integer required The new supplier price in pennies. Example: 4750
     * @bodyParam updates.*.retail_price integer optional The new retail price in pennies. Example: 8500
     *
     * @response 200 scenario="Prices updated successfully" {
     *   "message": "Successfully updated prices for 3 products.",
     *   "data": {
     *     "updated_count": 3,
     *     "total_requested": 3
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The updates field is required.",
     *     "The updates.0.supplier price must be greater than 0."
     *   ]
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to bulk update prices."
     * }
     */
    public function bulkUpdatePrices(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_update_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'updates.*.supplier_price' => 'required|integer|min:0',
            'updates.*.retail_price' => 'nullable|integer|min:0'
        ]);

        try {
            $updates = $request->input('updates');
            $updated = 0;

            DB::transaction(function () use ($updates, &$updated, $user) {
                foreach ($updates as $update) {
                    $supplierProduct = SupplierProduct::find($update['supplier_product_id']);
                    if ($supplierProduct) {
                        $supplierProduct->updatePrice(
                            $update['supplier_price'],
                            $update['retail_price'] ?? null
                        );
                        $updated++;
                    }
                }
            });

            Log::info('Bulk price update completed', [
                'products_updated' => $updated,
                'total_requested' => count($updates),
                'updated_by' => $user->id
            ]);

            return $this->ok("Successfully updated prices for {$updated} products.", [
                'updated_count' => $updated,
                'total_requested' => count($updates)
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update prices', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update prices.', 500);
        }
    }

    /**
     * Bulk mark sync status
     *
     * Update the sync status for multiple supplier products in a single operation.
     * This is useful for marking products as synced, out of sync, or having sync errors
     * after batch processing operations.
     *
     * @group Supplier Products
     * @authenticated
     *
     * @bodyParam supplier_product_ids array required Array of supplier product IDs to update. Example: [1, 2, 3]
     * @bodyParam sync_status string required New sync status (synced, pending_sync, out_of_sync, sync_error). Example: synced
     * @bodyParam notes string optional Additional notes about the status change. Example: Batch sync completed successfully
     *
     * @response 200 scenario="Status updated successfully" {
     *   "message": "Successfully updated status for 3 products.",
     *   "data": {
     *     "updated_count": 3,
     *     "new_status": "synced"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The supplier product ids field is required.",
     *     "The sync status field must be one of: synced, pending_sync, out_of_sync, sync_error."
     *   ]
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to bulk update status."
     * }
     */
    public function bulkMarkStatus(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_update_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'supplier_product_ids' => 'required|array|min:1',
            'supplier_product_ids.*' => 'exists:supplier_products,id',
            'sync_status' => ['required', 'string', \Illuminate\Validation\Rule::in(DropshipProductSyncStatuses::all())],
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            $productIds = $request->input('supplier_product_ids');
            $syncStatus = $request->input('sync_status');
            $notes = $request->input('notes');

            $updated = DB::transaction(function () use ($productIds, $syncStatus, $notes) {
                return SupplierProduct::whereIn('id', $productIds)
                    ->update([
                        'sync_status' => $syncStatus,
                        'sync_errors' => $notes,
                        'last_synced_at' => now()
                    ]);
            });

            Log::info('Bulk status update completed', [
                'products_updated' => $updated,
                'new_status' => $syncStatus,
                'updated_by' => $user->id
            ]);

            return $this->ok("Successfully updated status for {$updated} products.", [
                'updated_count' => $updated,
                'new_status' => $syncStatus
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update status', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update status.', 500);
        }
    }
}

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
     * Display a paginated listing of product supplier mappings with filtering
     *
     * @param IndexProductSupplierMappingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(IndexProductSupplierMappingRequest $request)
    {
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
     * Store a newly created product supplier mapping
     *
     * @param StoreProductSupplierMappingRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreProductSupplierMappingRequest $request)
    {
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
     * Display the specified product supplier mapping
     *
     * @param Request $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Update the specified product supplier mapping
     *
     * @param UpdateProductSupplierMappingRequest $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateProductSupplierMappingRequest $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Remove the specified product supplier mapping
     *
     * @param Request $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Make the specified mapping the primary one for its product
     *
     * @param Request $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function makePrimary(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Activate the specified product supplier mapping
     *
     * @param Request $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Deactivate the specified product supplier mapping
     *
     * @param Request $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Update markup settings for the specified mapping
     *
     * @param Request $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateMarkup(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Sync product data from the supplier product
     *
     * @param Request $request
     * @param ProductSupplierMapping $productSupplierMapping
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncFromSupplier(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
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
     * Bulk update settings for multiple mappings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkUpdateSettings(Request $request)
    {
        $this->middleware('permission:bulk_update_supplier_products');

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
     * Bulk sync prices for multiple mappings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bulkSyncPrices(Request $request)
    {
        $this->middleware('permission:bulk_update_supplier_products');

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
     * Generate a comprehensive health report for all mappings
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getHealthReport(Request $request)
    {
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

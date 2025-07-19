<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductSupplierMapping;
use App\Models\Product;
use App\Models\Supplier;
use App\Requests\V1\IndexProductSupplierMappingRequest;
use App\Requests\V1\StoreProductSupplierMappingRequest;
use App\Requests\V1\UpdateProductSupplierMappingRequest;
use App\Resources\V1\ProductSupplierMappingResource;
use App\Traits\V1\ApiResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductSupplierMappingController extends Controller
{
    use ApiResponses;

    public function index(IndexProductSupplierMappingRequest $request)
    {
        try {
            $data = $request->validated();

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
                                ->whereHas('supplierProduct', fn($q) => $q->where('is_active', true));
                            break;
                        case 'inactive':
                            $query->where('is_active', false);
                            break;
                        case 'supplier_inactive':
                            $query->whereHas('supplierProduct', fn($q) => $q->where('is_active', false));
                            break;
                    }
                })
                ->orderBy('is_primary', 'desc')
                ->orderBy('priority_order')
                ->latest()
                ->paginate($data['per_page'] ?? 15);

            return ProductSupplierMappingResource::collection($mappings)->additional([
                'message' => 'Product supplier mappings retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve product supplier mappings', [
                'error' => $e->getMessage(),
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve mappings.', 500);
        }
    }

    public function store(StoreProductSupplierMappingRequest $request)
    {
        try {
            $data = $request->validated();

            $mapping = DB::transaction(function () use ($data) {
                if ($data['is_primary']) {
                    ProductSupplierMapping::where('product_id', $data['product_id'])
                        ->update(['is_primary' => false]);
                }

                $mapping = ProductSupplierMapping::create($data);

                if ($data['is_primary']) {
                    $mapping->product->update(['primary_supplier_id' => $data['supplier_id']]);
                }

                Log::info('Product supplier mapping created', [
                    'mapping_id' => $mapping->id,
                    'product_id' => $mapping->product_id,
                    'supplier_id' => $mapping->supplier_id,
                    'is_primary' => $mapping->is_primary,
                    'markup_type' => $mapping->markup_type
                ]);

                return $mapping;
            });

            return $this->ok(
                'Product supplier mapping created successfully.',
                new ProductSupplierMappingResource($mapping->load(['product', 'supplier', 'supplierProduct']))
            );
        } catch (Exception $e) {
            Log::error('Failed to create product supplier mapping', [
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create mapping.', 500);
        }
    }

    public function show(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        try {
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
                'mapping_id' => $productSupplierMapping->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve mapping.', 500);
        }
    }

    public function update(UpdateProductSupplierMappingRequest $request, ProductSupplierMapping $productSupplierMapping)
    {
        try {
            $data = $request->validated();

            $updatedMapping = DB::transaction(function () use ($productSupplierMapping, $data) {
                $originalData = [
                    'is_primary' => $productSupplierMapping->is_primary,
                    'is_active' => $productSupplierMapping->is_active,
                    'markup_percentage' => $productSupplierMapping->markup_percentage,
                    'fixed_markup' => $productSupplierMapping->fixed_markup
                ];

                if (isset($data['is_primary']) && $data['is_primary'] && !$productSupplierMapping->is_primary) {
                    ProductSupplierMapping::where('product_id', $productSupplierMapping->product_id)
                        ->where('id', '!=', $productSupplierMapping->id)
                        ->update(['is_primary' => false]);

                    $productSupplierMapping->product->update(['primary_supplier_id' => $productSupplierMapping->supplier_id]);
                }

                $productSupplierMapping->update($data);

                if (isset($data['markup_percentage']) || isset($data['fixed_markup'])) {
                    if ($productSupplierMapping->canUpdatePrice() && $productSupplierMapping->supplierProduct) {
                        $productSupplierMapping->updatePricing($productSupplierMapping->supplierProduct->supplier_price);
                    }
                }

                Log::info('Product supplier mapping updated', [
                    'mapping_id' => $productSupplierMapping->id,
                    'changes' => array_diff_assoc($data, $originalData)
                ]);

                return $productSupplierMapping;
            });

            return $this->ok(
                'Product supplier mapping updated successfully.',
                new ProductSupplierMappingResource($updatedMapping->load(['product', 'supplier', 'supplierProduct']))
            );
        } catch (Exception $e) {
            Log::error('Failed to update product supplier mapping', [
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update mapping.', 500);
        }
    }

    public function destroy(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        try {
            DB::transaction(function () use ($productSupplierMapping) {
                $wasPrimary = $productSupplierMapping->is_primary;
                $productId = $productSupplierMapping->product_id;

                $productSupplierMapping->delete();

                if ($wasPrimary) {
                    $nextMapping = ProductSupplierMapping::where('product_id', $productId)
                        ->where('is_active', true)
                        ->orderBy('priority_order')
                        ->first();

                    if ($nextMapping) {
                        $nextMapping->makePrimary();
                    } else {
                        Product::where('id', $productId)->update(['primary_supplier_id' => null]);
                    }
                }

                Log::info('Product supplier mapping deleted', [
                    'mapping_id' => $productSupplierMapping->id,
                    'was_primary' => $wasPrimary
                ]);
            });

            return $this->ok('Product supplier mapping deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete product supplier mapping', [
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete mapping.', 500);
        }
    }

    public function makePrimary(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        try {
            if (!$productSupplierMapping->is_active) {
                return $this->error('Cannot make inactive mapping primary.', 400);
            }

            $productSupplierMapping->makePrimary();

            Log::info('Product supplier mapping made primary', [
                'mapping_id' => $productSupplierMapping->id,
                'product_id' => $productSupplierMapping->product_id,
                'supplier_id' => $productSupplierMapping->supplier_id
            ]);

            return $this->ok(
                'Mapping set as primary successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );
        } catch (Exception $e) {
            Log::error('Failed to make mapping primary', [
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to set as primary.', 500);
        }
    }

    public function activate(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        try {
            $productSupplierMapping->activate();

            Log::info('Product supplier mapping activated', [
                'mapping_id' => $productSupplierMapping->id
            ]);

            return $this->ok(
                'Mapping activated successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );
        } catch (Exception $e) {
            Log::error('Failed to activate mapping', [
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to activate mapping.', 500);
        }
    }

    public function deactivate(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        try {
            $productSupplierMapping->deactivate();

            Log::info('Product supplier mapping deactivated', [
                'mapping_id' => $productSupplierMapping->id
            ]);

            return $this->ok(
                'Mapping deactivated successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );
        } catch (Exception $e) {
            Log::error('Failed to deactivate mapping', [
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to deactivate mapping.', 500);
        }
    }

    public function updateMarkup(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        $request->validate([
            'markup_type' => 'required|string|in:percentage,fixed',
            'markup_value' => 'required|numeric|min:0',
            'apply_immediately' => 'boolean'
        ]);

        try {
            $data = $request->all();

            $productSupplierMapping->updateMarkup($data['markup_type'], $data['markup_value']);

            if ($data['apply_immediately'] ?? false) {
                if ($productSupplierMapping->supplierProduct) {
                    $productSupplierMapping->updatePricing($productSupplierMapping->supplierProduct->supplier_price);
                }
            }

            Log::info('Product supplier mapping markup updated', [
                'mapping_id' => $productSupplierMapping->id,
                'markup_type' => $data['markup_type'],
                'markup_value' => $data['markup_value'],
                'applied_immediately' => $data['apply_immediately'] ?? false
            ]);

            return $this->ok(
                'Markup updated successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );
        } catch (Exception $e) {
            Log::error('Failed to update markup', [
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to update markup.', 500);
        }
    }

    public function syncFromSupplier(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        try {
            if (!$productSupplierMapping->is_active) {
                return $this->error('Cannot sync from inactive mapping.', 400);
            }

            if (!$productSupplierMapping->supplierProduct) {
                return $this->error('No supplier product found for this mapping.', 400);
            }

            $productSupplierMapping->syncFromSupplierProduct();

            Log::info('Product synced from supplier', [
                'mapping_id' => $productSupplierMapping->id,
                'product_id' => $productSupplierMapping->product_id,
                'supplier_product_id' => $productSupplierMapping->supplier_product_id
            ]);

            return $this->ok(
                'Product synced from supplier successfully.',
                new ProductSupplierMappingResource($productSupplierMapping->load(['product', 'supplier', 'supplierProduct']))
            );
        } catch (Exception $e) {
            Log::error('Failed to sync from supplier', [
                'mapping_id' => $productSupplierMapping->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to sync from supplier.', 500);
        }
    }

    public function bulkUpdateSettings(Request $request)
    {
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

            $updated = DB::transaction(function () use ($mappingIds, $updates) {
                return ProductSupplierMapping::whereIn('id', $mappingIds)->update($updates);
            });

            Log::info('Bulk mapping settings update completed', [
                'mappings_updated' => $updated,
                'settings' => $updates
            ]);

            return $this->ok("Successfully updated settings for {$updated} mappings.", [
                'updated_count' => $updated,
                'applied_settings' => $updates
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update mapping settings', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update settings.', 500);
        }
    }

    public function bulkSyncPrices(Request $request)
    {
        $request->validate([
            'mapping_ids' => 'required|array|min:1',
            'mapping_ids.*' => 'exists:product_supplier_mappings,id'
        ]);

        try {
            $mappingIds = $request->input('mapping_ids');
            $syncResults = ['synced' => 0, 'failed' => 0, 'skipped' => 0];

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
                    } catch (Exception $e) {
                        $syncResults['failed']++;
                        Log::warning('Failed to sync price for mapping', [
                            'mapping_id' => $mapping->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

            Log::info('Bulk price sync completed', $syncResults);

            return $this->ok('Bulk price sync completed.', $syncResults);
        } catch (Exception $e) {
            Log::error('Failed to bulk sync prices', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk sync prices.', 500);
        }
    }

    public function getHealthReport(Request $request)
    {
        try {
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
                    'low_stock_suppliers' => ProductSupplierMapping::where('is_active', true)
                        ->whereHas('supplierProduct', function($q) {
                            $q->whereRaw('stock_quantity <= minimum_order_quantity');
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
                ]
            ];

            return $this->ok('Health report generated successfully.', $report);
        } catch (Exception $e) {
            Log::error('Failed to generate health report', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to generate health report.', 500);
        }
    }
}

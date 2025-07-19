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

    public function index(IndexSupplierProductRequest $request)
    {
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
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve supplier products.', 500);
        }
    }

    public function store(StoreSupplierProductRequest $request)
    {
        try {
            $data = $request->validated();

            $supplierProduct = DB::transaction(function () use ($data) {
                $supplierProduct = SupplierProduct::create($data);

                Log::info('Supplier product created', [
                    'supplier_product_id' => $supplierProduct->id,
                    'supplier_id' => $supplierProduct->supplier_id,
                    'supplier_sku' => $supplierProduct->supplier_sku
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
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create supplier product.', 500);
        }
    }

    public function show(Request $request, SupplierProduct $supplierProduct)
    {
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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier product.', 500);
        }
    }

    public function update(UpdateSupplierProductRequest $request, SupplierProduct $supplierProduct)
    {
        try {
            $data = $request->validated();

            $updatedProduct = DB::transaction(function () use ($supplierProduct, $data) {
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
                        'new_price' => $data['supplier_price']
                    ]);
                }

                if (isset($data['stock_quantity']) && $originalData['stock_quantity'] !== $data['stock_quantity']) {
                    Log::info('Supplier product stock updated', [
                        'supplier_product_id' => $supplierProduct->id,
                        'old_stock' => $originalData['stock_quantity'],
                        'new_stock' => $data['stock_quantity']
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
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update supplier product.', 500);
        }
    }

    public function destroy(Request $request, SupplierProduct $supplierProduct)
    {
        try {
            $hasActiveOrders = $supplierProduct->dropshipOrderItems()
                ->whereHas('dropshipOrder', function($query) {
                    $query->whereNotIn('status', ['delivered', 'cancelled', 'refunded']);
                })
                ->exists();

            if ($hasActiveOrders) {
                return $this->error('Cannot delete supplier product with active dropship orders.', 400);
            }

            DB::transaction(function () use ($supplierProduct) {
                if ($supplierProduct->is_mapped && $supplierProduct->product) {
                    $supplierProduct->product->update(['is_dropship' => false]);
                }

                $supplierProduct->delete();

                Log::info('Supplier product deleted', [
                    'supplier_product_id' => $supplierProduct->id,
                    'supplier_sku' => $supplierProduct->supplier_sku
                ]);
            });

            return $this->ok('Supplier product deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete supplier product', [
                'supplier_product_id' => $supplierProduct->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete supplier product.', 500);
        }
    }

    public function syncFromSupplier(Request $request, Supplier $supplier)
    {
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
                'integration_type' => $integration->integration_type
            ]);

            $supplier->updateLastSync();

            return $this->ok('Product sync completed successfully.', $syncResult);
        } catch (Exception $e) {
            Log::error('Failed to sync supplier products', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to sync supplier products.', 500);
        }
    }

    public function mapToProduct(Request $request, SupplierProduct $supplierProduct)
    {
        $request->validate([
            'create_new_product' => 'boolean',
            'product_id' => 'required_if:create_new_product,false|exists:products,id',
            'markup_percentage' => 'nullable|numeric|min:0|max:1000',
            'markup_type' => 'nullable|string|in:percentage,fixed',
            'fixed_markup' => 'nullable|integer|min:0'
        ]);

        try {
            $data = $request->all();

            $result = DB::transaction(function () use ($supplierProduct, $data) {
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
                    'created_new_product' => $data['create_new_product'] ?? false
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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to map supplier product.', 500);
        }
    }

    public function bulkUpdateStock(Request $request)
    {
        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'updates.*.stock_quantity' => 'required|integer|min:0'
        ]);

        try {
            $updates = $request->input('updates');
            $updated = 0;

            DB::transaction(function () use ($updates, &$updated) {
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
                'total_requested' => count($updates)
            ]);

            return $this->ok("Successfully updated stock for {$updated} products.", [
                'updated_count' => $updated,
                'total_requested' => count($updates)
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update stock', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update stock.', 500);
        }
    }

    public function bulkUpdatePrices(Request $request)
    {
        $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.supplier_product_id' => 'required|exists:supplier_products,id',
            'updates.*.supplier_price' => 'required|integer|min:0',
            'updates.*.retail_price' => 'nullable|integer|min:0'
        ]);

        try {
            $updates = $request->input('updates');
            $updated = 0;

            DB::transaction(function () use ($updates, &$updated) {
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
                'total_requested' => count($updates)
            ]);

            return $this->ok("Successfully updated prices for {$updated} products.", [
                'updated_count' => $updated,
                'total_requested' => count($updates)
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update prices', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update prices.', 500);
        }
    }

    public function bulkMarkStatus(Request $request)
    {
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
                'new_status' => $syncStatus
            ]);

            return $this->ok("Successfully updated status for {$updated} products.", [
                'updated_count' => $updated,
                'new_status' => $syncStatus
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update status', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update status.', 500);
        }
    }
}

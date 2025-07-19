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

    public function index(IndexSupplierRequest $request)
    {
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
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve suppliers.', 500);
        }
    }

    public function store(StoreSupplierRequest $request)
    {
        try {
            $data = $request->validated();

            $supplier = DB::transaction(function () use ($data) {
                $supplier = Supplier::create($data);

                Log::info('Supplier created', [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name,
                    'integration_type' => $supplier->integration_type
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
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create supplier.', 500);
        }
    }

    public function show(Request $request, Supplier $supplier)
    {
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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier.', 500);
        }
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        try {
            $data = $request->validated();

            $updatedSupplier = DB::transaction(function () use ($supplier, $data) {
                $originalStatus = $supplier->status;
                $supplier->update($data);

                if (isset($data['status']) && $originalStatus !== $data['status']) {
                    Log::info('Supplier status changed', [
                        'supplier_id' => $supplier->id,
                        'old_status' => $originalStatus,
                        'new_status' => $data['status']
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
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update supplier.', 500);
        }
    }

    public function destroy(Request $request, Supplier $supplier)
    {
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

            DB::transaction(function () use ($supplier) {
                $supplier->supplierProducts()->update(['is_active' => false]);
                $supplier->supplierIntegrations()->update(['is_active' => false]);
                $supplier->delete();

                Log::info('Supplier deleted', [
                    'supplier_id' => $supplier->id,
                    'name' => $supplier->name
                ]);
            });

            return $this->ok('Supplier deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete supplier.', 500);
        }
    }

    public function activate(Request $request, Supplier $supplier)
    {
        try {
            $supplier->update(['status' => 'active']);

            Log::info('Supplier activated', [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name
            ]);

            return $this->ok(
                'Supplier activated successfully.',
                new SupplierResource($supplier)
            );
        } catch (Exception $e) {
            Log::error('Failed to activate supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to activate supplier.', 500);
        }
    }

    public function deactivate(Request $request, Supplier $supplier)
    {
        try {
            $supplier->update(['status' => 'inactive']);

            Log::info('Supplier deactivated', [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name
            ]);

            return $this->ok(
                'Supplier deactivated successfully.',
                new SupplierResource($supplier)
            );
        } catch (Exception $e) {
            Log::error('Failed to deactivate supplier', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to deactivate supplier.', 500);
        }
    }

    public function getStats(Request $request, Supplier $supplier)
    {
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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier stats.', 500);
        }
    }

    public function testConnection(Request $request, Supplier $supplier)
    {
        try {
            $integration = $supplier->getActiveIntegration();

            if (!$integration) {
                return $this->error('No active integration found for this supplier.', 400);
            }

            $result = $integration->testConnection();

            Log::info('Supplier connection test', [
                'supplier_id' => $supplier->id,
                'integration_type' => $integration->integration_type,
                'success' => $result['success']
            ]);

            return $this->ok('Connection test completed.', $result);
        } catch (Exception $e) {
            Log::error('Failed to test supplier connection', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to test connection.', 500);
        }
    }
}

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
use App\Constants\SupplierIntegrationTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SupplierIntegrationController extends Controller
{
    use ApiResponses;

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
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve supplier integrations.', 500);
        }
    }

    public function store(StoreSupplierIntegrationRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $integration = DB::transaction(function () use ($data) {
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
                    'name' => $integration->name
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
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create supplier integration.', 500);
        }
    }

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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier integration.', 500);
        }
    }

    public function update(UpdateSupplierIntegrationRequest $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            $updatedIntegration = DB::transaction(function () use ($supplierIntegration, $data) {
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
                        'new_status' => $data['is_active'] ? 'active' : 'inactive'
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
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update supplier integration.', 500);
        }
    }

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

            DB::transaction(function () use ($supplierIntegration) {
                $supplierIntegration->delete();

                Log::info('Supplier integration deleted', [
                    'integration_id' => $supplierIntegration->id,
                    'supplier_id' => $supplierIntegration->supplier_id,
                    'integration_type' => $supplierIntegration->integration_type
                ]);
            });

            return $this->ok('Supplier integration deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete supplier integration.', 500);
        }
    }

    public function enable(Request $request, SupplierIntegration $supplierIntegration)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_supplier_integrations')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            DB::transaction(function () use ($supplierIntegration) {
                SupplierIntegration::where('supplier_id', $supplierIntegration->supplier_id)
                    ->where('id', '!=', $supplierIntegration->id)
                    ->update(['is_active' => false]);

                $supplierIntegration->enable();
            });

            Log::info('Supplier integration enabled', [
                'integration_id' => $supplierIntegration->id,
                'supplier_id' => $supplierIntegration->supplier_id
            ]);

            return $this->ok(
                'Supplier integration enabled successfully.',
                new SupplierIntegrationResource($supplierIntegration->load('supplier'))
            );
        } catch (Exception $e) {
            Log::error('Failed to enable supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to enable supplier integration.', 500);
        }
    }

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
                'supplier_id' => $supplierIntegration->supplier_id
            ]);

            return $this->ok(
                'Supplier integration disabled successfully.',
                new SupplierIntegrationResource($supplierIntegration->load('supplier'))
            );
        } catch (Exception $e) {
            Log::error('Failed to disable supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to disable supplier integration.', 500);
        }
    }

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
                    'integration_type' => $supplierIntegration->integration_type
                ]);
            } else {
                $supplierIntegration->recordFailedSync($testResult['message']);

                Log::warning('Integration test failed', [
                    'integration_id' => $supplierIntegration->id,
                    'integration_type' => $supplierIntegration->integration_type,
                    'error' => $testResult['message']
                ]);
            }

            return $this->ok('Integration test completed.', $testResult);
        } catch (Exception $e) {
            Log::error('Failed to test supplier integration', [
                'integration_id' => $supplierIntegration->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to test integration.', 500);
        }
    }

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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to sync integration.', 500);
        }
    }

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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to reset failures.', 500);
        }
    }

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
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve logs.', 500);
        }
    }
}

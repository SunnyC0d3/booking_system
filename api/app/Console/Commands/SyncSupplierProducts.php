<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierIntegration;
use App\Models\SupplierProduct;
use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncSupplierProducts extends Command
{
    protected $signature = 'dropship:sync-products
                            {--supplier= : Sync products for specific supplier ID}
                            {--force : Force sync even if recently synced}
                            {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Sync products from all active suppliers or a specific supplier';

    public function handle()
    {
        $supplierId = $this->option('supplier');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        $suppliers = $this->getSuppliers($supplierId);

        if ($suppliers->isEmpty()) {
            $this->error('No eligible suppliers found for sync');
            return 1;
        }

        $this->info("Found {$suppliers->count()} supplier(s) to sync");

        $totalStats = [
            'suppliers_processed' => 0,
            'suppliers_failed' => 0,
            'products_found' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'products_deactivated' => 0,
            'errors' => []
        ];

        foreach ($suppliers as $supplier) {
            $this->info("Processing supplier: {$supplier->name}");

            try {
                $stats = $this->syncSupplierProducts($supplier, $force, $dryRun);

                $totalStats['suppliers_processed']++;
                $totalStats['products_found'] += $stats['products_found'];
                $totalStats['products_created'] += $stats['products_created'];
                $totalStats['products_updated'] += $stats['products_updated'];
                $totalStats['products_deactivated'] += $stats['products_deactivated'];

                $this->line("  - Found: {$stats['products_found']} products");
                $this->line("  - Created: {$stats['products_created']} new products");
                $this->line("  - Updated: {$stats['products_updated']} existing products");
                $this->line("  - Deactivated: {$stats['products_deactivated']} products");

            } catch (Exception $e) {
                $totalStats['suppliers_failed']++;
                $totalStats['errors'][] = "Supplier {$supplier->name}: " . $e->getMessage();

                $this->error("  Failed to sync supplier {$supplier->name}: " . $e->getMessage());

                Log::error('Failed to sync supplier products', [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->displaySummary($totalStats, $dryRun);

        return $totalStats['suppliers_failed'] > 0 ? 1 : 0;
    }

    private function getSuppliers($supplierId = null)
    {
        $query = Supplier::with('supplierIntegrations')
            ->where('status', SupplierStatuses::ACTIVE);

        if ($supplierId) {
            $query->where('id', $supplierId);
        }

        return $query->get()->filter(function ($supplier) {
            return $supplier->supplierIntegrations()
                ->where('is_active', true)
                ->whereIn('integration_type', SupplierIntegrationTypes::getAutomatedTypes())
                ->exists();
        });
    }

    private function syncSupplierProducts(Supplier $supplier, bool $force, bool $dryRun): array
    {
        $integration = $supplier->getActiveIntegration();

        if (!$integration || !$integration->isAutomated()) {
            throw new Exception('No active automated integration found');
        }

        if (!$force && !$integration->needsSync()) {
            $this->line("  Skipping - recently synced");
            return [
                'products_found' => 0,
                'products_created' => 0,
                'products_updated' => 0,
                'products_deactivated' => 0
            ];
        }

        $stats = [
            'products_found' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'products_deactivated' => 0
        ];

        $supplierProducts = $this->fetchProductsFromSupplier($integration);
        $stats['products_found'] = count($supplierProducts);

        if (!$dryRun) {
            $this->processSupplierProducts($supplier, $supplierProducts, $stats);
            $this->deactivateRemovedProducts($supplier, $supplierProducts, $stats);

            $supplier->updateLastSync();
            $integration->recordSuccessfulSync([
                'products_processed' => $stats['products_found'],
                'products_created' => $stats['products_created'],
                'products_updated' => $stats['products_updated']
            ]);
        }

        return $stats;
    }

    private function fetchProductsFromSupplier(SupplierIntegration $integration): array
    {
        switch ($integration->integration_type) {
            case SupplierIntegrationTypes::API:
                return $this->fetchFromApi($integration);
            case SupplierIntegrationTypes::FTP:
                return $this->fetchFromFtp($integration);
            default:
                return [];
        }
    }

    private function fetchFromApi(SupplierIntegration $integration): array
    {
        $endpoint = $integration->getApiEndpoint();
        $apiKey = $integration->getApiKey();

        if (!$endpoint || !$apiKey) {
            throw new Exception('API configuration incomplete');
        }

        return [];
    }

    private function fetchFromFtp(SupplierIntegration $integration): array
    {
        $ftpHost = $integration->getFtpHost();
        $ftpUsername = $integration->getFtpUsername();
        $ftpPassword = $integration->getFtpPassword();

        if (!$ftpHost || !$ftpUsername || !$ftpPassword) {
            throw new Exception('FTP configuration incomplete');
        }

        return [];
    }

    private function processSupplierProducts(Supplier $supplier, array $supplierProducts, array &$stats): void
    {
        foreach ($supplierProducts as $productData) {
            $existingProduct = SupplierProduct::where('supplier_id', $supplier->id)
                ->where('supplier_sku', $productData['sku'])
                ->first();

            if ($existingProduct) {
                if ($this->shouldUpdateProduct($existingProduct, $productData)) {
                    $this->updateSupplierProduct($existingProduct, $productData);
                    $stats['products_updated']++;
                }
            } else {
                $this->createSupplierProduct($supplier, $productData);
                $stats['products_created']++;
            }
        }
    }

    private function shouldUpdateProduct(SupplierProduct $product, array $productData): bool
    {
        return $product->supplier_price !== ($productData['price'] * 100) ||
            $product->stock_quantity !== $productData['stock'] ||
            $product->name !== $productData['name'];
    }

    private function updateSupplierProduct(SupplierProduct $product, array $productData): void
    {
        $product->update([
            'name' => $productData['name'],
            'description' => $productData['description'] ?? $product->description,
            'supplier_price' => $productData['price'] * 100,
            'stock_quantity' => $productData['stock'],
            'weight' => $productData['weight'] ?? $product->weight,
            'sync_status' => DropshipProductSyncStatuses::SYNCED,
            'last_synced_at' => now(),
            'sync_errors' => null,
        ]);

        if ($product->productMapping && $product->productMapping->canUpdatePrice()) {
            $product->productMapping->updatePricing($product->supplier_price);
        }

        if ($product->productMapping && $product->productMapping->canUpdateStock()) {
            $product->productMapping->updateStock($product->stock_quantity);
        }
    }

    private function createSupplierProduct(Supplier $supplier, array $productData): void
    {
        SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'supplier_sku' => $productData['sku'],
            'supplier_product_id' => $productData['id'],
            'name' => $productData['name'],
            'description' => $productData['description'] ?? '',
            'supplier_price' => $productData['price'] * 100,
            'stock_quantity' => $productData['stock'],
            'weight' => $productData['weight'] ?? 0,
            'sync_status' => DropshipProductSyncStatuses::SYNCED,
            'is_active' => true,
            'last_synced_at' => now(),
        ]);
    }

    private function deactivateRemovedProducts(Supplier $supplier, array $supplierProducts, array &$stats): void
    {
        $supplierSkus = collect($supplierProducts)->pluck('sku')->toArray();

        $removedProducts = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->whereNotIn('supplier_sku', $supplierSkus)
            ->get();

        foreach ($removedProducts as $product) {
            $product->update([
                'is_active' => false,
                'sync_status' => DropshipProductSyncStatuses::SUPPLIER_DISCONTINUED,
                'last_synced_at' => now()
            ]);
            $stats['products_deactivated']++;
        }
    }

    private function displaySummary(array $stats, bool $dryRun): void
    {
        $this->info('');
        $this->info('=== Sync Summary ===');
        $this->line("Suppliers processed: {$stats['suppliers_processed']}");
        $this->line("Suppliers failed: {$stats['suppliers_failed']}");
        $this->line("Products found: {$stats['products_found']}");

        if (!$dryRun) {
            $this->line("Products created: {$stats['products_created']}");
            $this->line("Products updated: {$stats['products_updated']}");
            $this->line("Products deactivated: {$stats['products_deactivated']}");
        } else {
            $this->line("Products would be created: {$stats['products_created']}");
            $this->line("Products would be updated: {$stats['products_updated']}");
            $this->line("Products would be deactivated: {$stats['products_deactivated']}");
        }

        if (!empty($stats['errors'])) {
            $this->error('');
            $this->error('Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }
    }
}

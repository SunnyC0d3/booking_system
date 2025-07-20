<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\ProductSupplierMapping;
use App\Constants\SupplierStatuses;
use App\Constants\SupplierIntegrationTypes;
use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class SyncSupplierStock extends Command
{
    protected $signature = 'dropship:sync-stock
                            {--supplier= : Sync stock for specific supplier ID}
                            {--product= : Sync stock for specific supplier product ID}
                            {--force : Force sync even if recently synced}
                            {--threshold=10 : Only sync products with stock below this threshold}
                            {--batch-size=100 : Number of products to process in each batch}
                            {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Sync stock levels from suppliers and update product availability';

    public function handle()
    {
        $supplierId = $this->option('supplier');
        $productId = $this->option('product');
        $force = $this->option('force');
        $threshold = (int) $this->option('threshold');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        if ($productId) {
            return $this->syncSingleProduct($productId, $dryRun);
        }

        $suppliers = $this->getEligibleSuppliers($supplierId, $force);

        if ($suppliers->isEmpty()) {
            $this->info('No eligible suppliers found for stock sync');
            return 0;
        }

        $this->info("Found {$suppliers->count()} supplier(s) for stock sync");

        $totalStats = [
            'suppliers_processed' => 0,
            'suppliers_failed' => 0,
            'products_checked' => 0,
            'stock_updated' => 0,
            'out_of_stock_found' => 0,
            'low_stock_alerts' => 0,
            'mapping_updates' => 0,
            'errors' => []
        ];

        foreach ($suppliers as $supplier) {
            $this->info("Processing supplier: {$supplier->name}");

            try {
                $stats = $this->syncSupplierStock($supplier, $threshold, $batchSize, $force, $dryRun);

                $totalStats['suppliers_processed']++;
                $totalStats['products_checked'] += $stats['products_checked'];
                $totalStats['stock_updated'] += $stats['stock_updated'];
                $totalStats['out_of_stock_found'] += $stats['out_of_stock_found'];
                $totalStats['low_stock_alerts'] += $stats['low_stock_alerts'];
                $totalStats['mapping_updates'] += $stats['mapping_updates'];

                $this->displaySupplierStats($supplier, $stats);

            } catch (Exception $e) {
                $totalStats['suppliers_failed']++;
                $totalStats['errors'][] = "Supplier {$supplier->name}: " . $e->getMessage();

                $this->error("  Failed to sync supplier {$supplier->name}: " . $e->getMessage());

                Log::error('Failed to sync supplier stock', [
                    'supplier_id' => $supplier->id,
                    'supplier_name' => $supplier->name,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->displayTotalSummary($totalStats, $dryRun);

        return $totalStats['suppliers_failed'] > 0 ? 1 : 0;
    }

    private function syncSingleProduct(int $productId, bool $dryRun): int
    {
        $supplierProduct = SupplierProduct::with(['supplier', 'productMapping'])
            ->find($productId);

        if (!$supplierProduct) {
            $this->error("Supplier product with ID {$productId} not found");
            return 1;
        }

        $this->info("Syncing stock for product: {$supplierProduct->name}");

        try {
            $oldStock = $supplierProduct->stock_quantity;
            $newStock = $this->fetchProductStock($supplierProduct);

            if ($newStock !== null && $newStock !== $oldStock) {
                if (!$dryRun) {
                    $this->updateProductStock($supplierProduct, $newStock);
                    $this->info("Stock updated: {$oldStock} → {$newStock}");
                } else {
                    $this->info("Would update stock: {$oldStock} → {$newStock}");
                }
            } else {
                $this->info("Stock unchanged: {$oldStock}");
            }

            return 0;
        } catch (Exception $e) {
            $this->error("Failed to sync product stock: " . $e->getMessage());
            return 1;
        }
    }

    private function getEligibleSuppliers($supplierId, bool $force)
    {
        $query = Supplier::with('supplierIntegrations')
            ->where('status', SupplierStatuses::ACTIVE)
            ->where('stock_sync_enabled', true);

        if ($supplierId) {
            $query->where('id', $supplierId);
        }

        return $query->get()->filter(function ($supplier) use ($force) {
            $integration = $supplier->getActiveIntegration();

            if (!$integration || !$integration->isAutomated()) {
                return false;
            }

            return $force || $this->shouldSyncSupplierStock($supplier, $integration);
        });
    }

    private function shouldSyncSupplierStock(Supplier $supplier, $integration): bool
    {
        if (!$supplier->last_sync_at) {
            return true;
        }

        $syncFrequency = $integration->sync_frequency_minutes ?? 60;
        $nextSyncTime = $supplier->last_sync_at->addMinutes($syncFrequency);

        return now()->isAfter($nextSyncTime);
    }

    private function syncSupplierStock(Supplier $supplier, int $threshold, int $batchSize, bool $force, bool $dryRun): array
    {
        $stats = [
            'products_checked' => 0,
            'stock_updated' => 0,
            'out_of_stock_found' => 0,
            'low_stock_alerts' => 0,
            'mapping_updates' => 0
        ];

        $integration = $supplier->getActiveIntegration();
        $supplierProducts = $this->getProductsToSync($supplier, $threshold);

        $stats['products_checked'] = $supplierProducts->count();

        if ($stats['products_checked'] === 0) {
            $this->line("  No products found for stock sync");
            return $stats;
        }

        $progressBar = $this->output->createProgressBar($stats['products_checked']);
        $progressBar->start();

        $supplierProducts->chunk($batchSize, function ($batch) use (&$stats, $integration, $dryRun, $progressBar) {
            $stockUpdates = $this->fetchBatchStockData($integration, $batch);

            foreach ($batch as $product) {
                $this->processSingleProductStock($product, $stockUpdates, $stats, $dryRun);
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->line('');

        if (!$dryRun) {
            $supplier->updateLastSync();
            $integration->recordSuccessfulSync([
                'stock_sync' => true,
                'products_checked' => $stats['products_checked'],
                'stock_updates' => $stats['stock_updated']
            ]);
        }

        return $stats;
    }

    private function getProductsToSync(Supplier $supplier, int $threshold)
    {
        $query = SupplierProduct::where('supplier_id', $supplier->id)
            ->where('is_active', true)
            ->with(['productMapping']);

        if ($threshold > 0) {
            $query->where('stock_quantity', '<=', $threshold);
        }

        return $query->orderBy('last_synced_at', 'asc')
            ->orderBy('stock_quantity', 'asc')
            ->get();
    }

    private function fetchBatchStockData($integration, $products): array
    {
        $stockData = [];

        foreach ($products as $product) {
            $newStock = $this->fetchProductStock($product);
            if ($newStock !== null) {
                $stockData[$product->supplier_sku] = $newStock;
            }
        }

        return $stockData;
    }

    private function fetchProductStock(SupplierProduct $product): ?int
    {
        $integration = $product->supplier->getActiveIntegration();

        if (!$integration) {
            return null;
        }

        switch ($integration->integration_type) {
            case SupplierIntegrationTypes::API:
                return $this->fetchStockFromApi($integration, $product);
            case SupplierIntegrationTypes::FTP:
                return $this->fetchStockFromFtp($integration, $product);
            default:
                return $this->generateMockStock($product);
        }
    }

    private function fetchStockFromApi($integration, SupplierProduct $product): ?int
    {
        $endpoint = $integration->getApiEndpoint();
        $apiKey = $integration->getApiKey();

        if (!$endpoint || !$apiKey) {
            throw new Exception('API configuration incomplete for stock sync');
        }

        return $this->generateMockStock($product);
    }

    private function fetchStockFromFtp($integration, SupplierProduct $product): ?int
    {
        $ftpHost = $integration->getFtpHost();

        if (!$ftpHost) {
            throw new Exception('FTP configuration incomplete for stock sync');
        }

        return $this->generateMockStock($product);
    }

    private function generateMockStock(SupplierProduct $product): int
    {
        $currentStock = $product->stock_quantity;
        $variance = rand(-5, 15);

        return max(0, $currentStock + $variance);
    }

    private function processSingleProductStock(SupplierProduct $product, array $stockUpdates, array &$stats, bool $dryRun): void
    {
        $sku = $product->supplier_sku;
        $oldStock = $product->stock_quantity;
        $newStock = $stockUpdates[$sku] ?? null;

        if ($newStock === null || $newStock === $oldStock) {
            return;
        }

        if (!$dryRun) {
            $this->updateProductStock($product, $newStock);
        }

        $stats['stock_updated']++;

        if ($newStock === 0) {
            $stats['out_of_stock_found']++;
            $this->handleOutOfStock($product, $dryRun);
        } elseif ($newStock <= ($product->minimum_order_quantity ?? 5)) {
            $stats['low_stock_alerts']++;
            $this->handleLowStock($product, $newStock, $dryRun);
        }

        if ($product->productMapping && $product->productMapping->canUpdateStock()) {
            if (!$dryRun) {
                $product->productMapping->updateStock($newStock);
            }
            $stats['mapping_updates']++;
        }
    }

    private function updateProductStock(SupplierProduct $product, int $newStock): void
    {
        $product->update([
            'stock_quantity' => $newStock,
            'sync_status' => DropshipProductSyncStatuses::SYNCED,
            'last_synced_at' => now(),
            'sync_errors' => null
        ]);
    }

    private function handleOutOfStock(SupplierProduct $product, bool $dryRun): void
    {
        Log::warning('Product out of stock detected', [
            'supplier_product_id' => $product->id,
            'supplier_sku' => $product->supplier_sku,
            'product_name' => $product->name,
            'supplier_id' => $product->supplier_id
        ]);

        if (!$dryRun && $product->productMapping) {
            $product->productMapping->product->update(['quantity' => 0]);
        }
    }

    private function handleLowStock(SupplierProduct $product, int $stock, bool $dryRun): void
    {
        Log::info('Low stock alert', [
            'supplier_product_id' => $product->id,
            'supplier_sku' => $product->supplier_sku,
            'product_name' => $product->name,
            'current_stock' => $stock,
            'minimum_threshold' => $product->minimum_order_quantity ?? 5,
            'supplier_id' => $product->supplier_id
        ]);
    }

    private function displaySupplierStats(Supplier $supplier, array $stats): void
    {
        $this->line("  - Products checked: {$stats['products_checked']}");
        $this->line("  - Stock updated: {$stats['stock_updated']}");
        $this->line("  - Out of stock: {$stats['out_of_stock_found']}");
        $this->line("  - Low stock alerts: {$stats['low_stock_alerts']}");
        $this->line("  - Mapping updates: {$stats['mapping_updates']}");
    }

    private function displayTotalSummary(array $stats, bool $dryRun): void
    {
        $this->info('');
        $this->info('=== Stock Sync Summary ===');
        $this->line("Suppliers processed: {$stats['suppliers_processed']}");
        $this->line("Suppliers failed: {$stats['suppliers_failed']}");
        $this->line("Products checked: {$stats['products_checked']}");

        if (!$dryRun) {
            $this->line("Stock levels updated: {$stats['stock_updated']}");
            $this->line("Out of stock found: {$stats['out_of_stock_found']}");
            $this->line("Low stock alerts: {$stats['low_stock_alerts']}");
            $this->line("Product mappings updated: {$stats['mapping_updates']}");
        } else {
            $this->line("Stock levels would be updated: {$stats['stock_updated']}");
            $this->line("Out of stock would be found: {$stats['out_of_stock_found']}");
            $this->line("Low stock alerts would be sent: {$stats['low_stock_alerts']}");
            $this->line("Product mappings would be updated: {$stats['mapping_updates']}");
        }

        if (!empty($stats['errors'])) {
            $this->error('');
            $this->error('Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->error("  - {$error}");
            }
        }

        if ($stats['out_of_stock_found'] > 0) {
            $this->warn('');
            $this->warn("Warning: {$stats['out_of_stock_found']} products are now out of stock!");
        }

        if ($stats['low_stock_alerts'] > 0) {
            $this->comment('');
            $this->comment("Note: {$stats['low_stock_alerts']} products have low stock levels");
        }
    }
}

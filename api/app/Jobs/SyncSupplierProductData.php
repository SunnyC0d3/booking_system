<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\ProductSupplierMapping;
use App\Constants\SupplierIntegrationTypes;
use App\Constants\DropshipProductSyncStatuses;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Exception;

class SyncSupplierProductData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800;
    public $tries = 2;
    public $maxExceptions = 2;

    protected Supplier $supplier;
    protected string $syncType;
    protected array $options;

    public function __construct(Supplier $supplier, string $syncType = 'full', array $options = [])
    {
        $this->supplier = $supplier;
        $this->syncType = $syncType;
        $this->options = $options;

        $this->onQueue($this->getQueueName($syncType));
    }

    public function handle(): void
    {
        try {
            Log::info('Starting supplier product data sync', [
                'supplier_id' => $this->supplier->id,
                'supplier_name' => $this->supplier->name,
                'sync_type' => $this->syncType,
                'attempt' => $this->attempts()
            ]);

            if (!$this->supplier->isActive()) {
                throw new Exception('Supplier is not active');
            }

            $integration = $this->supplier->getActiveIntegration();

            if (!$integration || !$integration->isAutomated()) {
                throw new Exception('No active automated integration found');
            }

            $result = $this->performSync($integration);

            $this->handleSyncResult($result, $integration);

        } catch (Exception $e) {
            $this->handleSyncException($e);
            throw $e;
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error('SyncSupplierProductData job failed permanently', [
            'supplier_id' => $this->supplier->id,
            'sync_type' => $this->syncType,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $integration = $this->supplier->getActiveIntegration();
        if ($integration) {
            $integration->recordFailedSync($exception->getMessage());
        }
    }

    private function performSync($integration): array
    {
        switch ($integration->integration_type) {
            case SupplierIntegrationTypes::API:
                return $this->syncFromApi($integration);

            case SupplierIntegrationTypes::FTP:
                return $this->syncFromFtp($integration);

            default:
                throw new Exception("Sync not supported for integration type: {$integration->integration_type}");
        }
    }

    private function syncFromApi($integration): array
    {
        $endpoint = $integration->getApiEndpoint();
        $apiKey = $integration->getApiKey();

        if (!$endpoint || !$apiKey) {
            throw new Exception('API configuration incomplete');
        }

        $syncResult = [
            'products_found' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'products_deactivated' => 0,
            'stock_updates' => 0,
            'price_updates' => 0,
            'errors' => []
        ];

        try {
            $allProducts = $this->fetchAllProductsFromApi($integration);
            $syncResult['products_found'] = count($allProducts);

            if (empty($allProducts)) {
                Log::warning('No products returned from API', [
                    'supplier_id' => $this->supplier->id,
                    'api_endpoint' => $endpoint
                ]);
                return $syncResult;
            }

            DB::transaction(function () use ($allProducts, &$syncResult) {
                $this->processProductBatch($allProducts, $syncResult);
            });

            return $syncResult;

        } catch (Exception $e) {
            throw new Exception("API sync failed: " . $e->getMessage());
        }
    }

    private function fetchAllProductsFromApi($integration): array
    {
        $endpoint = $integration->getApiEndpoint();
        $apiKey = $integration->getApiKey();
        $config = $integration->configuration;

        $allProducts = [];
        $page = 1;
        $pageSize = $config['pagination']['page_size'] ?? 50;
        $maxPages = 100;

        do {
            $response = Http::timeout($config['timeout'] ?? 60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json'
                ])
                ->get($endpoint . '/products', [
                    'page' => $page,
                    'per_page' => $pageSize,
                    'include_inactive' => $this->syncType === 'full' ? 'true' : 'false'
                ]);

            if (!$response->successful()) {
                throw new Exception("API request failed with status: " . $response->status());
            }

            $data = $response->json();
            $products = $data['products'] ?? $data['data'] ?? [];

            if (empty($products)) {
                break;
            }

            $allProducts = array_merge($allProducts, $products);

            $hasMore = $data['has_more'] ?? false;
            $totalPages = $data['total_pages'] ?? 1;

            if (!$hasMore || $page >= $totalPages || $page >= $maxPages) {
                break;
            }

            $page++;

        } while (true);

        Log::info('Fetched products from API', [
            'supplier_id' => $this->supplier->id,
            'total_products' => count($allProducts),
            'pages_fetched' => $page
        ]);

        return $allProducts;
    }

    private function syncFromFtp($integration): array
    {
        $ftpHost = $integration->getFtpHost();
        $ftpUsername = $integration->getFtpUsername();
        $ftpPassword = $integration->getFtpPassword();

        if (!$ftpHost || !$ftpUsername || !$ftpPassword) {
            throw new Exception('FTP configuration incomplete');
        }

        $syncResult = [
            'products_found' => 0,
            'products_created' => 0,
            'products_updated' => 0,
            'products_deactivated' => 0,
            'stock_updates' => 0,
            'price_updates' => 0,
            'errors' => []
        ];

        try {
            $productData = $this->fetchProductsFromFtp($integration);
            $syncResult['products_found'] = count($productData);

            if (empty($productData)) {
                Log::warning('No products found in FTP files', [
                    'supplier_id' => $this->supplier->id,
                    'ftp_host' => $ftpHost
                ]);
                return $syncResult;
            }

            DB::transaction(function () use ($productData, &$syncResult) {
                $this->processProductBatch($productData, $syncResult);
            });

            return $syncResult;

        } catch (Exception $e) {
            throw new Exception("FTP sync failed: " . $e->getMessage());
        }
    }

    private function fetchProductsFromFtp($integration): array
    {
        $ftpHost = $integration->getFtpHost();
        $ftpUsername = $integration->getFtpUsername();
        $ftpPassword = $integration->getFtpPassword();
        $config = $integration->configuration;

        $downloadDir = $config['download_directory'] ?? '/products';

        $ftpConnection = ftp_connect($ftpHost, $config['ftp_port'] ?? 21);

        if (!$ftpConnection) {
            throw new Exception('Could not connect to FTP server');
        }

        if (!ftp_login($ftpConnection, $ftpUsername, $ftpPassword)) {
            ftp_close($ftpConnection);
            throw new Exception('FTP login failed');
        }

        if ($config['passive_mode'] ?? true) {
            ftp_pasv($ftpConnection, true);
        }

        $files = ftp_nlist($ftpConnection, $downloadDir);
        $csvFiles = array_filter($files, fn($file) => str_ends_with($file, '.csv'));

        if (empty($csvFiles)) {
            ftp_close($ftpConnection);
            throw new Exception('No CSV files found in FTP directory');
        }

        $latestFile = $this->getLatestFile($csvFiles);
        $tempFile = tempnam(sys_get_temp_dir(), 'supplier_products_');

        if (!ftp_get($ftpConnection, $tempFile, $latestFile, FTP_BINARY)) {
            ftp_close($ftpConnection);
            unlink($tempFile);
            throw new Exception('Failed to download FTP file');
        }

        ftp_close($ftpConnection);

        $productData = $this->parseCsvFile($tempFile);
        unlink($tempFile);

        return $productData;
    }

    private function getLatestFile(array $files): string
    {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files[0];
    }

    private function parseCsvFile(string $filePath): array
    {
        $products = [];

        if (($handle = fopen($filePath, 'r')) !== false) {
            $headers = fgetcsv($handle);

            if (!$headers) {
                fclose($handle);
                throw new Exception('Invalid CSV file - no headers found');
            }

            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) === count($headers)) {
                    $products[] = array_combine($headers, $data);
                }
            }

            fclose($handle);
        }

        return $products;
    }

    private function processProductBatch(array $products, array &$syncResult): void
    {
        $existingProducts = SupplierProduct::where('supplier_id', $this->supplier->id)
            ->get()
            ->keyBy('supplier_sku');

        $processedSkus = [];

        foreach ($products as $productData) {
            try {
                $sku = $productData['sku'] ?? $productData['supplier_sku'] ?? null;

                if (!$sku) {
                    $syncResult['errors'][] = 'Missing SKU in product data';
                    continue;
                }

                $processedSkus[] = $sku;
                $existingProduct = $existingProducts->get($sku);

                if ($existingProduct) {
                    $this->updateExistingProduct($existingProduct, $productData, $syncResult);
                } else {
                    $this->createNewProduct($productData, $syncResult);
                }

            } catch (Exception $e) {
                $syncResult['errors'][] = "Error processing product {$sku}: " . $e->getMessage();
                Log::warning('Error processing individual product', [
                    'sku' => $sku ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->deactivateRemovedProducts($existingProducts, $processedSkus, $syncResult);
    }

    private function updateExistingProduct(SupplierProduct $product, array $data, array &$syncResult): void
    {
        $updates = [];
        $hasChanges = false;

        $newPrice = isset($data['price']) ? (int)round(floatval($data['price']) * 100) : $product->supplier_price;
        $newStock = isset($data['stock']) ? (int)$data['stock'] : $product->stock_quantity;
        $newName = $data['name'] ?? $data['product_name'] ?? $product->name;

        if ($product->supplier_price !== $newPrice) {
            $updates['supplier_price'] = $newPrice;
            $syncResult['price_updates']++;
            $hasChanges = true;
        }

        if ($product->stock_quantity !== $newStock) {
            $updates['stock_quantity'] = $newStock;
            $syncResult['stock_updates']++;
            $hasChanges = true;
        }

        if ($product->name !== $newName) {
            $updates['name'] = $newName;
            $hasChanges = true;
        }

        if ($hasChanges) {
            $updates['sync_status'] = DropshipProductSyncStatuses::SYNCED;
            $updates['last_synced_at'] = now();
            $updates['sync_errors'] = null;

            $product->update($updates);
            $syncResult['products_updated']++;

            $this->updateMappedProduct($product, $updates);
        }
    }

    private function createNewProduct(array $data, array &$syncResult): void
    {
        $sku = $data['sku'] ?? $data['supplier_sku'];
        $price = isset($data['price']) ? (int)round(floatval($data['price']) * 100) : 0;
        $stock = isset($data['stock']) ? (int)$data['stock'] : 0;

        SupplierProduct::create([
            'supplier_id' => $this->supplier->id,
            'supplier_sku' => $sku,
            'supplier_product_id' => $data['id'] ?? $sku,
            'name' => $data['name'] ?? $data['product_name'] ?? 'Unnamed Product',
            'description' => $data['description'] ?? '',
            'supplier_price' => $price,
            'stock_quantity' => $stock,
            'weight' => isset($data['weight']) ? floatval($data['weight']) : 0,
            'length' => isset($data['length']) ? floatval($data['length']) : 0,
            'width' => isset($data['width']) ? floatval($data['width']) : 0,
            'height' => isset($data['height']) ? floatval($data['height']) : 0,
            'sync_status' => DropshipProductSyncStatuses::SYNCED,
            'is_active' => true,
            'last_synced_at' => now()
        ]);

        $syncResult['products_created']++;
    }

    private function updateMappedProduct(SupplierProduct $supplierProduct, array $updates): void
    {
        $mapping = ProductSupplierMapping::where('supplier_product_id', $supplierProduct->id)
            ->where('is_active', true)
            ->first();

        if (!$mapping) {
            return;
        }

        if (isset($updates['supplier_price']) && $mapping->canUpdatePrice()) {
            $mapping->updatePricing($updates['supplier_price']);
        }

        if (isset($updates['stock_quantity']) && $mapping->canUpdateStock()) {
            $mapping->updateStock($updates['stock_quantity']);
        }
    }

    private function deactivateRemovedProducts($existingProducts, array $processedSkus, array &$syncResult): void
    {
        $removedProducts = $existingProducts->filter(function ($product) use ($processedSkus) {
            return !in_array($product->supplier_sku, $processedSkus);
        });

        foreach ($removedProducts as $product) {
            $product->update([
                'is_active' => false,
                'sync_status' => DropshipProductSyncStatuses::SUPPLIER_DISCONTINUED,
                'last_synced_at' => now()
            ]);

            $syncResult['products_deactivated']++;
        }
    }

    private function handleSyncResult(array $result, $integration): void
    {
        $this->supplier->updateLastSync();

        $integration->recordSuccessfulSync([
            'sync_type' => $this->syncType,
            'products_processed' => $result['products_found'],
            'products_created' => $result['products_created'],
            'products_updated' => $result['products_updated'],
            'products_deactivated' => $result['products_deactivated'],
            'stock_updates' => $result['stock_updates'],
            'price_updates' => $result['price_updates'],
            'error_count' => count($result['errors'])
        ]);

        Log::info('Supplier product sync completed successfully', [
            'supplier_id' => $this->supplier->id,
            'sync_type' => $this->syncType,
            'summary' => $result
        ]);
    }

    private function handleSyncException(Exception $e): void
    {
        Log::error('Exception in SyncSupplierProductData job', [
            'supplier_id' => $this->supplier->id,
            'sync_type' => $this->syncType,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts()
        ]);
    }

    private function getQueueName(string $syncType): string
    {
        return match($syncType) {
            'pricing_update' => 'bulk_pricing',
            'stock_update' => 'bulk_stock',
            'urgent' => 'supplier_sync_urgent',
            default => 'supplier_sync'
        };
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(4);
    }

    public function backoff(): array
    {
        return [300, 900];
    }
}

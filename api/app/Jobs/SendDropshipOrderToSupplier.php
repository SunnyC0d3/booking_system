<?php

namespace App\Jobs;

use App\Models\DropshipOrder;
use App\Models\SupplierIntegration;
use App\Constants\SupplierIntegrationTypes;
use App\Constants\DropshipStatuses;
use App\Services\V1\Dropshipping\DropshipOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class SendDropshipOrderToSupplier implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;
    public $maxExceptions = 3;

    protected DropshipOrder $dropshipOrder;
    protected string $integrationType;
    protected array $options;

    public $backoff = [300, 900, 1800]; // 5 min, 15 min, 30 min

    public function __construct(DropshipOrder $dropshipOrder, string $integrationType, array $options = [])
    {
        $this->dropshipOrder = $dropshipOrder;
        $this->integrationType = $integrationType;
        $this->options = $options;

        $this->onQueue($this->getQueueName($integrationType));
    }

    public function handle(DropshipOrderService $dropshipOrderService): void
    {
        try {
            Log::info('Processing dropship order submission to supplier', [
                'dropship_order_id' => $this->dropshipOrder->id,
                'supplier_id' => $this->dropshipOrder->supplier_id,
                'integration_type' => $this->integrationType,
                'attempt' => $this->attempts()
            ]);

            if (!$this->dropshipOrder->supplier->isActive()) {
                throw new Exception('Supplier is not active');
            }

            $integration = $this->dropshipOrder->supplier->getActiveIntegration();

            if (!$integration) {
                throw new Exception('No active integration found for supplier');
            }

            $result = $this->sendOrderToSupplier($integration);

            if ($result['success']) {
                $this->handleSuccessfulSubmission($result);
            } else {
                $this->handleFailedSubmission($result);
            }

        } catch (Exception $e) {
            $this->handleJobException($e);
            throw $e;
        }
    }

    private function sendOrderToSupplier(SupplierIntegration $integration): array
    {
        switch ($this->integrationType) {
            case SupplierIntegrationTypes::API:
                return $this->sendViaApi($integration);

            case SupplierIntegrationTypes::WEBHOOK:
                return $this->sendViaWebhook($integration);

            case SupplierIntegrationTypes::FTP:
                return $this->sendViaFtp($integration);

            case SupplierIntegrationTypes::EMAIL:
                return $this->sendViaEmail($integration);

            default:
                throw new Exception("Unsupported integration type: {$this->integrationType}");
        }
    }

    private function sendViaApi(SupplierIntegration $integration): array
    {
        $endpoint = $integration->getApiEndpoint();
        $apiKey = $integration->getApiKey();

        if (!$endpoint || !$apiKey) {
            throw new Exception('API configuration incomplete');
        }

        $orderData = $this->prepareOrderDataForApi();
        $config = $integration->configuration;

        try {
            $response = Http::timeout($config['timeout'] ?? 30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($endpoint . '/orders', $orderData);

            if ($response->successful()) {
                $responseData = $response->json();

                return [
                    'success' => true,
                    'supplier_order_id' => $responseData['order_id'] ?? null,
                    'estimated_delivery' => $responseData['estimated_delivery'] ?? null,
                    'response_data' => $responseData,
                    'method' => 'api'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API request failed',
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'method' => 'api'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'API connection failed: ' . $e->getMessage(),
                'method' => 'api'
            ];
        }
    }

    private function sendViaWebhook(SupplierIntegration $integration): array
    {
        $webhookUrl = $integration->getWebhookUrl();
        $webhookSecret = $integration->getWebhookSecret();

        if (!$webhookUrl) {
            throw new Exception('Webhook URL not configured');
        }

        $orderData = $this->prepareOrderDataForWebhook();
        $signature = $this->generateWebhookSignature($orderData, $webhookSecret);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Event-Type' => 'order.created'
                ])
                ->post($webhookUrl, $orderData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'webhook_delivered' => true,
                    'response_data' => $response->json(),
                    'method' => 'webhook'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Webhook delivery failed',
                    'status_code' => $response->status(),
                    'method' => 'webhook'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Webhook delivery failed: ' . $e->getMessage(),
                'method' => 'webhook'
            ];
        }
    }

    private function sendViaEmail(SupplierIntegration $integration): array
    {
        return [
            'success' => true,
            'email_queued' => true,
            'method' => 'email',
            'note' => 'Email handled by listener'
        ];
    }

    private function prepareOrderDataForApi(): array
    {
        return [
            'external_order_id' => $this->dropshipOrder->id,
            'customer' => [
                'name' => $this->dropshipOrder->order->user->name ?? 'Guest Customer',
                'email' => $this->dropshipOrder->order->user->email ?? null
            ],
            'shipping_address' => $this->dropshipOrder->shipping_address,
            'items' => $this->dropshipOrder->dropshipOrderItems->map(function ($item) {
                return [
                    'sku' => $item->supplier_sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->getSupplierPriceInPounds(),
                    'product_name' => $item->getProductName()
                ];
            })->toArray(),
            'total_amount' => $this->dropshipOrder->getTotalCostInPounds(),
            'currency' => 'GBP',
            'notes' => $this->dropshipOrder->notes,
            'created_at' => $this->dropshipOrder->created_at->toISOString()
        ];
    }

    private function prepareOrderDataForWebhook(): array
    {
        return [
            'event_type' => 'order.created',
            'order' => $this->prepareOrderDataForApi(),
            'timestamp' => now()->toISOString(),
            'webhook_id' => uniqid('wh_', true)
        ];
    }

    private function prepareOrderDataForFtp(): array
    {
        $items = [];
        foreach ($this->dropshipOrder->dropshipOrderItems as $item) {
            $items[] = [
                'order_id' => $this->dropshipOrder->id,
                'sku' => $item->supplier_sku,
                'product_name' => $item->getProductName(),
                'quantity' => $item->quantity,
                'unit_price' => $item->getSupplierPriceInPounds(),
                'total_price' => $item->getTotalSupplierCostInPounds(),
                'customer_name' => $this->dropshipOrder->order->user->name ?? 'Guest',
                'shipping_address' => json_encode($this->dropshipOrder->shipping_address),
                'created_at' => $this->dropshipOrder->created_at->format('Y-m-d H:i:s')
            ];
        }
        return $items;
    }

    private function convertToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    private function generateWebhookSignature(array $data, ?string $secret): string
    {
        if (!$secret) {
            return '';
        }

        $payload = json_encode($data);
        return hash_hmac('sha256', $payload, $secret);
    }

    private function handleSuccessfulSubmission(array $result): void
    {
        $updateData = [
            'sent_to_supplier_at' => now(),
            'supplier_response' => $result
        ];

        if (!empty($result['supplier_order_id'])) {
            $updateData['supplier_order_id'] = $result['supplier_order_id'];
            $this->dropshipOrder->updateStatus(DropshipStatuses::CONFIRMED_BY_SUPPLIER, $updateData);
        } else {
            $this->dropshipOrder->updateStatus(DropshipStatuses::SENT_TO_SUPPLIER, $updateData);
        }

        $integration = $this->dropshipOrder->supplier->getActiveIntegration();
        if ($integration) {
            $integration->recordSuccessfulSync([
                'order_sent' => true,
                'method' => $result['method'] ?? $this->integrationType
            ]);
        }

        Log::info('Dropship order successfully sent to supplier', [
            'dropship_order_id' => $this->dropshipOrder->id,
            'method' => $result['method'] ?? $this->integrationType,
            'supplier_order_id' => $result['supplier_order_id'] ?? null
        ]);
    }

    private function handleFailedSubmission(array $result): void
    {
        $errorMessage = $result['error'] ?? 'Unknown error occurred';

        $this->dropshipOrder->update([
            'supplier_response' => $result,
            'notes' => ($this->dropshipOrder->notes ? $this->dropshipOrder->notes . "\n" : '') .
                "Failed to send to supplier: {$errorMessage}"
        ]);

        $integration = $this->dropshipOrder->supplier->getActiveIntegration();
        if ($integration) {
            $integration->recordFailedSync($errorMessage);
        }

        throw new Exception($errorMessage);
    }

    private function handleJobException(Exception $e): void
    {
        Log::error('Exception in SendDropshipOrderToSupplier job', [
            'dropship_order_id' => $this->dropshipOrder->id,
            'supplier_id' => $this->dropshipOrder->supplier_id,
            'integration_type' => $this->integrationType,
            'error' => $e->getMessage(),
            'attempt' => $this->attempts()
        ]);
    }

    private function getQueueName(string $integrationType): string
    {
        return match($integrationType) {
            SupplierIntegrationTypes::API => 'supplier_api',
            SupplierIntegrationTypes::WEBHOOK => 'supplier_webhooks',
            SupplierIntegrationTypes::FTP => 'supplier_ftp',
            SupplierIntegrationTypes::EMAIL => 'supplier_email',
            default => 'supplier_integration'
        };
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(6);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    private function sendViaFtp(SupplierIntegration $integration): array
    {
        $ftpHost = $integration->getFtpHost();
        $ftpUsername = $integration->getFtpUsername();
        $ftpPassword = $integration->getFtpPassword();
        $ftpPort = $integration->getFtpPort() ?? 21;

        if (!$ftpHost || !$ftpUsername || !$ftpPassword) {
            throw new Exception('FTP configuration incomplete');
        }

        $orderData = $this->prepareOrderDataForFtp();
        $config = $integration->configuration;
        $uploadDir = $config['upload_directory'] ?? '/orders';
        $filename = $config['filename_pattern'] ?? 'order_{order_id}_{timestamp}.csv';

        // Replace placeholders in filename
        $filename = str_replace(['{order_id}', '{timestamp}'], [
            $this->dropshipOrder->id,
            now()->format('YmdHis')
        ], $filename);

        try {
            // Create FTP connection
            $connection = ftp_connect($ftpHost, $ftpPort);
            if (!$connection) {
                throw new Exception("Could not connect to FTP server: {$ftpHost}:{$ftpPort}");
            }

            // Login to FTP server
            if (!ftp_login($connection, $ftpUsername, $ftpPassword)) {
                ftp_close($connection);
                throw new Exception('FTP login failed');
            }

            // Set passive mode if configured
            if ($config['passive_mode'] ?? true) {
                ftp_pasv($connection, true);
            }

            // Change to upload directory
            if (!ftp_chdir($connection, $uploadDir)) {
                ftp_close($connection);
                throw new Exception("Could not change to directory: {$uploadDir}");
            }

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'dropship_order_');
            file_put_contents($tempFile, $this->convertToCsv($orderData));

            // Upload file
            if (!ftp_put($connection, $filename, $tempFile, FTP_BINARY)) {
                ftp_close($connection);
                unlink($tempFile);
                throw new Exception('FTP upload failed');
            }

            // Cleanup
            ftp_close($connection);
            unlink($tempFile);

            return [
                'success' => true,
                'filename' => $filename,
                'upload_path' => $uploadDir . '/' . $filename,
                'method' => 'ftp'
            ];

        } catch (Exception $e) {
            if (isset($connection) && is_resource($connection)) {
                ftp_close($connection);
            }
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }

            return [
                'success' => false,
                'error' => 'FTP upload failed: ' . $e->getMessage(),
                'method' => 'ftp'
            ];
        }
    }

    // Enhanced error handling with retry logic
    public function failed(Throwable $exception): void
    {
        Log::error('Dropship order job failed permanently', [
            'dropship_order_id' => $this->dropshipOrder->id,
            'attempts' => $this->attempts(),
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Mark order as failed
        $this->dropshipOrder->updateStatus(DropshipStatuses::FAILED, [
            'failed_at' => now(),
            'failure_reason' => $exception->getMessage(),
            'attempts_made' => $this->attempts()
        ]);

        // Send failure notification to admins
        $this->notifyAdminsOfFailure($exception);

        // Record integration failure
        $integration = $this->dropshipOrder->supplier->getActiveIntegration();
        if ($integration) {
            $integration->recordFailedSync($exception->getMessage());
        }
    }

    private function notifyAdminsOfFailure(Throwable $exception): void
    {
        try {
            $adminEmails = User::whereHas('roles', function($query) {
                $query->whereIn('name', ['super admin', 'admin']);
            })->pluck('email')->toArray();

            $emailData = [
                'dropship_order' => [
                    'id' => $this->dropshipOrder->id,
                    'order_id' => $this->dropshipOrder->order_id,
                    'supplier_name' => $this->dropshipOrder->supplier->name,
                    'created_at' => $this->dropshipOrder->created_at->format('M j, Y g:i A'),
                ],
                'error' => [
                    'message' => $exception->getMessage(),
                    'attempts' => $this->attempts(),
                    'integration_type' => $this->integrationType,
                    'occurred_at' => now()->format('M j, Y g:i A'),
                ],
                'customer' => [
                    'name' => $this->dropshipOrder->order->user->name ?? 'Guest',
                    'email' => $this->dropshipOrder->order->user->email ?? 'N/A',
                ]
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new DropshipOrderFailedMail($emailData));
            }

        } catch (Exception $e) {
            Log::error('Failed to send dropship failure notification', [
                'dropship_order_id' => $this->dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

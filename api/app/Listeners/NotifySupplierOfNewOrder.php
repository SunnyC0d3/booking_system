<?php

namespace App\Listeners;

use App\Events\DropshipOrderCreated;
use App\Models\DropshipOrder;
use App\Models\Supplier;
use App\Models\SupplierIntegration;
use App\Constants\SupplierIntegrationTypes;
use App\Jobs\SendDropshipOrderToSupplier;
use App\Mail\SupplierOrderNotification;
use App\Services\V1\Emails\Email;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class NotifySupplierOfNewOrder implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'supplier_notifications';
    public $delay = 60;

    private Email $emailService;

    public function __construct(Email $emailService)
    {
        $this->emailService = $emailService;
    }

    public function handle(DropshipOrderCreated $event): void
    {
        $dropshipOrder = $event->dropshipOrder;

        try {
            Log::info('Processing supplier notification for new dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_id' => $dropshipOrder->supplier_id,
                'order_id' => $dropshipOrder->order_id
            ]);

            $supplier = $dropshipOrder->supplier;

            if (!$supplier->isActive()) {
                Log::warning('Skipping notification - supplier is not active', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'supplier_id' => $supplier->id,
                    'supplier_status' => $supplier->status
                ]);
                return;
            }

            $this->processSupplierNotification($dropshipOrder, $supplier);

        } catch (Exception $e) {
            Log::error('Failed to notify supplier of new dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_id' => $dropshipOrder->supplier_id,
                'error' => $e->getMessage()
            ]);

            $this->failed($e);
        }
    }

    public function failed(Exception $exception): void
    {
        Log::critical('NotifySupplierOfNewOrder listener failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }

    private function processSupplierNotification(DropshipOrder $dropshipOrder, Supplier $supplier): void
    {
        $integration = $supplier->getActiveIntegration();

        if ($integration && $integration->isAutomated()) {
            $this->handleAutomatedNotification($dropshipOrder, $integration);
        } else {
            $this->handleManualNotification($dropshipOrder, $supplier);
        }

        $this->sendInternalNotification($dropshipOrder);
    }

    private function handleAutomatedNotification(DropshipOrder $dropshipOrder, SupplierIntegration $integration): void
    {
        switch ($integration->integration_type) {
            case SupplierIntegrationTypes::API:
                $this->handleApiNotification($dropshipOrder, $integration);
                break;

            case SupplierIntegrationTypes::WEBHOOK:
                $this->handleWebhookNotification($dropshipOrder, $integration);
                break;

            case SupplierIntegrationTypes::FTP:
                $this->handleFtpNotification($dropshipOrder, $integration);
                break;

            default:
                $this->handleEmailNotification($dropshipOrder, $integration);
        }
    }

    private function handleApiNotification(DropshipOrder $dropshipOrder, SupplierIntegration $integration): void
    {
        SendDropshipOrderToSupplier::dispatch($dropshipOrder, 'api')
            ->onQueue('supplier_api')
            ->delay(now()->addSeconds(30));

        Log::info('API notification job dispatched', [
            'dropship_order_id' => $dropshipOrder->id,
            'integration_id' => $integration->id,
            'api_endpoint' => $integration->getApiEndpoint()
        ]);
    }

    private function handleWebhookNotification(DropshipOrder $dropshipOrder, SupplierIntegration $integration): void
    {
        SendDropshipOrderToSupplier::dispatch($dropshipOrder, 'webhook')
            ->onQueue('supplier_webhooks')
            ->delay(now()->addSeconds(15));

        Log::info('Webhook notification job dispatched', [
            'dropship_order_id' => $dropshipOrder->id,
            'integration_id' => $integration->id,
            'webhook_url' => $integration->getWebhookUrl()
        ]);
    }

    private function handleFtpNotification(DropshipOrder $dropshipOrder, SupplierIntegration $integration): void
    {
        SendDropshipOrderToSupplier::dispatch($dropshipOrder, 'ftp')
            ->onQueue('supplier_ftp')
            ->delay(now()->addMinutes(2));

        Log::info('FTP notification job dispatched', [
            'dropship_order_id' => $dropshipOrder->id,
            'integration_id' => $integration->id,
            'ftp_host' => $integration->getFtpHost()
        ]);
    }

    private function handleEmailNotification(DropshipOrder $dropshipOrder, SupplierIntegration $integration): void
    {
        $emailAddress = $integration->getEmailAddress();

        if (!$emailAddress) {
            Log::warning('Email integration has no email address configured', [
                'dropship_order_id' => $dropshipOrder->id,
                'integration_id' => $integration->id
            ]);
            return;
        }

        $this->sendSupplierEmail($dropshipOrder, $emailAddress, $integration);
    }

    private function handleManualNotification(DropshipOrder $dropshipOrder, Supplier $supplier): void
    {
        if ($supplier->email) {
            $this->sendSupplierEmail($dropshipOrder, $supplier->email);
        }

        $this->createManualProcessingTask($dropshipOrder, $supplier);
    }

    private function sendSupplierEmail(DropshipOrder $dropshipOrder, string $emailAddress, ?SupplierIntegration $integration = null): void
    {
        try {
            $orderData = $this->prepareOrderDataForEmail($dropshipOrder);

            Mail::to($emailAddress)->send(new SupplierOrderNotification($dropshipOrder, $orderData));

            Log::info('Supplier email notification sent', [
                'dropship_order_id' => $dropshipOrder->id,
                'email_address' => $emailAddress,
                'integration_id' => $integration?->id
            ]);

            $dropshipOrder->update([
                'sent_to_supplier_at' => now(),
                'supplier_response' => [
                    'method' => 'email',
                    'sent_to' => $emailAddress,
                    'sent_at' => now()->toISOString()
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to send supplier email notification', [
                'dropship_order_id' => $dropshipOrder->id,
                'email_address' => $emailAddress,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function prepareOrderDataForEmail(DropshipOrder $dropshipOrder): array
    {
        return [
            'order_number' => $dropshipOrder->order->order_number ?? $dropshipOrder->order_id,
            'dropship_order_id' => $dropshipOrder->id,
            'customer' => [
                'name' => $dropshipOrder->order->user->name ?? 'Guest Customer',
                'email' => $dropshipOrder->order->user->email ?? null
            ],
            'shipping_address' => $dropshipOrder->shipping_address,
            'items' => $dropshipOrder->dropshipOrderItems->map(function ($item) {
                return [
                    'supplier_sku' => $item->supplier_sku,
                    'product_name' => $item->getProductName(),
                    'quantity' => $item->quantity,
                    'unit_price' => $item->getSupplierPriceFormatted(),
                    'total_price' => $item->getTotalSupplierCostFormatted(),
                    'product_details' => $item->product_details
                ];
            })->toArray(),
            'total_cost' => $dropshipOrder->getTotalCostFormatted(),
            'notes' => $dropshipOrder->notes,
            'created_at' => $dropshipOrder->created_at->format('Y-m-d H:i:s'),
            'supplier' => [
                'name' => $dropshipOrder->supplier->name,
                'contact_person' => $dropshipOrder->supplier->contact_person
            ]
        ];
    }

    private function createManualProcessingTask(DropshipOrder $dropshipOrder, Supplier $supplier): void
    {
        Log::info('Manual processing task created for dropship order', [
            'dropship_order_id' => $dropshipOrder->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'integration_type' => 'manual',
            'action_required' => 'Manual order processing needed'
        ]);

        $dropshipOrder->update([
            'notes' => ($dropshipOrder->notes ? $dropshipOrder->notes . "\n" : '') .
                'Manual processing required - no automated integration available. Created at: ' . now()->toDateTimeString()
        ]);
    }

    private function sendInternalNotification(DropshipOrder $dropshipOrder): void
    {
        $notificationData = [
            'type' => 'dropship_order_created',
            'dropship_order_id' => $dropshipOrder->id,
            'order_id' => $dropshipOrder->order_id,
            'supplier_name' => $dropshipOrder->supplier->name,
            'customer_name' => $dropshipOrder->order->user->name ?? 'Guest',
            'total_value' => $dropshipOrder->getTotalRetailFormatted(),
            'items_count' => $dropshipOrder->dropshipOrderItems->count(),
            'integration_type' => $dropshipOrder->supplier->integration_type,
            'auto_fulfill' => $dropshipOrder->supplier->canAutoFulfill(),
            'created_at' => $dropshipOrder->created_at->toISOString()
        ];

        Log::info('Internal notification: New dropship order created', $notificationData);

        if ($dropshipOrder->getTotalRetailInPounds() > 500) {
            Log::notice('High-value dropship order created', [
                'dropship_order_id' => $dropshipOrder->id,
                'total_value' => $dropshipOrder->getTotalRetailFormatted(),
                'requires_attention' => true
            ]);
        }
    }

    public function viaQueue(): string
    {
        return 'supplier_notifications';
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2);
    }

    public function backoff(): array
    {
        return [60, 180, 300];
    }
}

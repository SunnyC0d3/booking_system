<?php

namespace App\Services\V1\Dropshipping;

use App\Mail\DropshipOrderConfirmedMail;
use App\Mail\DropshipOrderShippedMail;
use App\Mail\DropshipOrderRejectedMail;
use App\Mail\DropshipOrderDelayedMail;
use App\Mail\DropshipOrderRetryMail;
use App\Mail\DropshipSupplierAlertMail;
use App\Mail\DropshipIntegrationFailedMail;
use App\Mail\VendorDropshipReportMail;
use App\Mail\SupplierPerformanceAlertMail;
use App\Models\DropshipOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class DropshipEmailService
{
    public function sendOrderConfirmed(DropshipOrder $dropshipOrder): void
    {
        try {
            $emailData = [
                'customer' => [
                    'name' => $dropshipOrder->order->user->name ?? 'Customer',
                    'email' => $dropshipOrder->order->user->email,
                ],
                'order' => [
                    'id' => $dropshipOrder->order_id,
                    'created_at' => $dropshipOrder->order->created_at->format('M j, Y'),
                    'total_formatted' => $dropshipOrder->order->getTotalFormattedAttribute(),
                ],
                'dropship_order' => [
                    'id' => $dropshipOrder->id,
                    'supplier_order_id' => $dropshipOrder->supplier_order_id,
                    'confirmed_at' => $dropshipOrder->confirmed_at?->format('M j, Y g:i A'),
                    'estimated_delivery' => $dropshipOrder->estimated_delivery?->format('M j, Y'),
                    'total_retail_formatted' => $dropshipOrder->getTotalRetailFormatted(),
                    'items' => $dropshipOrder->dropshipOrderItems->map(function ($item) {
                        return [
                            'product_name' => $item->getProductName(),
                            'supplier_sku' => $item->supplier_sku,
                            'quantity' => $item->quantity,
                            'total_formatted' => $item->getTotalRetailFormatted(),
                        ];
                    })->toArray(),
                ],
                'supplier' => [
                    'name' => $dropshipOrder->supplier->name,
                ],
                'shipping_address' => $dropshipOrder->shipping_address,
            ];

            if ($dropshipOrder->order->user) {
                Mail::to($dropshipOrder->order->user->email)
                    ->send(new DropshipOrderConfirmedMail($emailData));
            }

            Log::info('Dropship order confirmed email sent', [
                'dropship_order_id' => $dropshipOrder->id,
                'customer_email' => $dropshipOrder->order->user->email ?? 'N/A'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send dropship order confirmed email', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendOrderShipped(DropshipOrder $dropshipOrder): void
    {
        try {
            $emailData = [
                'customer' => [
                    'name' => $dropshipOrder->order->user->name ?? 'Customer',
                    'email' => $dropshipOrder->order->user->email,
                ],
                'order' => [
                    'id' => $dropshipOrder->order_id,
                    'created_at' => $dropshipOrder->order->created_at->format('M j, Y'),
                ],
                'dropship_order' => [
                    'id' => $dropshipOrder->id,
                    'tracking_number' => $dropshipOrder->tracking_number,
                    'carrier' => $dropshipOrder->carrier ?? 'Standard Shipping',
                    'shipped_at' => $dropshipOrder->shipped_by_supplier_at?->format('M j, Y g:i A'),
                    'estimated_delivery' => $dropshipOrder->estimated_delivery?->format('M j, Y'),
                    'tracking_url' => $this->generateTrackingUrl($dropshipOrder),
                    'items' => $dropshipOrder->dropshipOrderItems->map(function ($item) {
                        return [
                            'product_name' => $item->getProductName(),
                            'supplier_sku' => $item->supplier_sku,
                            'quantity' => $item->quantity,
                            'total_formatted' => $item->getTotalRetailFormatted(),
                        ];
                    })->toArray(),
                ],
                'supplier' => [
                    'name' => $dropshipOrder->supplier->name,
                ],
            ];

            if ($dropshipOrder->order->user) {
                Mail::to($dropshipOrder->order->user->email)
                    ->send(new DropshipOrderShippedMail($emailData));
            }

            Log::info('Dropship order shipped email sent', [
                'dropship_order_id' => $dropshipOrder->id,
                'tracking_number' => $dropshipOrder->tracking_number
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send dropship order shipped email', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendOrderRejected(DropshipOrder $dropshipOrder, string $reason = null): void
    {
        try {
            $emailData = [
                'customer' => [
                    'name' => $dropshipOrder->order->user->name ?? 'Customer',
                    'email' => $dropshipOrder->order->user->email,
                ],
                'order' => [
                    'id' => $dropshipOrder->order_id,
                    'created_at' => $dropshipOrder->order->created_at->format('M j, Y'),
                ],
                'dropship_order' => [
                    'id' => $dropshipOrder->id,
                    'rejected_at' => now()->format('M j, Y g:i A'),
                    'rejection_reason' => $reason,
                    'total_retail_formatted' => $dropshipOrder->getTotalRetailFormatted(),
                    'items' => $dropshipOrder->dropshipOrderItems->map(function ($item) {
                        return [
                            'product_name' => $item->getProductName(),
                            'quantity' => $item->quantity,
                            'status' => ucfirst($item->status),
                            'total_formatted' => $item->getTotalRetailFormatted(),
                        ];
                    })->toArray(),
                ],
                'supplier' => [
                    'name' => $dropshipOrder->supplier->name,
                ],
            ];

            if ($dropshipOrder->order->user) {
                Mail::to($dropshipOrder->order->user->email)
                    ->send(new DropshipOrderRejectedMail($emailData));
            }

            Log::info('Dropship order rejected email sent', [
                'dropship_order_id' => $dropshipOrder->id,
                'reason' => $reason
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send dropship order rejected email', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendOrderDelayed(DropshipOrder $dropshipOrder, array $delayInfo): void
    {
        try {
            $emailData = [
                'customer' => [
                    'name' => $dropshipOrder->order->user->name ?? 'Customer',
                    'email' => $dropshipOrder->order->user->email,
                ],
                'order' => [
                    'id' => $dropshipOrder->order_id,
                ],
                'dropship_order' => [
                    'id' => $dropshipOrder->id,
                    'status' => ucfirst($dropshipOrder->status),
                    'original_estimated_delivery' => $delayInfo['original_delivery']?->format('M j, Y'),
                    'new_estimated_delivery' => $delayInfo['new_delivery']?->format('M j, Y'),
                    'tracking_number' => $dropshipOrder->tracking_number,
                    'tracking_url' => $this->generateTrackingUrl($dropshipOrder),
                    'items' => $dropshipOrder->dropshipOrderItems->map(function ($item) {
                        return [
                            'product_name' => $item->getProductName(),
                            'quantity' => $item->quantity,
                            'total_formatted' => $item->getTotalRetailFormatted(),
                        ];
                    })->toArray(),
                ],
                'supplier' => [
                    'name' => $dropshipOrder->supplier->name,
                ],
                'delay' => [
                    'days_delayed' => $delayInfo['days_delayed'],
                    'reason' => $delayInfo['reason'] ?? null,
                ],
            ];

            if ($dropshipOrder->order->user) {
                Mail::to($dropshipOrder->order->user->email)
                    ->send(new DropshipOrderDelayedMail($emailData));
            }

            Log::info('Dropship order delayed email sent', [
                'dropship_order_id' => $dropshipOrder->id,
                'days_delayed' => $delayInfo['days_delayed']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send dropship order delayed email', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendOrderRetry(DropshipOrder $dropshipOrder, int $attemptNumber, string $reason = null): void
    {
        try {
            $emailData = [
                'customer' => [
                    'name' => $dropshipOrder->order->user->name ?? 'Customer',
                    'email' => $dropshipOrder->order->user->email,
                ],
                'order' => [
                    'id' => $dropshipOrder->order_id,
                ],
                'dropship_order' => [
                    'id' => $dropshipOrder->id,
                    'total_retail_formatted' => $dropshipOrder->getTotalRetailFormatted(),
                    'items' => $dropshipOrder->dropshipOrderItems->map(function ($item) {
                        return [
                            'product_name' => $item->getProductName(),
                            'quantity' => $item->quantity,
                            'total_formatted' => $item->getTotalRetailFormatted(),
                        ];
                    })->toArray(),
                ],
                'supplier' => [
                    'name' => $dropshipOrder->supplier->name,
                ],
                'retry' => [
                    'attempt' => $attemptNumber,
                    'reason' => $reason,
                    'initiated_at' => now()->format('M j, Y g:i A'),
                ],
            ];

            if ($dropshipOrder->order->user) {
                Mail::to($dropshipOrder->order->user->email)
                    ->send(new DropshipOrderRetryMail($emailData));
            }

            Log::info('Dropship order retry email sent', [
                'dropship_order_id' => $dropshipOrder->id,
                'attempt' => $attemptNumber
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send dropship order retry email', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendSupplierAlert(Supplier $supplier, array $issueData): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            $emailData = [
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'status' => $supplier->status,
                    'integration_type' => $supplier->integration_type,
                ],
                'issue' => [
                    'type' => $issueData['type'],
                    'severity' => $issueData['severity'],
                    'description' => $issueData['description'],
                    'detected_at' => now()->format('M j, Y g:i A'),
                    'active_orders' => $issueData['active_orders'] ?? 0,
                    'affected_orders' => $issueData['affected_orders'] ?? [],
                    'recommended_actions' => $issueData['recommended_actions'] ?? [],
                ],
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new DropshipSupplierAlertMail($emailData));
            }

            Log::info('Supplier alert email sent', [
                'supplier_id' => $supplier->id,
                'issue_type' => $issueData['type'],
                'severity' => $issueData['severity']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send supplier alert email', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendIntegrationFailed(Supplier $supplier, array $integrationData): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            $emailData = [
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ],
                'integration' => $integrationData,
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new DropshipIntegrationFailedMail($emailData));
            }

            Log::info('Integration failed email sent', [
                'supplier_id' => $supplier->id,
                'integration_type' => $integrationData['type']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send integration failed email', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendVendorWeeklyReport(Vendor $vendor, array $reportData): void
    {
        try {
            $emailData = [
                'vendor' => [
                    'name' => $vendor->name,
                    'user_name' => $vendor->user->name,
                ],
                'report' => $reportData,
            ];

            Mail::to($vendor->user->email)
                ->send(new VendorDropshipReportMail($emailData));

            Log::info('Vendor weekly dropship report sent', [
                'vendor_id' => $vendor->id,
                'report_period' => $reportData['period']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send vendor weekly dropship report', [
                'vendor_id' => $vendor->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function sendSupplierPerformanceAlert(Supplier $supplier, array $performanceData): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            $emailData = [
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ],
                'performance' => $performanceData,
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new SupplierPerformanceAlertMail($emailData));
            }

            Log::info('Supplier performance alert sent', [
                'supplier_id' => $supplier->id,
                'metric' => $performanceData['metric']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send supplier performance alert', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function generateTrackingUrl(DropshipOrder $dropshipOrder): ?string
    {
        if (!$dropshipOrder->tracking_number) {
            return null;
        }

        $carrier = strtolower($dropshipOrder->carrier ?? '');
        $trackingNumber = $dropshipOrder->tracking_number;

        return match ($carrier) {
            'royal mail' => "https://www.royalmail.com/track-your-item?trackNumber={$trackingNumber}",
            'dpd' => "https://www.dpd.co.uk/apps/tracking/?reference={$trackingNumber}",
            'ups' => "https://www.ups.com/track?tracknum={$trackingNumber}",
            'dhl' => "https://www.dhl.com/en/express/tracking.html?AWB={$trackingNumber}",
            'fedex' => "https://www.fedex.com/fedextrack/?tracknumber={$trackingNumber}",
            'hermes' => "https://www.myhermes.co.uk/track#{$trackingNumber}",
            'yodel' => "https://www.yodel.co.uk/track/{$trackingNumber}",
            default => null,
        };
    }

    public function sendBulkOrderNotifications(array $dropshipOrders, string $type, array $additionalData = []): void
    {
        foreach ($dropshipOrders as $dropshipOrder) {
            try {
                match ($type) {
                    'confirmed' => $this->sendOrderConfirmed($dropshipOrder),
                    'shipped' => $this->sendOrderShipped($dropshipOrder),
                    'rejected' => $this->sendOrderRejected($dropshipOrder, $additionalData['reason'] ?? null),
                    'delayed' => $this->sendOrderDelayed($dropshipOrder, $additionalData),
                    'retry' => $this->sendOrderRetry(
                        $dropshipOrder,
                        $additionalData['attempt'] ?? 1,
                        $additionalData['reason'] ?? null
                    ),
                    default => Log::warning('Unknown bulk email type', ['type' => $type]),
                };
            } catch (\Exception $e) {
                Log::error('Failed to send bulk dropship email', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'type' => $type,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function sendDailyIssuesSummary(): void
    {
        try {
            $adminEmails = User::where('is_admin', true)->pluck('email')->toArray();

            // Get today's issues
            $overdueOrders = DropshipOrder::overdue()->count();
            $failedOrders = DropshipOrder::whereIn('status', ['rejected_by_supplier', 'cancelled'])->whereDate('updated_at', today())->count();
            $supplierIssues = Supplier::where('status', 'inactive')->whereDate('updated_at', today())->count();

            if ($overdueOrders === 0 && $failedOrders === 0 && $supplierIssues === 0) {
                return; // No issues to report
            }

            $emailData = [
                'summary' => [
                    'date' => now()->format('M j, Y'),
                    'overdue_orders' => $overdueOrders,
                    'failed_orders' => $failedOrders,
                    'supplier_issues' => $supplierIssues,
                    'total_issues' => $overdueOrders + $failedOrders + $supplierIssues,
                ],
                'details' => [
                    'top_issues' => $this->getTopIssues(),
                    'affected_suppliers' => $this->getAffectedSuppliers(),
                ],
            ];

            foreach ($adminEmails as $email) {
                Mail::to($email)->send(new DropshipSupplierAlertMail($emailData));
            }

            Log::info('Daily issues summary sent', [
                'total_issues' => $emailData['summary']['total_issues']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send daily issues summary', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getTopIssues(): array
    {
        return [
            'Overdue deliveries from Supplier ABC',
            'Integration timeout with Supplier XYZ',
            'High rejection rate from Supplier DEF',
        ];
    }

    private function getAffectedSuppliers(): array
    {
        return Supplier::whereHas('dropshipOrders', function ($query) {
            $query->whereIn('status', ['rejected_by_supplier', 'cancelled'])
                ->whereDate('updated_at', today());
        })
            ->limit(5)
            ->get(['id', 'name', 'status'])
            ->toArray();
    }
}

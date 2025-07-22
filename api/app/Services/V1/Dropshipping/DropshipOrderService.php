<?php

namespace App\Services\V1\Dropshipping;

use App\Models\DropshipOrder;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Constants\DropshipStatuses;
use App\Resources\V1\DropshipOrderResource;
use App\Services\V1\Dropshipping\DropshipEmailService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Exception;

class DropshipOrderService
{
    protected DropshipEmailService $emailService;

    public function __construct(DropshipEmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    public function getPaginatedOrders(array $filters, User $user): \Illuminate\Pagination\LengthAwarePaginator
    {
        Log::info('Retrieving dropship orders', [
            'user_id' => $user->id,
            'filters' => $filters,
        ]);

        $dropshipOrders = DropshipOrder::query()
            ->with(['order.user', 'supplier', 'dropshipOrderItems.supplierProduct'])
            ->when(!empty($filters['supplier_id']), fn($query) => $query->where('supplier_id', $filters['supplier_id']))
            ->when(!empty($filters['status']), fn($query) => $query->where('status', $filters['status']))
            ->when(!empty($filters['order_id']), fn($query) => $query->where('order_id', $filters['order_id']))
            ->when(!empty($filters['search']), function($query) use ($filters) {
                $query->where(function($q) use ($filters) {
                    $q->where('supplier_order_id', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('tracking_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('order.user', function($userQuery) use ($filters) {
                            $userQuery->where('name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when(!empty($filters['date_from']), fn($query) => $query->where('created_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($query) => $query->where('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['overdue']), function($query) use ($filters) {
                if ($filters['overdue']) {
                    $query->overdue();
                }
            })
            ->when(isset($filters['needs_retry']), function($query) use ($filters) {
                if ($filters['needs_retry']) {
                    $query->needsRetry();
                }
            })
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        Log::info('Dropship orders retrieved successfully', [
            'user_id' => $user->id,
            'total_orders' => $dropshipOrders->total(),
            'current_page' => $dropshipOrders->currentPage()
        ]);

        return $dropshipOrders;
    }

    public function createDropshipOrder(array $data): DropshipOrder
    {
        return DB::transaction(function () use ($data) {
            $order = Order::findOrFail($data['order_id']);
            $supplier = Supplier::findOrFail($data['supplier_id']);

            if (!$supplier->isActive()) {
                throw new Exception('Supplier is not active.');
            }

            $dropshipOrder = DropshipOrder::create([
                'order_id' => $order->id,
                'supplier_id' => $supplier->id,
                'status' => DropshipStatuses::PENDING,
                'total_cost' => (int) ($data['total_cost'] * 100), // Convert to pennies
                'total_retail' => (int) ($data['total_retail'] * 100), // Convert to pennies
                'profit_margin' => (int) (($data['total_retail'] - $data['total_cost']) * 100),
                'shipping_address' => $data['shipping_address'],
                'notes' => $data['notes'] ?? null,
                'auto_retry_enabled' => $data['auto_retry_enabled'] ?? true,
            ]);

            foreach ($data['items'] as $itemData) {
                $dropshipOrder->dropshipOrderItems()->create([
                    'order_item_id' => $itemData['order_item_id'],
                    'supplier_product_id' => $itemData['supplier_product_id'],
                    'supplier_sku' => $itemData['supplier_sku'],
                    'quantity' => $itemData['quantity'],
                    'supplier_price' => (int) ($itemData['supplier_price'] * 100), // Convert to pennies
                    'retail_price' => (int) ($itemData['retail_price'] * 100), // Convert to pennies
                    'profit_per_item' => (int) (($itemData['retail_price'] - $itemData['supplier_price']) * 100),
                    'product_details' => $itemData['product_details'] ?? null,
                    'status' => DropshipStatuses::PENDING,
                ]);
            }

            return $dropshipOrder;
        });
    }

    public function getDropshipOrder(DropshipOrder $dropshipOrder, User $user): DropshipOrder
    {
        Log::info('Retrieving dropship order', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
        ]);

        $dropshipOrder->load([
            'order.user',
            'supplier',
            'dropshipOrderItems.supplierProduct',
            'dropshipOrderItems.orderItem.product'
        ]);

        Log::info('Dropship order retrieved successfully', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'order_id' => $dropshipOrder->order_id,
            'supplier_id' => $dropshipOrder->supplier_id
        ]);

        return $dropshipOrder;
    }

    public function updateDropshipOrder(DropshipOrder $dropshipOrder, array $data): DropshipOrder
    {
        return DB::transaction(function () use ($dropshipOrder, $data) {
            $originalStatus = $dropshipOrder->status;

            $dropshipOrder->update($data);

            if (isset($data['status']) && $originalStatus !== $data['status']) {
                $this->updateOrderFromDropshipStatus($dropshipOrder);

                Log::info('Dropship order status changed', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'old_status' => $originalStatus,
                    'new_status' => $data['status']
                ]);
            }

            return $dropshipOrder;
        });
    }

    public function deleteDropshipOrder(DropshipOrder $dropshipOrder): void
    {
        if (!in_array($dropshipOrder->status, [DropshipStatuses::PENDING, DropshipStatuses::CANCELLED])) {
            throw new Exception('Cannot delete dropship order that has been sent to supplier.');
        }

        DB::transaction(function () use ($dropshipOrder) {
            $dropshipOrder->dropshipOrderItems()->delete();
            $dropshipOrder->delete();
        });
    }

    public function sendToSupplier(DropshipOrder $dropshipOrder, User $user): DropshipOrder
    {
        Log::info('Attempting to send dropship order to supplier', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'supplier_id' => $dropshipOrder->supplier_id,
            'current_status' => $dropshipOrder->status,
        ]);

        if (!$dropshipOrder->isPending()) {
            Log::warning('Cannot send dropship order - not in pending status', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'status' => $dropshipOrder->status
            ]);
            throw new Exception('Dropship order has already been sent to supplier.');
        }

        $supplier = $dropshipOrder->supplier;
        if (!$supplier->isActive()) {
            Log::warning('Cannot send dropship order - supplier not active', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_id' => $supplier->id,
                'supplier_status' => $supplier->status
            ]);
            throw new Exception('Supplier is not active.');
        }

        DB::transaction(function () use ($dropshipOrder, $supplier, $user) {
            $dropshipOrder->markAsSentToSupplier([
                'sent_at' => now(),
                'integration_type' => $supplier->integration_type,
                'sent_by_user_id' => $user->id,
            ]);

            Log::info('Dropship order sent to supplier successfully', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_id' => $supplier->id,
                'integration_type' => $supplier->integration_type
            ]);
        });

        return $dropshipOrder;
    }

    public function markAsConfirmed(DropshipOrder $dropshipOrder, array $data, User $user): DropshipOrder
    {
        Log::info('Marking dropship order as confirmed', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'supplier_order_id' => $data['supplier_order_id'],
        ]);

        $dropshipOrder->markAsConfirmed(
            $data['supplier_order_id'],
            $data['supplier_response'] ?? []
        );

        if (isset($data['estimated_delivery'])) {
            $dropshipOrder->update(['estimated_delivery' => $data['estimated_delivery']]);
        }

        // Send confirmation email
        $this->emailService->sendOrderConfirmed($dropshipOrder);

        Log::info('Dropship order confirmed by supplier', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'supplier_order_id' => $data['supplier_order_id']
        ]);

        return $dropshipOrder;
    }

    public function markAsShipped(DropshipOrder $dropshipOrder, array $data, User $user): DropshipOrder
    {
        Log::info('Marking dropship order as shipped', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'tracking_number' => $data['tracking_number'],
            'carrier' => $data['carrier'] ?? 'Unknown',
        ]);

        $dropshipOrder->markAsShipped(
            $data['tracking_number'],
            $data['carrier'] ?? null,
            isset($data['estimated_delivery']) ? \Carbon\Carbon::parse($data['estimated_delivery']) : null
        );

        // Send shipping email
        $this->emailService->sendOrderShipped($dropshipOrder);

        Log::info('Dropship order marked as shipped', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'tracking_number' => $data['tracking_number'],
            'carrier' => $data['carrier'] ?? 'Unknown'
        ]);

        return $dropshipOrder;
    }

    public function markAsDelivered(DropshipOrder $dropshipOrder, User $user): DropshipOrder
    {
        Log::info('Marking dropship order as delivered', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
        ]);

        $dropshipOrder->markAsDelivered();

        Log::info('Dropship order marked as delivered', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id
        ]);

        return $dropshipOrder;
    }

    public function cancelDropshipOrder(DropshipOrder $dropshipOrder, ?string $reason = null): void
    {
        if ($dropshipOrder->isDelivered()) {
            throw new Exception('Cannot cancel delivered dropship order.');
        }

        $dropshipOrder->markAsCancelled($reason);
        $this->updateOrderFromDropshipStatus($dropshipOrder);
    }

    public function retryDropshipOrder(DropshipOrder $dropshipOrder, User $user): DropshipOrder
    {
        Log::info('Retrying dropship order', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'current_retry_count' => $dropshipOrder->retry_count,
        ]);

        if (!$dropshipOrder->canRetry()) {
            Log::warning('Cannot retry dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'status' => $dropshipOrder->status,
                'retry_count' => $dropshipOrder->retry_count
            ]);
            throw new Exception('Dropship order cannot be retried.');
        }

        $dropshipOrder->incrementRetryCount();
        $dropshipOrder->updateStatus(DropshipStatuses::PENDING);

        Log::info('Dropship order retry initiated', [
            'user_id' => $user->id,
            'dropship_order_id' => $dropshipOrder->id,
            'retry_count' => $dropshipOrder->retry_count
        ]);

        return $dropshipOrder;
    }

    public function bulkUpdateStatus(array $orderIds, string $status, ?string $notes = null): array
    {
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($orderIds, $status, $notes, &$updated, &$errors) {
            $orders = DropshipOrder::whereIn('id', $orderIds)->get();

            foreach ($orders as $order) {
                try {
                    $this->updateDropshipOrder($order, [
                        'status' => $status,
                        'notes' => $notes ? ($order->notes . "\n" . $notes) : $order->notes
                    ]);
                    $updated++;
                } catch (Exception $e) {
                    $errors[] = "Order {$order->id}: " . $e->getMessage();
                }
            }
        });

        return [
            'updated_count' => $updated,
            'error_count' => count($errors),
            'new_status' => $status,
            'errors' => $errors
        ];
    }

    public function getStatistics(User $user): array
    {
        Log::info('Retrieving dropship order statistics', [
            'user_id' => $user->id,
        ]);

        $stats = [
            'totals' => [
                'all_orders' => DropshipOrder::count(),
                'pending' => DropshipOrder::pending()->count(),
                'active' => DropshipOrder::active()->count(),
                'completed' => DropshipOrder::completed()->count(),
                'overdue' => DropshipOrder::overdue()->count(),
            ],
            'by_status' => DropshipOrder::selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_supplier' => DropshipOrder::with('supplier:id,name')
                ->selectRaw('supplier_id, count(*) as count')
                ->groupBy('supplier_id')
                ->get()
                ->map(function($item) {
                    return [
                        'supplier_name' => $item->supplier->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                }),
            'recent_activity' => DropshipOrder::with(['order.user', 'supplier'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(function($order) {
                    return [
                        'id' => $order->id,
                        'order_id' => $order->order_id,
                        'supplier_name' => $order->supplier->name,
                        'customer_name' => $order->order->user->name ?? 'Guest',
                        'status' => $order->status,
                        'total_cost' => $order->getTotalCostFormatted(),
                        'created_at' => $order->created_at,
                    ];
                }),
        ];

        Log::info('Dropship order statistics retrieved successfully', [
            'user_id' => $user->id,
            'total_orders' => $stats['totals']['all_orders'],
            'pending_orders' => $stats['totals']['pending']
        ]);

        return $stats;
    }

    public function getFilteredOrders(array $filters): LengthAwarePaginator
    {
        $query = DropshipOrder::query()
            ->with(['order.user', 'supplier', 'dropshipOrderItems.supplierProduct'])
            ->when(!empty($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(!empty($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['order_id']), fn($q) => $q->where('order_id', $filters['order_id']))
            ->when(!empty($filters['search']), function($q) use ($filters) {
                $q->where(function($subQuery) use ($filters) {
                    $subQuery->where('supplier_order_id', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('tracking_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('order.user', function($userQuery) use ($filters) {
                            $userQuery->where('name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when(!empty($filters['date_from']), fn($q) => $q->where('created_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']), fn($q) => $q->where('created_at', '<=', $filters['date_to']))
            ->when(isset($filters['overdue']) && $filters['overdue'], fn($q) => $q->overdue())
            ->when(isset($filters['needs_retry']) && $filters['needs_retry'], fn($q) => $q->needsRetry())
            ->latest();

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getOrderStatistics(): array
    {
        $totalOrders = DropshipOrder::count();
        $pendingOrders = DropshipOrder::pending()->count();
        $activeOrders = DropshipOrder::active()->count();
        $completedOrders = DropshipOrder::completed()->count();
        $overdueOrders = DropshipOrder::overdue()->count();

        $statusBreakdown = DropshipOrder::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $supplierBreakdown = DropshipOrder::with('supplier:id,name')
            ->selectRaw('supplier_id, count(*) as count')
            ->groupBy('supplier_id')
            ->get()
            ->map(function($item) {
                return [
                    'supplier_name' => $item->supplier->name ?? 'Unknown',
                    'count' => $item->count
                ];
            });

        $recentActivity = DropshipOrder::with(['order.user', 'supplier'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'supplier_name' => $order->supplier->name,
                    'customer_name' => $order->order->user->name ?? 'Guest',
                    'status' => $order->status,
                    'total_cost' => $order->getTotalCostFormatted(),
                    'created_at' => $order->created_at,
                ];
            });

        return [
            'totals' => [
                'all_orders' => $totalOrders,
                'pending' => $pendingOrders,
                'active' => $activeOrders,
                'completed' => $completedOrders,
                'overdue' => $overdueOrders,
            ],
            'by_status' => $statusBreakdown,
            'by_supplier' => $supplierBreakdown,
            'recent_activity' => $recentActivity,
        ];
    }

    public function handleSupplierResponse(DropshipOrder $dropshipOrder, array $response): void
    {
        try {
            $this->processSupplierResponse($dropshipOrder, $response);
            $this->updateOrderFromDropshipStatus($dropshipOrder);
        } catch (Exception $e) {
            Log::error('Failed to handle supplier response', [
                'dropship_order_id' => $dropshipOrder->id,
                'response' => $response,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getSupplierOrderPayload(DropshipOrder $dropshipOrder): array
    {
        $orderData = $this->getSupplierOrderData($dropshipOrder);

        // Add any additional formatting needed for specific supplier integrations
        $supplier = $dropshipOrder->supplier;
        $integration = $supplier->getActiveIntegration();

        if ($integration && $integration->isApiIntegration()) {
            // Format for API integration
            $orderData['integration'] = [
                'type' => 'api',
                'endpoint' => $integration->getApiEndpoint(),
                'format' => 'json'
            ];
        } elseif ($integration && $integration->isEmailIntegration()) {
            // Format for email integration
            $orderData['integration'] = [
                'type' => 'email',
                'email' => $integration->getEmailAddress(),
                'format' => 'formatted_email'
            ];
        }

        return $orderData;
    }

    public function processOverdueOrdersDaily(): array
    {
        $processedCount = $this->processOverdueOrders();

        // Additional overdue processing logic
        $overdueOrders = DropshipOrder::overdue()->with('supplier')->get();
        $supplierAlerts = [];

        foreach ($overdueOrders->groupBy('supplier_id') as $supplierId => $orders) {
            $supplier = $orders->first()->supplier;
            $overdueCount = $orders->count();

            if ($overdueCount >= 5) { // Alert threshold
                $supplierAlerts[] = [
                    'supplier' => $supplier,
                    'overdue_count' => $overdueCount,
                    'orders' => $orders->pluck('id')->toArray()
                ];
            }
        }

        return [
            'processed_count' => $processedCount,
            'total_overdue' => $overdueOrders->count(),
            'supplier_alerts' => $supplierAlerts
        ];
    }

    public function validateDropshipOrderData(array $data): array
    {
        $errors = [];

        // Validate order exists and can be dropshipped
        $order = Order::find($data['order_id']);
        if (!$order) {
            $errors[] = 'Order not found';
        } elseif ($order->hasActiveShipment()) {
            $errors[] = 'Order already has active shipment';
        }

        // Validate supplier
        $supplier = Supplier::find($data['supplier_id']);
        if (!$supplier) {
            $errors[] = 'Supplier not found';
        } elseif (!$supplier->isActive()) {
            $errors[] = 'Supplier is not active';
        }

        // Validate financial calculations
        $calculatedProfit = ($data['total_retail'] - $data['total_cost']) * 100;
        if ($calculatedProfit < 0) {
            $errors[] = 'Negative profit margin detected';
        }

        // Validate items
        if (empty($data['items'])) {
            $errors[] = 'No items provided';
        } else {
            foreach ($data['items'] as $index => $item) {
                if (($item['retail_price'] - $item['supplier_price']) < 0) {
                    $errors[] = "Item {$index}: Negative profit margin";
                }
            }
        }

        return $errors;
    }

    public function generateSupplierPerformanceReport(int $supplierId, int $days = 30): array
    {
        $supplier = Supplier::findOrFail($supplierId);

        $orders = $supplier->dropshipOrders()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $totalOrders = $orders->count();
        $successfulOrders = $orders->whereIn('status', [DropshipStatuses::DELIVERED])->count();
        $failedOrders = $orders->whereIn('status', [
            DropshipStatuses::REJECTED_BY_SUPPLIER,
            DropshipStatuses::CANCELLED
        ])->count();

        $avgFulfillmentTime = $orders
            ->whereNotNull('shipped_by_supplier_at')
            ->whereNotNull('sent_to_supplier_at')
            ->avg(function ($order) {
                return $order->sent_to_supplier_at->diffInHours($order->shipped_by_supplier_at);
            });

        $profitabilityData = $this->calculateSupplierProfitability($supplier, $days);

        return [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'integration_type' => $supplier->integration_type,
            ],
            'period' => [
                'days' => $days,
                'from' => now()->subDays($days)->format('Y-m-d'),
                'to' => now()->format('Y-m-d'),
            ],
            'performance' => [
                'total_orders' => $totalOrders,
                'successful_orders' => $successfulOrders,
                'failed_orders' => $failedOrders,
                'success_rate' => $totalOrders > 0 ? round(($successfulOrders / $totalOrders) * 100, 2) : 0,
                'avg_fulfillment_hours' => round($avgFulfillmentTime ?? 0, 1),
            ],
            'profitability' => $profitabilityData,
            'issues' => $this->getSupplierIssues($supplier, $days),
        ];
    }

    protected function calculateSupplierProfitability(Supplier $supplier, int $days): array
    {
        $orders = $supplier->dropshipOrders()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $totalCost = $orders->sum('total_cost');
        $totalRetail = $orders->sum('total_retail');
        $totalProfit = $orders->sum('profit_margin');

        return [
            'total_cost' => $totalCost,
            'total_cost_formatted' => '£' . number_format($totalCost / 100, 2),
            'total_retail' => $totalRetail,
            'total_retail_formatted' => '£' . number_format($totalRetail / 100, 2),
            'total_profit' => $totalProfit,
            'total_profit_formatted' => '£' . number_format($totalProfit / 100, 2),
            'profit_margin_percentage' => $totalRetail > 0 ? round(($totalProfit / $totalRetail) * 100, 2) : 0,
        ];
    }

    protected function getSupplierIssues(Supplier $supplier, int $days): array
    {
        $issues = [];

        // Check for high failure rate
        $orders = $supplier->dropshipOrders()
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $failureRate = $orders->count() > 0
            ? ($orders->whereIn('status', [DropshipStatuses::REJECTED_BY_SUPPLIER, DropshipStatuses::CANCELLED])->count() / $orders->count()) * 100
            : 0;

        if ($failureRate > 10) {
            $issues[] = [
                'type' => 'high_failure_rate',
                'description' => "Failure rate of {$failureRate}% exceeds 10% threshold",
                'severity' => $failureRate > 25 ? 'high' : 'medium'
            ];
        }

        // Check for slow fulfillment
        $avgFulfillmentTime = $orders
            ->whereNotNull('shipped_by_supplier_at')
            ->whereNotNull('sent_to_supplier_at')
            ->avg(function ($order) {
                return $order->sent_to_supplier_at->diffInHours($order->shipped_by_supplier_at);
            });

        if ($avgFulfillmentTime && $avgFulfillmentTime > 120) { // More than 5 days
            $issues[] = [
                'type' => 'slow_fulfillment',
                'description' => "Average fulfillment time of " . round($avgFulfillmentTime / 24, 1) . " days exceeds 5 day threshold",
                'severity' => $avgFulfillmentTime > 240 ? 'high' : 'medium'
            ];
        }

        // Check for integration issues
        $integration = $supplier->getActiveIntegration();
        if ($integration && !$integration->isHealthy()) {
            $issues[] = [
                'type' => 'integration_issues',
                'description' => "Integration health score: " . $integration->getHealthScore(),
                'severity' => $integration->getHealthScore() < 50 ? 'high' : 'medium'
            ];
        }

        return $issues;
    }
}

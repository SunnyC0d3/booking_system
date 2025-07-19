<?php

namespace App\Http\Controllers\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropshipOrder;
use App\Models\Order;
use App\Models\Supplier;
use App\Requests\V1\IndexDropshipOrderRequest;
use App\Requests\V1\StoreDropshipOrderRequest;
use App\Requests\V1\UpdateDropshipOrderRequest;
use App\Resources\V1\DropshipOrderResource;
use App\Traits\V1\ApiResponses;
use App\Constants\DropshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class DropshipOrderController extends Controller
{
    use ApiResponses;

    public function index(IndexDropshipOrderRequest $request)
    {
        try {
            $data = $request->validated();

            $dropshipOrders = DropshipOrder::query()
                ->with(['order.user', 'supplier', 'dropshipOrderItems.supplierProduct'])
                ->when(!empty($data['supplier_id']), fn($query) => $query->where('supplier_id', $data['supplier_id']))
                ->when(!empty($data['status']), fn($query) => $query->where('status', $data['status']))
                ->when(!empty($data['order_id']), fn($query) => $query->where('order_id', $data['order_id']))
                ->when(!empty($data['search']), function($query) use ($data) {
                    $query->where(function($q) use ($data) {
                        $q->where('supplier_order_id', 'like', '%' . $data['search'] . '%')
                            ->orWhere('tracking_number', 'like', '%' . $data['search'] . '%')
                            ->orWhereHas('order.user', function($userQuery) use ($data) {
                                $userQuery->where('name', 'like', '%' . $data['search'] . '%')
                                    ->orWhere('email', 'like', '%' . $data['search'] . '%');
                            });
                    });
                })
                ->when(!empty($data['date_from']), fn($query) => $query->where('created_at', '>=', $data['date_from']))
                ->when(!empty($data['date_to']), fn($query) => $query->where('created_at', '<=', $data['date_to']))
                ->when(isset($data['overdue']), function($query) use ($data) {
                    if ($data['overdue']) {
                        $query->overdue();
                    }
                })
                ->when(isset($data['needs_retry']), function($query) use ($data) {
                    if ($data['needs_retry']) {
                        $query->needsRetry();
                    }
                })
                ->latest()
                ->paginate($data['per_page'] ?? 15);

            return DropshipOrderResource::collection($dropshipOrders)->additional([
                'message' => 'Dropship orders retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve dropship orders', [
                'error' => $e->getMessage(),
                'filters' => $data ?? []
            ]);
            return $this->error('Failed to retrieve dropship orders.', 500);
        }
    }

    public function store(StoreDropshipOrderRequest $request)
    {
        try {
            $data = $request->validated();

            $dropshipOrder = DB::transaction(function () use ($data) {
                $order = Order::findOrFail($data['order_id']);
                $supplier = Supplier::findOrFail($data['supplier_id']);

                if (!$supplier->isActive()) {
                    throw new Exception('Supplier is not active.');
                }

                $dropshipOrder = DropshipOrder::create([
                    'order_id' => $order->id,
                    'supplier_id' => $supplier->id,
                    'status' => DropshipStatuses::PENDING,
                    'total_cost' => $data['total_cost'],
                    'total_retail' => $data['total_retail'],
                    'profit_margin' => $data['total_retail'] - $data['total_cost'],
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
                        'supplier_price' => $itemData['supplier_price'],
                        'retail_price' => $itemData['retail_price'],
                        'profit_per_item' => $itemData['retail_price'] - $itemData['supplier_price'],
                        'product_details' => $itemData['product_details'] ?? null,
                        'status' => DropshipStatuses::PENDING,
                    ]);
                }

                Log::info('Dropship order created', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'order_id' => $order->id,
                    'supplier_id' => $supplier->id,
                    'total_cost' => $dropshipOrder->getTotalCostFormatted(),
                    'items_count' => count($data['items'])
                ]);

                return $dropshipOrder;
            });

            return $this->ok(
                'Dropship order created successfully.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to create dropship order', [
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to create dropship order: ' . $e->getMessage(), 500);
        }
    }

    public function show(Request $request, DropshipOrder $dropshipOrder)
    {
        try {
            $dropshipOrder->load([
                'order.user',
                'supplier',
                'dropshipOrderItems.supplierProduct',
                'dropshipOrderItems.orderItem.product'
            ]);

            return $this->ok(
                'Dropship order retrieved successfully.',
                new DropshipOrderResource($dropshipOrder)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve dropship order', [
                'dropship_order_id' => $dropshipOrder->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve dropship order.', 500);
        }
    }

    public function update(UpdateDropshipOrderRequest $request, DropshipOrder $dropshipOrder)
    {
        try {
            $data = $request->validated();

            $updatedOrder = DB::transaction(function () use ($dropshipOrder, $data) {
                $originalStatus = $dropshipOrder->status;

                $dropshipOrder->update($data);

                if (isset($data['status']) && $originalStatus !== $data['status']) {
                    Log::info('Dropship order status changed', [
                        'dropship_order_id' => $dropshipOrder->id,
                        'old_status' => $originalStatus,
                        'new_status' => $data['status']
                    ]);
                }

                return $dropshipOrder;
            });

            return $this->ok(
                'Dropship order updated successfully.',
                new DropshipOrderResource($updatedOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to update dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'data' => $data ?? []
            ]);
            return $this->error('Failed to update dropship order.', 500);
        }
    }

    public function destroy(Request $request, DropshipOrder $dropshipOrder)
    {
        try {
            if (!in_array($dropshipOrder->status, [DropshipStatuses::PENDING, DropshipStatuses::CANCELLED])) {
                return $this->error('Cannot delete dropship order that has been sent to supplier.', 400);
            }

            DB::transaction(function () use ($dropshipOrder) {
                $dropshipOrder->dropshipOrderItems()->delete();
                $dropshipOrder->delete();

                Log::info('Dropship order deleted', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'order_id' => $dropshipOrder->order_id,
                    'supplier_id' => $dropshipOrder->supplier_id
                ]);
            });

            return $this->ok('Dropship order deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to delete dropship order.', 500);
        }
    }

    public function sendToSupplier(Request $request, DropshipOrder $dropshipOrder)
    {
        try {
            if (!$dropshipOrder->isPending()) {
                return $this->error('Dropship order has already been sent to supplier.', 400);
            }

            $supplier = $dropshipOrder->supplier;
            if (!$supplier->isActive()) {
                return $this->error('Supplier is not active.', 400);
            }

            DB::transaction(function () use ($dropshipOrder, $supplier) {
                $dropshipOrder->markAsSentToSupplier([
                    'sent_at' => now(),
                    'integration_type' => $supplier->integration_type,
                ]);

                Log::info('Dropship order sent to supplier', [
                    'dropship_order_id' => $dropshipOrder->id,
                    'supplier_id' => $supplier->id,
                    'integration_type' => $supplier->integration_type
                ]);
            });

            return $this->ok(
                'Dropship order sent to supplier successfully.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to send dropship order to supplier', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to send dropship order to supplier.', 500);
        }
    }

    public function markAsConfirmed(Request $request, DropshipOrder $dropshipOrder)
    {
        $request->validate([
            'supplier_order_id' => 'required|string|max:255',
            'estimated_delivery' => 'nullable|date|after:today',
            'supplier_response' => 'nullable|array'
        ]);

        try {
            $data = $request->all();

            $dropshipOrder->markAsConfirmed(
                $data['supplier_order_id'],
                $data['supplier_response'] ?? []
            );

            if (isset($data['estimated_delivery'])) {
                $dropshipOrder->update(['estimated_delivery' => $data['estimated_delivery']]);
            }

            Log::info('Dropship order confirmed by supplier', [
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_order_id' => $data['supplier_order_id']
            ]);

            return $this->ok(
                'Dropship order marked as confirmed.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to mark dropship order as confirmed', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to mark as confirmed.', 500);
        }
    }

    public function markAsShipped(Request $request, DropshipOrder $dropshipOrder)
    {
        $request->validate([
            'tracking_number' => 'required|string|max:255',
            'carrier' => 'nullable|string|max:255',
            'estimated_delivery' => 'nullable|date|after:today'
        ]);

        try {
            $data = $request->all();

            $dropshipOrder->markAsShipped(
                $data['tracking_number'],
                $data['carrier'] ?? null,
                isset($data['estimated_delivery']) ? \Carbon\Carbon::parse($data['estimated_delivery']) : null
            );

            Log::info('Dropship order marked as shipped', [
                'dropship_order_id' => $dropshipOrder->id,
                'tracking_number' => $data['tracking_number'],
                'carrier' => $data['carrier'] ?? 'Unknown'
            ]);

            return $this->ok(
                'Dropship order marked as shipped.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to mark dropship order as shipped', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to mark as shipped.', 500);
        }
    }

    public function markAsDelivered(Request $request, DropshipOrder $dropshipOrder)
    {
        try {
            $dropshipOrder->markAsDelivered();

            Log::info('Dropship order marked as delivered', [
                'dropship_order_id' => $dropshipOrder->id
            ]);

            return $this->ok(
                'Dropship order marked as delivered.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to mark dropship order as delivered', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to mark as delivered.', 500);
        }
    }

    public function cancel(Request $request, DropshipOrder $dropshipOrder)
    {
        $request->validate([
            'reason' => 'nullable|string|max:1000'
        ]);

        try {
            $reason = $request->input('reason');

            if ($dropshipOrder->isDelivered()) {
                return $this->error('Cannot cancel delivered dropship order.', 400);
            }

            $dropshipOrder->markAsCancelled($reason);

            Log::info('Dropship order cancelled', [
                'dropship_order_id' => $dropshipOrder->id,
                'reason' => $reason
            ]);

            return $this->ok(
                'Dropship order cancelled successfully.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to cancel dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to cancel dropship order.', 500);
        }
    }

    public function retry(Request $request, DropshipOrder $dropshipOrder)
    {
        try {
            if (!$dropshipOrder->canRetry()) {
                return $this->error('Dropship order cannot be retried.', 400);
            }

            $dropshipOrder->incrementRetryCount();
            $dropshipOrder->updateStatus(DropshipStatuses::PENDING);

            Log::info('Dropship order retry initiated', [
                'dropship_order_id' => $dropshipOrder->id,
                'retry_count' => $dropshipOrder->retry_count
            ]);

            return $this->ok(
                'Dropship order retry initiated.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to retry dropship order', [
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retry dropship order.', 500);
        }
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'dropship_order_ids' => 'required|array|min:1',
            'dropship_order_ids.*' => 'exists:dropship_orders,id',
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(DropshipStatuses::all())],
            'notes' => 'nullable|string|max:1000'
        ]);

        try {
            $orderIds = $request->input('dropship_order_ids');
            $status = $request->input('status');
            $notes = $request->input('notes');

            $updated = DB::transaction(function () use ($orderIds, $status, $notes) {
                $orders = DropshipOrder::whereIn('id', $orderIds)->get();

                foreach ($orders as $order) {
                    $order->updateStatus($status, ['notes' => $notes]);
                }

                return $orders->count();
            });

            Log::info('Bulk dropship order status update completed', [
                'orders_updated' => $updated,
                'new_status' => $status
            ]);

            return $this->ok("Successfully updated status for {$updated} dropship orders.", [
                'updated_count' => $updated,
                'new_status' => $status
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update dropship order status', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to bulk update status.', 500);
        }
    }

    public function getStats(Request $request)
    {
        try {
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

            return $this->ok('Dropship order stats retrieved successfully.', $stats);
        } catch (Exception $e) {
            Log::error('Failed to get dropship order stats', [
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve stats.', 500);
        }
    }
}

<?php

namespace App\Http\Controllers\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\DropshipOrder;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\ProductSupplierMapping;
use App\Models\Product;
use App\Resources\V1\DropshipOrderResource;
use App\Resources\V1\SupplierResource;
use App\Resources\V1\SupplierProductResource;
use App\Resources\V1\ProductSupplierMappingResource;
use App\Traits\V1\ApiResponses;
use App\Constants\DropshipStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class VendorDropshippingController extends Controller
{
    use ApiResponses;

    public function getDashboard(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropshipping_analytics')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $dashboard = [
                'overview' => $this->getDashboardOverview($vendorProductIds),
                'recent_orders' => $this->getRecentDropshipOrders($vendorProductIds, 5),
                'top_suppliers' => $this->getTopSuppliers($vendorProductIds),
                'profit_summary' => $this->getProfitSummary($vendorProductIds),
                'alerts' => $this->getDropshippingAlerts($vendorProductIds),
                'performance_metrics' => $this->getPerformanceMetrics($vendorProductIds),
            ];

            return $this->ok('Dropshipping dashboard retrieved successfully.', $dashboard);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor dropshipping dashboard', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve dashboard.', 500);
        }
    }

    public function getDropshipOrders(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropship_orders')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $dropshipOrders = DropshipOrder::query()
                ->with(['order.user', 'supplier', 'dropshipOrderItems.supplierProduct'])
                ->whereHas('order.orderItems', function($query) use ($vendorProductIds) {
                    $query->whereIn('product_id', $vendorProductIds);
                })
                ->when($request->status, fn($query) => $query->where('status', $request->status))
                ->when($request->supplier_id, fn($query) => $query->where('supplier_id', $request->supplier_id))
                ->when($request->search, function($query) use ($request) {
                    $query->where(function($q) use ($request) {
                        $q->where('supplier_order_id', 'like', '%' . $request->search . '%')
                            ->orWhere('tracking_number', 'like', '%' . $request->search . '%');
                    });
                })
                ->latest()
                ->paginate($request->per_page ?? 15);

            return DropshipOrderResource::collection($dropshipOrders)->additional([
                'message' => 'Dropship orders retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor dropship orders', [
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve dropship orders.', 500);
        }
    }

    public function getDropshipOrder(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropship_orders')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $hasVendorProducts = $dropshipOrder->order->orderItems()
                ->whereIn('product_id', $vendorProductIds)
                ->exists();

            if (!$hasVendorProducts) {
                return $this->error('Dropship order not found or access denied.', 404);
            }

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
            Log::error('Failed to retrieve vendor dropship order', [
                'dropship_order_id' => $dropshipOrder->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve dropship order.', 500);
        }
    }

    public function getSuppliers(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $suppliers = Supplier::query()
                ->whereHas('productMappings', function($query) use ($vendorProductIds) {
                    $query->whereIn('product_id', $vendorProductIds);
                })
                ->withCount(['supplierProducts', 'dropshipOrders'])
                ->when($request->status, fn($query) => $query->where('status', $request->status))
                ->when($request->search, function($query) use ($request) {
                    $query->where(function($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%')
                            ->orWhere('company_name', 'like', '%' . $request->search . '%');
                    });
                })
                ->latest()
                ->paginate($request->per_page ?? 15);

            return SupplierResource::collection($suppliers)->additional([
                'message' => 'Suppliers retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor suppliers', [
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve suppliers.', 500);
        }
    }

    public function getSupplier(Request $request, Supplier $supplier)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_suppliers')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $hasVendorProducts = $supplier->productMappings()
                ->whereIn('product_id', $vendorProductIds)
                ->exists();

            if (!$hasVendorProducts) {
                return $this->error('Supplier not found or access denied.', 404);
            }

            $supplier->load([
                'supplierProducts' => function($query) use ($vendorProductIds) {
                    $query->whereHas('product', function($q) use ($vendorProductIds) {
                        $q->whereIn('id', $vendorProductIds);
                    })->with(['product'])->latest();
                },
                'dropshipOrders' => function($query) use ($vendorProductIds) {
                    $query->whereHas('order.orderItems', function($q) use ($vendorProductIds) {
                        $q->whereIn('product_id', $vendorProductIds);
                    })->with(['order.user'])->latest()->limit(10);
                }
            ]);

            return $this->ok(
                'Supplier retrieved successfully.',
                new SupplierResource($supplier)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier', [
                'supplier_id' => $supplier->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier.', 500);
        }
    }

    public function getSupplierProducts(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $supplierProducts = SupplierProduct::query()
                ->with(['supplier', 'product'])
                ->whereHas('product', function($query) use ($vendorProductIds) {
                    $query->whereIn('id', $vendorProductIds);
                })
                ->when($request->supplier_id, fn($query) => $query->where('supplier_id', $request->supplier_id))
                ->when($request->sync_status, fn($query) => $query->where('sync_status', $request->sync_status))
                ->when(isset($request->is_active), fn($query) => $query->where('is_active', $request->is_active))
                ->when($request->search, function($query) use ($request) {
                    $query->where(function($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%')
                            ->orWhere('supplier_sku', 'like', '%' . $request->search . '%');
                    });
                })
                ->latest()
                ->paginate($request->per_page ?? 15);

            return SupplierProductResource::collection($supplierProducts)->additional([
                'message' => 'Supplier products retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier products', [
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier products.', 500);
        }
    }

    public function getSupplierProduct(Request $request, SupplierProduct $supplierProduct)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_products')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            if (!$supplierProduct->product || $supplierProduct->product->vendor_id !== $vendor->id) {
                return $this->error('Supplier product not found or access denied.', 404);
            }

            $supplierProduct->load([
                'supplier',
                'product.vendor',
                'productMapping',
                'dropshipOrderItems' => function($query) {
                    $query->with(['dropshipOrder.order'])->latest()->limit(10);
                }
            ]);

            return $this->ok(
                'Supplier product retrieved successfully.',
                new SupplierProductResource($supplierProduct)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier product', [
                'supplier_product_id' => $supplierProduct->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier product.', 500);
        }
    }

    public function getProductMappings(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropshipping_analytics')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $mappings = ProductSupplierMapping::query()
                ->with(['product.vendor', 'supplier', 'supplierProduct'])
                ->whereIn('product_id', $vendorProductIds)
                ->when($request->supplier_id, fn($query) => $query->where('supplier_id', $request->supplier_id))
                ->when(isset($request->is_primary), fn($query) => $query->where('is_primary', $request->is_primary))
                ->when(isset($request->is_active), fn($query) => $query->where('is_active', $request->is_active))
                ->when($request->search, function($query) use ($request) {
                    $query->whereHas('product', function($q) use ($request) {
                        $q->where('name', 'like', '%' . $request->search . '%');
                    });
                })
                ->orderBy('is_primary', 'desc')
                ->orderBy('priority_order')
                ->latest()
                ->paginate($request->per_page ?? 15);

            return ProductSupplierMappingResource::collection($mappings)->additional([
                'message' => 'Product supplier mappings retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor product mappings', [
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve product mappings.', 500);
        }
    }

    public function getProductMapping(Request $request, ProductSupplierMapping $productSupplierMapping)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropshipping_analytics')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            if ($productSupplierMapping->product->vendor_id !== $vendor->id) {
                return $this->error('Product mapping not found or access denied.', 404);
            }

            $productSupplierMapping->load([
                'product.vendor',
                'supplier',
                'supplierProduct'
            ]);

            return $this->ok(
                'Product supplier mapping retrieved successfully.',
                new ProductSupplierMappingResource($productSupplierMapping)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor product mapping', [
                'mapping_id' => $productSupplierMapping->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve product mapping.', 500);
        }
    }

    public function getAnalytics(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropshipping_analytics')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $analytics = [
                'overview' => $this->getDashboardOverview($vendorProductIds),
                'order_trends' => $this->getOrderTrends($vendorProductIds),
                'supplier_performance' => $this->getSupplierPerformanceAnalytics($vendorProductIds),
                'profit_analysis' => $this->getProfitAnalysis($vendorProductIds),
                'product_performance' => $this->getProductPerformance($vendorProductIds),
                'fulfillment_metrics' => $this->getFulfillmentMetrics($vendorProductIds),
            ];

            return $this->ok('Analytics retrieved successfully.', $analytics);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor dropshipping analytics', [
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve analytics.', 500);
        }
    }

    public function getProfitMargins(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_profit_margins')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $profitMargins = $this->getProfitMarginsData($vendorProductIds);

            return $this->ok('Profit margins retrieved successfully.', $profitMargins);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor profit margins', [
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve profit margins.', 500);
        }
    }

    public function getSupplierPerformance(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_supplier_performance')) {
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $vendor = $user->vendor;
            if (!$vendor) {
                return $this->error('Vendor profile not found.', 404);
            }

            $vendorProductIds = Product::where('vendor_id', $vendor->id)->pluck('id');

            $performance = $this->getSupplierPerformanceData($vendorProductIds);

            return $this->ok('Supplier performance retrieved successfully.', $performance);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier performance', [
                'vendor_id' => $vendor->id ?? null,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier performance.', 500);
        }
    }

    protected function getDashboardOverview($vendorProductIds): array
    {
        $totalOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->count();

        $pendingOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->pending()->count();

        $activeOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->active()->count();

        $completedOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->completed()->count();

        $totalRevenue = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->sum('total_retail');

        $totalProfit = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->sum('profit_margin');

        return [
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'active_orders' => $activeOrders,
            'completed_orders' => $completedOrders,
            'total_revenue' => $totalRevenue,
            'total_revenue_formatted' => '£' . number_format($totalRevenue / 100, 2),
            'total_profit' => $totalProfit,
            'total_profit_formatted' => '£' . number_format($totalProfit / 100, 2),
            'average_profit_margin' => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0,
        ];
    }

    protected function getRecentDropshipOrders($vendorProductIds, int $limit = 5): array
    {
        return DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })
            ->with(['order.user', 'supplier'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_id' => $order->order_id,
                    'supplier_name' => $order->supplier->name,
                    'customer_name' => $order->order->user->name ?? 'Guest',
                    'status' => $order->status,
                    'status_label' => $order->getStatusLabel(),
                    'total_cost' => $order->getTotalCostFormatted(),
                    'total_retail' => $order->getTotalRetailFormatted(),
                    'profit_margin' => $order->getProfitMarginFormatted(),
                    'created_at' => $order->created_at,
                ];
            })->toArray();
    }

    protected function getTopSuppliers($vendorProductIds): array
    {
        return Supplier::whereHas('productMappings', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })
            ->withCount(['dropshipOrders' => function($query) use ($vendorProductIds) {
                $query->whereHas('order.orderItems', function($q) use ($vendorProductIds) {
                    $q->whereIn('product_id', $vendorProductIds);
                });
            }])
            ->orderBy('dropship_orders_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'orders_count' => $supplier->dropship_orders_count,
                    'status' => $supplier->status,
                    'integration_type' => $supplier->integration_type,
                ];
            })->toArray();
    }

    protected function getProfitSummary($vendorProductIds): array
    {
        $thisMonth = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('SUM(profit_margin) as profit, SUM(total_retail) as revenue')
            ->first();

        $lastMonth = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })
            ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->selectRaw('SUM(profit_margin) as profit, SUM(total_retail) as revenue')
            ->first();

        $thisMonthProfit = $thisMonth->profit ?? 0;
        $lastMonthProfit = $lastMonth->profit ?? 0;
        $profitGrowth = $lastMonthProfit > 0 ? (($thisMonthProfit - $lastMonthProfit) / $lastMonthProfit) * 100 : 0;

        return [
            'this_month_profit' => $thisMonthProfit,
            'this_month_profit_formatted' => '£' . number_format($thisMonthProfit / 100, 2),
            'last_month_profit' => $lastMonthProfit,
            'last_month_profit_formatted' => '£' . number_format($lastMonthProfit / 100, 2),
            'profit_growth_percentage' => round($profitGrowth, 2),
            'this_month_revenue' => $thisMonth->revenue ?? 0,
            'this_month_revenue_formatted' => '£' . number_format(($thisMonth->revenue ?? 0) / 100, 2),
        ];
    }

    protected function getDropshippingAlerts($vendorProductIds): array
    {
        $alerts = [];

        $overdueOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->overdue()->count();

        if ($overdueOrders > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => "{$overdueOrders} dropship orders are overdue",
                'action_url' => '/vendor/dropshipping/orders?overdue=1'
            ];
        }

        $failedOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->whereIn('status', [DropshipStatuses::REJECTED_BY_SUPPLIER, DropshipStatuses::CANCELLED])->count();

        if ($failedOrders > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "{$failedOrders} dropship orders have failed",
                'action_url' => '/vendor/dropshipping/orders?status=failed'
            ];
        }

        return $alerts;
    }

    protected function getPerformanceMetrics($vendorProductIds): array
    {
        $totalOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->count();

        $successfulOrders = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })->whereIn('status', [DropshipStatuses::DELIVERED])->count();

        $averageFulfillmentTime = DropshipOrder::whereHas('order.orderItems', function($query) use ($vendorProductIds) {
            $query->whereIn('product_id', $vendorProductIds);
        })
            ->whereNotNull('shipped_by_supplier_at')
            ->whereNotNull('sent_to_supplier_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, sent_to_supplier_at, shipped_by_supplier_at)) as avg_hours')
            ->value('avg_hours');

        return [
            'success_rate' => $totalOrders > 0 ? round(($successfulOrders / $totalOrders) * 100, 2) : 0,
            'average_fulfillment_time_hours' => $averageFulfillmentTime ? round($averageFulfillmentTime, 1) : null,
            'total_orders_processed' => $totalOrders,
            'successful_deliveries' => $successfulOrders,
        ];
    }

    protected function getOrderTrends($vendorProductIds): array
    {
        return [];
    }

    protected function getSupplierPerformanceAnalytics($vendorProductIds): array
    {
        return [];
    }

    protected function getProfitAnalysis($vendorProductIds): array
    {
        return [];
    }

    protected function getProductPerformance($vendorProductIds): array
    {
        return [];
    }

    protected function getFulfillmentMetrics($vendorProductIds): array
    {
        return [];
    }

    protected function getProfitMarginsData($vendorProductIds): array
    {
        return [];
    }

    protected function getSupplierPerformanceData($vendorProductIds): array
    {
        return [];
    }
}

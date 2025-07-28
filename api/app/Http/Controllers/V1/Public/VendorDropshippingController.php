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
use Illuminate\Support\Facades\Log;
use Exception;

class VendorDropshippingController extends Controller
{
    protected function getOrderTrends($vendorProductIds): array
    {
        $thirtyDaysAgo = now()->subDays(30);
        $sevenDaysAgo = now()->subDays(7);

        // Daily orders for last 30 days
        $dailyOrders = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_cost) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'orders' => $item->count,
                    'revenue' => $item->revenue / 100, // Convert to pounds
                ];
            });

        // Weekly orders for last 12 weeks
        $weeklyOrders = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', now()->subDays(84))
            ->selectRaw('YEAR(created_at) as year, WEEK(created_at) as week, COUNT(*) as count, SUM(total_cost) as revenue')
            ->groupBy('year', 'week')
            ->orderBy('year')
            ->orderBy('week')
            ->get()
            ->map(function($item) {
                return [
                    'week' => "W{$item->week} {$item->year}",
                    'orders' => $item->count,
                    'revenue' => $item->revenue / 100,
                ];
            });

        // Monthly orders for last 12 months
        $monthlyOrders = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(total_cost) as revenue')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function($item) {
                return [
                    'month' => date('M Y', mktime(0, 0, 0, $item->month, 1, $item->year)),
                    'orders' => $item->count,
                    'revenue' => $item->revenue / 100,
                ];
            });

        // Calculate growth rate
        $currentWeekOrders = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', $sevenDaysAgo)
            ->count();

        $previousWeekOrders = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->whereBetween('created_at', [now()->subDays(14), $sevenDaysAgo])
            ->count();

        $growthRate = $previousWeekOrders > 0
            ? round((($currentWeekOrders - $previousWeekOrders) / $previousWeekOrders) * 100, 2)
            : 0;

        return [
            'daily_orders' => $dailyOrders,
            'weekly_orders' => $weeklyOrders,
            'monthly_orders' => $monthlyOrders,
            'growth_rate' => $growthRate,
            'seasonal_patterns' => $this->calculateSeasonalPatterns($vendorProductIds)
        ];
    }

    protected function getSupplierPerformanceAnalytics($vendorProductIds): array
    {
        $suppliers = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->with('supplier')
            ->selectRaw('supplier_id, COUNT(*) as total_orders,
                         AVG(CASE WHEN status IN ("delivered", "shipped_by_supplier") THEN 1 ELSE 0 END) * 100 as success_rate,
                         AVG(TIMESTAMPDIFF(HOUR, sent_to_supplier_at, shipped_by_supplier_at)) as avg_processing_time')
            ->groupBy('supplier_id')
            ->get();

        return [
            'top_suppliers' => $suppliers->sortByDesc('success_rate')->take(5)->values(),
            'fulfillment_rates' => $suppliers->pluck('success_rate', 'supplier.name'),
            'average_processing_times' => $suppliers->pluck('avg_processing_time', 'supplier.name'),
            'reliability_scores' => $suppliers->map(function($supplier) {
                return [
                    'name' => $supplier->supplier->name,
                    'score' => $this->calculateReliabilityScore($supplier)
                ];
            })
        ];
    }

    protected function getProfitAnalysis($vendorProductIds): array
    {
        $profitByProduct = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->with('product')
            ->selectRaw('product_id, SUM(profit_margin) as total_profit, COUNT(*) as orders, AVG(profit_margin) as avg_margin')
            ->groupBy('product_id')
            ->get()
            ->map(function($item) {
                return [
                    'product_name' => $item->product->name,
                    'total_profit' => $item->total_profit / 100,
                    'orders' => $item->orders,
                    'avg_margin' => round($item->avg_margin / 100, 2)
                ];
            });

        $profitBySupplier = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->with('supplier')
            ->selectRaw('supplier_id, SUM(profit_margin) as total_profit, COUNT(*) as orders')
            ->groupBy('supplier_id')
            ->get();

        return [
            'profit_by_product' => $profitByProduct,
            'profit_by_supplier' => $profitBySupplier,
            'margin_trends' => $this->calculateMarginTrends($vendorProductIds),
            'cost_breakdown' => $this->getCostBreakdown($vendorProductIds)
        ];
    }

    protected function getProductPerformance($vendorProductIds): array
    {
        $thirtyDaysAgo = now()->subDays(30);

        $bestSelling = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->with('product')
            ->selectRaw('product_id, COUNT(*) as orders, SUM(total_cost) as revenue')
            ->groupBy('product_id')
            ->orderByDesc('orders')
            ->take(10)
            ->get();

        $mostProfitable = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->with('product')
            ->selectRaw('product_id, SUM(profit_margin) as profit')
            ->groupBy('product_id')
            ->orderByDesc('profit')
            ->take(10)
            ->get();

        $lowPerforming = DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->with('product')
            ->selectRaw('product_id, COUNT(*) as orders')
            ->groupBy('product_id')
            ->having('orders', '<', 5)
            ->orderBy('orders')
            ->get();

        return [
            'best_selling_products' => $bestSelling,
            'most_profitable_products' => $mostProfitable,
            'low_performing_products' => $lowPerforming,
            'inventory_turnover' => $this->calculateInventoryTurnover($vendorProductIds)
        ];
    }

    // Helper methods
    private function calculateSeasonalPatterns($vendorProductIds): array
    {
        // Implementation for seasonal pattern analysis
        return DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as orders')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function($item) {
                return [
                    'month' => date('F', mktime(0, 0, 0, $item->month, 1)),
                    'orders' => $item->orders
                ];
            });
    }

    private function calculateReliabilityScore($supplier): int
    {
        // Calculate based on success rate, processing time, and error rate
        $successRate = $supplier->success_rate;
        $processingTime = $supplier->avg_processing_time ?? 48; // Default 48 hours

        $score = ($successRate * 0.6) + (max(0, 100 - ($processingTime / 2)) * 0.4);
        return min(100, max(0, round($score)));
    }

    private function calculateMarginTrends($vendorProductIds): array
    {
        return DropshipOrder::whereIn('product_id', $vendorProductIds)
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('DATE(created_at) as date, AVG(profit_margin) as avg_margin')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'margin' => round($item->avg_margin / 100, 2)
                ];
            });
    }

    private function getCostBreakdown($vendorProductIds): array
    {
        $totalOrders = DropshipOrder::whereIn('product_id', $vendorProductIds)->count();
        $totalCost = DropshipOrder::whereIn('product_id', $vendorProductIds)->sum('supplier_cost');
        $totalRevenue = DropshipOrder::whereIn('product_id', $vendorProductIds)->sum('total_cost');
        $totalProfit = $totalRevenue - $totalCost;

        return [
            'total_revenue' => $totalRevenue / 100,
            'total_cost' => $totalCost / 100,
            'total_profit' => $totalProfit / 100,
            'profit_margin_percentage' => $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0,
            'average_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders / 100, 2) : 0
        ];
    }

    private function calculateInventoryTurnover($vendorProductIds): array
    {
        // Calculate inventory turnover for dropshipped products
        return Product::whereIn('id', $vendorProductIds)
            ->with(['orderItems' => function($query) {
                $query->where('created_at', '>=', now()->subDays(90));
            }])
            ->get()
            ->map(function($product) {
                $soldQuantity = $product->orderItems->sum('quantity');
                $avgInventory = $product->quantity ?: 1; // Avoid division by zero

                return [
                    'product_name' => $product->name,
                    'turnover_rate' => round($soldQuantity / $avgInventory, 2),
                    'days_of_inventory' => $soldQuantory > 0 ? round((90 * $avgInventory) / $soldQuantity, 1) : 0
                ];
            });
    }
}

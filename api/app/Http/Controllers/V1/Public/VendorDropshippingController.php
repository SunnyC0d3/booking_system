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
    use ApiResponses;

    /**
     * Get comprehensive order trends and analytics
     *
     * Analyzes dropshipping order patterns including daily, weekly, and monthly trends.
     * Calculates growth rates and identifies seasonal patterns to help vendors optimize
     * their dropshipping strategy and forecast demand.
     *
     * @group Vendor Dropshipping Analytics
     * @authenticated
     *
     * @queryParam period string optional Analysis period (30days, 90days, 1year). Example: 30days
     * @queryParam chart_type string optional Chart data type (daily, weekly, monthly). Example: daily
     * @queryParam include_growth boolean optional Include growth rate calculations. Example: true
     * @queryParam seasonal_analysis boolean optional Include seasonal pattern analysis. Example: true
     *
     * @response 200 scenario="Order trends retrieved successfully" {
     *   "data": {
     *     "daily_orders": [
     *       {
     *         "date": "2024-01-20",
     *         "orders": 15,
     *         "revenue": 1247.50
     *       },
     *       {
     *         "date": "2024-01-21",
     *         "orders": 18,
     *         "revenue": 1456.25
     *       }
     *     ],
     *     "weekly_orders": [
     *       {
     *         "week": "W3 2024",
     *         "orders": 89,
     *         "revenue": 7234.75
     *       }
     *     ],
     *     "monthly_orders": [
     *       {
     *         "month": "Jan 2024",
     *         "orders": 342,
     *         "revenue": 28456.80
     *       }
     *     ],
     *     "growth_rate": 12.5,
     *     "seasonal_patterns": [
     *       {
     *         "month": "January",
     *         "orders": 342
     *       }
     *     ]
     *   },
     *   "message": "Order trends retrieved successfully.",
     *   "status": 200
     * }
     */
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

    /**
     * Get supplier performance analytics and reliability metrics
     *
     * Provides comprehensive analysis of supplier performance including success rates,
     * processing times, and reliability scores. Helps vendors identify top-performing
     * suppliers and optimize their supplier relationships for better fulfillment rates.
     *
     * @group Vendor Dropshipping Analytics
     * @authenticated
     *
     * @queryParam supplier_id integer optional Filter by specific supplier. Example: 15
     * @queryParam time_period string optional Analysis time period (30days, 90days, 1year). Example: 90days
     * @queryParam include_reliability boolean optional Include reliability score calculations. Example: true
     * @queryParam min_orders integer optional Minimum orders threshold for inclusion. Example: 5
     *
     * @response 200 scenario="Supplier performance analytics retrieved" {
     *   "data": {
     *     "top_suppliers": [
     *       {
     *         "supplier_id": 15,
     *         "total_orders": 124,
     *         "success_rate": 97.5,
     *         "avg_processing_time": 18.5
     *       }
     *     ],
     *     "fulfillment_rates": {
     *       "Global Electronics Ltd": 97.5,
     *       "TechSource Distribution": 94.2,
     *       "Gadget Wholesale Co": 89.8
     *     },
     *     "average_processing_times": {
     *       "Global Electronics Ltd": 18.5,
     *       "TechSource Distribution": 24.3,
     *       "Gadget Wholesale Co": 32.1
     *     },
     *     "reliability_scores": [
     *       {
     *         "name": "Global Electronics Ltd",
     *         "score": 92
     *       }
     *     ]
     *   },
     *   "message": "Supplier performance analytics retrieved successfully.",
     *   "status": 200
     * }
     */
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

    /**
     * Get comprehensive profit analysis and margin trends
     *
     * Analyzes profit margins across products and suppliers, providing insights into
     * profitability trends and cost breakdowns. Essential for optimizing pricing
     * strategies and identifying the most profitable products and suppliers.
     *
     * @group Vendor Dropshipping Analytics
     * @authenticated
     *
     * @queryParam group_by string optional Group profits by (product, supplier, category). Example: product
     * @queryParam time_period string optional Analysis period (30days, 90days, 1year). Example: 90days
     * @queryParam include_trends boolean optional Include profit margin trends over time. Example: true
     * @queryParam min_margin numeric optional Minimum margin threshold for filtering. Example: 10.00
     *
     * @response 200 scenario="Profit analysis retrieved successfully" {
     *   "data": {
     *     "profit_by_product": [
     *       {
     *         "product_name": "Wireless Gaming Headset Pro",
     *         "total_profit": 1247.85,
     *         "orders": 34,
     *         "avg_margin": 36.70
     *       }
     *     ],
     *     "profit_by_supplier": [
     *       {
     *         "supplier_id": 15,
     *         "total_profit": 3456.92,
     *         "orders": 89
     *       }
     *     ],
     *     "margin_trends": [
     *       {
     *         "date": "2024-01-20",
     *         "margin": 34.25
     *       }
     *     ],
     *     "cost_breakdown": {
     *       "total_revenue": 15678.45,
     *       "total_cost": 9876.23,
     *       "total_profit": 5802.22,
     *       "profit_margin_percentage": 37.0,
     *       "average_order_value": 89.45
     *     }
     *   },
     *   "message": "Profit analysis retrieved successfully.",
     *   "status": 200
     * }
     */
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

    /**
     * Get product performance metrics and sales analytics
     *
     * Analyzes individual product performance including best-selling items,
     * most profitable products, and low-performing inventory. Provides insights
     * for inventory optimization and product portfolio management.
     *
     * @group Vendor Dropshipping Analytics
     * @authenticated
     *
     * @queryParam sort_by string optional Sort products by (orders, revenue, profit). Example: orders
     * @queryParam time_period string optional Analysis period (7days, 30days, 90days). Example: 30days
     * @queryParam limit integer optional Number of top products to return. Example: 10
     * @queryParam include_low_performing boolean optional Include low-performing products analysis. Example: true
     *
     * @response 200 scenario="Product performance metrics retrieved" {
     *   "data": {
     *     "best_selling_products": [
     *       {
     *         "product_id": 127,
     *         "orders": 45,
     *         "revenue": 2345.67
     *       }
     *     ],
     *     "most_profitable_products": [
     *       {
     *         "product_id": 134,
     *         "profit": 1456.89
     *       }
     *     ],
     *     "low_performing_products": [
     *       {
     *         "product_id": 98,
     *         "orders": 3
     *       }
     *     ],
     *     "inventory_turnover": [
     *       {
     *         "product_name": "Bluetooth Speaker Ultra",
     *         "turnover_rate": 4.2,
     *         "days_of_inventory": 21.4
     *       }
     *     ]
     *   },
     *   "message": "Product performance metrics retrieved successfully.",
     *   "status": 200
     * }
     */
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
                    'days_of_inventory' => $soldQuantity > 0 ? round((90 * $avgInventory) / $soldQuantity, 1) : 0
                ];
            });
    }
}

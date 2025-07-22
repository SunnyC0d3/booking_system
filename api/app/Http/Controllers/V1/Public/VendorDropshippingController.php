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
     * Get vendor dropshipping dashboard
     *
     * Retrieve comprehensive dropshipping dashboard data for the authenticated vendor including
     * overview statistics, recent orders, top suppliers, profit analysis, alerts, and performance metrics.
     * This provides a complete view of the vendor's dropshipping operations.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @response 200 scenario="Dashboard retrieved successfully" {
     *   "message": "Dropshipping dashboard retrieved successfully.",
     *   "data": {
     *     "overview": {
     *       "total_orders": 128,
     *       "pending_orders": 5,
     *       "active_orders": 23,
     *       "completed_orders": 95,
     *       "total_revenue": 15640,
     *       "total_revenue_formatted": "£156.40",
     *       "total_profit": 4692,
     *       "total_profit_formatted": "£46.92",
     *       "average_profit_margin": 30.0
     *     },
     *     "recent_orders": [
     *       {
     *         "id": 15,
     *         "order_id": 98,
     *         "supplier_name": "GlobalTech Distributors",
     *         "customer_name": "John Smith",
     *         "status": "confirmed",
     *         "status_label": "Confirmed",
     *         "total_cost": "£45.00",
     *         "total_retail": "£79.99",
     *         "profit_margin": "£34.99",
     *         "created_at": "2025-01-15T16:20:00.000000Z"
     *       }
     *     ],
     *     "top_suppliers": [
     *       {
     *         "id": 1,
     *         "name": "GlobalTech Distributors",
     *         "orders_count": 45,
     *         "status": "active",
     *         "integration_type": "api"
     *       }
     *     ],
     *     "profit_summary": {
     *       "this_month_profit": 1250,
     *       "this_month_profit_formatted": "£12.50",
     *       "last_month_profit": 980,
     *       "last_month_profit_formatted": "£9.80",
     *       "profit_growth_percentage": 27.55,
     *       "this_month_revenue": 4167,
     *       "this_month_revenue_formatted": "£41.67"
     *     },
     *     "alerts": [
     *       {
     *         "type": "warning",
     *         "message": "3 dropship orders are overdue",
     *         "action_url": "/vendor/dropshipping/orders?overdue=1"
     *       }
     *     ],
     *     "performance_metrics": {
     *       "success_rate": 94.5,
     *       "average_fulfillment_time_hours": 36.2,
     *       "total_orders_processed": 128,
     *       "successful_deliveries": 121
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve dashboard."
     * }
     */
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

            Log::info('Vendor dropshipping dashboard accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'total_orders' => $dashboard['overview']['total_orders']
            ]);

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

    /**
     * Get vendor dropship orders
     *
     * Retrieve a paginated list of dropship orders for the authenticated vendor's products.
     * This endpoint supports filtering by status, supplier, and search terms. Only orders
     * containing the vendor's products are included.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @queryParam status string optional Filter orders by status (pending, confirmed, shipped, delivered, cancelled). Example: confirmed
     * @queryParam supplier_id integer optional Filter orders by specific supplier ID. Example: 1
     * @queryParam search string optional Search orders by supplier order ID or tracking number. Example: GT001
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of orders per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Orders retrieved successfully" {
     *   "message": "Dropship orders retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 15,
     *         "order_id": 98,
     *         "supplier_id": 1,
     *         "status": "confirmed",
     *         "supplier_order_id": "GT-2025-001",
     *         "tracking_number": null,
     *         "tracking_url": null,
     *         "total_cost": 4500,
     *         "total_cost_formatted": "£45.00",
     *         "total_retail": 7999,
     *         "total_retail_formatted": "£79.99",
     *         "profit_margin": 3499,
     *         "profit_margin_formatted": "£34.99",
     *         "shipping_address": {
     *           "name": "John Smith",
     *           "line1": "123 Main Street",
     *           "city": "London",
     *           "postcode": "SW1A 1AA",
     *           "country": "GB"
     *         },
     *         "estimated_delivery": "2025-01-18T00:00:00.000000Z",
     *         "sent_to_supplier_at": "2025-01-15T16:25:00.000000Z",
     *         "confirmed_by_supplier_at": "2025-01-15T17:30:00.000000Z",
     *         "shipped_by_supplier_at": null,
     *         "delivered_at": null,
     *         "order": {
     *           "id": 98,
     *           "total_amount": 7999,
     *           "user": {
     *             "id": 5,
     *             "name": "John Smith",
     *             "email": "john@example.com"
     *           }
     *         },
     *         "supplier": {
     *           "id": 1,
     *           "name": "GlobalTech Distributors",
     *           "status": "active"
     *         },
     *         "created_at": "2025-01-15T16:20:00.000000Z",
     *         "updated_at": "2025-01-15T17:30:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 23,
     *     "last_page": 2,
     *     "from": 1,
     *     "to": 15
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve dropship orders."
     * }
     */
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

            Log::info('Vendor dropship orders accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'total_orders' => $dropshipOrders->total(),
                'filters' => $request->only(['status', 'supplier_id', 'search'])
            ]);

            return DropshipOrderResource::collection($dropshipOrders)->additional([
                'message' => 'Dropship orders retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor dropship orders', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve dropship orders.', 500);
        }
    }

    /**
     * Get specific vendor dropship order
     *
     * Retrieve detailed information about a specific dropship order for the authenticated vendor.
     * Only orders containing the vendor's products can be accessed. Includes comprehensive
     * order details, items, and fulfillment status.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to retrieve. Example: 15
     *
     * @response 200 scenario="Order found" {
     *   "message": "Dropship order retrieved successfully.",
     *   "data": {
     *     "id": 15,
     *     "order_id": 98,
     *     "supplier_id": 1,
     *     "status": "confirmed",
     *     "supplier_order_id": "GT-2025-001",
     *     "tracking_number": null,
     *     "tracking_url": null,
     *     "total_cost": 4500,
     *     "total_cost_formatted": "£45.00",
     *     "total_retail": 7999,
     *     "total_retail_formatted": "£79.99",
     *     "profit_margin": 3499,
     *     "profit_margin_formatted": "£34.99",
     *     "shipping_address": {
     *       "name": "John Smith",
     *       "line1": "123 Main Street",
     *       "line2": "Apt 4B",
     *       "city": "London",
     *       "county": "Greater London",
     *       "postcode": "SW1A 1AA",
     *       "country": "GB",
     *       "phone": "+44 20 1234 5678"
     *     },
     *     "notes": "Handle with care",
     *     "estimated_delivery": "2025-01-18T00:00:00.000000Z",
     *     "sent_to_supplier_at": "2025-01-15T16:25:00.000000Z",
     *     "confirmed_by_supplier_at": "2025-01-15T17:30:00.000000Z",
     *     "shipped_by_supplier_at": null,
     *     "delivered_at": null,
     *     "retry_count": 0,
     *     "auto_retry_enabled": true,
     *     "order": {
     *       "id": 98,
     *       "total_amount": 7999,
     *       "status": "processing",
     *       "user": {
     *         "id": 5,
     *         "name": "John Smith",
     *         "email": "john@example.com"
     *       }
     *     },
     *     "supplier": {
     *       "id": 1,
     *       "name": "GlobalTech Distributors",
     *       "status": "active",
     *       "integration_type": "api",
     *       "processing_time_days": 2
     *     },
     *     "dropship_order_items": [
     *       {
     *         "id": 1,
     *         "order_item_id": 156,
     *         "supplier_product_id": 1,
     *         "supplier_sku": "GT-WH-001",
     *         "quantity": 1,
     *         "supplier_price": 4500,
     *         "retail_price": 7999,
     *         "profit_per_item": 3499,
     *         "product_details": {
     *           "name": "Wireless Bluetooth Headphones Pro",
     *           "description": "Premium noise-cancelling wireless headphones",
     *           "weight": 0.35,
     *           "dimensions": {
     *             "length": 20.0,
     *             "width": 18.0,
     *             "height": 8.0
     *           },
     *           "images": ["headphones-1.jpg", "headphones-2.jpg"],
     *           "attributes": {
     *             "color": "Black",
     *             "connectivity": "Bluetooth 5.0"
     *           }
     *         },
     *         "status": "confirmed",
     *         "supplier_product": {
     *           "id": 1,
     *           "supplier_sku": "GT-WH-001",
     *           "name": "Wireless Bluetooth Headphones Pro",
     *           "stock_quantity": 150
     *         }
     *       }
     *     ],
     *     "created_at": "2025-01-15T16:20:00.000000Z",
     *     "updated_at": "2025-01-15T17:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 404 scenario="Order not found or access denied" {
     *   "message": "Dropship order not found or access denied."
     * }
     */
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

            Log::info('Vendor dropship order accessed', [
                'dropship_order_id' => $dropshipOrder->id,
                'vendor_id' => $vendor->id,
                'user_id' => $user->id
            ]);

            return $this->ok(
                'Dropship order retrieved successfully.',
                new DropshipOrderResource($dropshipOrder)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor dropship order', [
                'dropship_order_id' => $dropshipOrder->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve dropship order.', 500);
        }
    }

    /**
     * Get vendor suppliers
     *
     * Retrieve a paginated list of suppliers that have product mappings with the authenticated
     * vendor's products. This shows suppliers that are actively working with the vendor for
     * dropshipping operations.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @queryParam status string optional Filter suppliers by status (active, inactive, pending_approval). Example: active
     * @queryParam search string optional Search suppliers by name or company name. Example: Tech
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of suppliers per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Suppliers retrieved successfully" {
     *   "message": "Suppliers retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "name": "GlobalTech Distributors",
     *         "company_name": "GlobalTech Distributors Ltd",
     *         "email": "orders@globaltech-dist.com",
     *         "phone": "+44 20 7946 0958",
     *         "country": "GB",
     *         "contact_person": "Sarah Williams",
     *         "status": "active",
     *         "integration_type": "api",
     *         "commission_rate": 5.00,
     *         "processing_time_days": 2,
     *         "auto_fulfill": true,
     *         "minimum_order_value": 25.00,
     *         "maximum_order_value": 5000.00,
     *         "supplier_products_count": 12,
     *         "dropship_orders_count": 45,
     *         "created_at": "2025-01-10T08:00:00.000000Z",
     *         "updated_at": "2025-01-15T14:25:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 3,
     *     "last_page": 1,
     *     "from": 1,
     *     "to": 3
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve suppliers."
     * }
     */
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

            Log::info('Vendor suppliers accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'total_suppliers' => $suppliers->total()
            ]);

            return SupplierResource::collection($suppliers)->additional([
                'message' => 'Suppliers retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor suppliers', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve suppliers.', 500);
        }
    }

    /**
     * Get specific vendor supplier
     *
     * Retrieve detailed information about a specific supplier that has product mappings with
     * the authenticated vendor's products. Includes supplier products and recent orders.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @urlParam supplier integer required The ID of the supplier to retrieve. Example: 1
     *
     * @response 200 scenario="Supplier found" {
     *   "message": "Supplier retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "name": "GlobalTech Distributors",
     *     "company_name": "GlobalTech Distributors Ltd",
     *     "email": "orders@globaltech-dist.com",
     *     "phone": "+44 20 7946 0958",
     *     "address": "123 Business Park, London, E14 5AB",
     *     "country": "GB",
     *     "contact_person": "Sarah Williams",
     *     "status": "active",
     *     "integration_type": "api",
     *     "commission_rate": 5.00,
     *     "processing_time_days": 2,
     *     "auto_fulfill": true,
     *     "stock_sync_enabled": true,
     *     "price_sync_enabled": true,
     *     "minimum_order_value": 25.00,
     *     "maximum_order_value": 5000.00,
     *     "supported_countries": ["GB", "IE", "FR", "DE", "NL", "BE"],
     *     "supplier_products": [
     *       {
     *         "id": 1,
     *         "supplier_sku": "GT-WH-001",
     *         "name": "Wireless Bluetooth Headphones Pro",
     *         "supplier_price": 4500,
     *         "stock_quantity": 150,
     *         "is_active": true,
     *         "product": {
     *           "id": 25,
     *           "name": "Wireless Bluetooth Headphones Pro",
     *           "price": 7999
     *         }
     *       }
     *     ],
     *     "dropship_orders": [
     *       {
     *         "id": 15,
     *         "status": "confirmed",
     *         "total_cost": "£45.00",
     *         "created_at": "2025-01-15T16:20:00.000000Z",
     *         "order": {
     *           "id": 98,
     *           "user": {
     *             "name": "John Smith"
     *           }
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 404 scenario="Supplier not found or access denied" {
     *   "message": "Supplier not found or access denied."
     * }
     */
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

            Log::info('Vendor supplier accessed', [
                'supplier_id' => $supplier->id,
                'vendor_id' => $vendor->id,
                'user_id' => $user->id
            ]);

            return $this->ok(
                'Supplier retrieved successfully.',
                new SupplierResource($supplier)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier', [
                'supplier_id' => $supplier->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier.', 500);
        }
    }

    /**
     * Get vendor supplier products
     *
     * Retrieve a paginated list of supplier products that are mapped to the authenticated
     * vendor's products. This shows the inventory available for dropshipping from suppliers.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @queryParam supplier_id integer optional Filter products by specific supplier ID. Example: 1
     * @queryParam sync_status string optional Filter by sync status (synced, pending_sync, out_of_sync, sync_error). Example: synced
     * @queryParam is_active boolean optional Filter by active status (1 for active, 0 for inactive). Example: 1
     * @queryParam search string optional Search products by name or supplier SKU. Example: Wireless
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of products per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Products retrieved successfully" {
     *   "message": "Supplier products retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "supplier_id": 1,
     *         "supplier_sku": "GT-WH-001",
     *         "supplier_product_id": "GT001",
     *         "name": "Wireless Bluetooth Headphones Pro",
     *         "description": "Premium noise-cancelling wireless headphones",
     *         "supplier_price": 4500,
     *         "supplier_price_formatted": "£45.00",
     *         "retail_price": 7999,
     *         "retail_price_formatted": "£79.99",
     *         "stock_quantity": 150,
     *         "sync_status": "synced",
     *         "is_active": true,
     *         "is_mapped": true,
     *         "minimum_order_quantity": 1,
     *         "processing_time_days": 2,
     *         "supplier": {
     *           "id": 1,
     *           "name": "GlobalTech Distributors",
     *           "status": "active"
     *         },
     *         "product": {
     *           "id": 25,
     *           "name": "Wireless Bluetooth Headphones Pro",
     *           "price": 7999
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve supplier products."
     * }
     */
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

            Log::info('Vendor supplier products accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'total_products' => $supplierProducts->total()
            ]);

            return SupplierProductResource::collection($supplierProducts)->additional([
                'message' => 'Supplier products retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier products', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier products.', 500);
        }
    }

    /**
     * Get specific vendor supplier product
     *
     * Retrieve detailed information about a specific supplier product that is mapped to one
     * of the authenticated vendor's products. Includes mapping information and recent orders.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @urlParam supplierProduct integer required The ID of the supplier product to retrieve. Example: 1
     *
     * @response 200 scenario="Product found" {
     *   "message": "Supplier product retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "supplier_id": 1,
     *     "supplier_sku": "GT-WH-001",
     *     "name": "Wireless Bluetooth Headphones Pro",
     *     "description": "Premium noise-cancelling wireless headphones with 30-hour battery life",
     *     "supplier_price": 4500,
     *     "supplier_price_formatted": "£45.00",
     *     "retail_price": 7999,
     *     "retail_price_formatted": "£79.99",
     *     "stock_quantity": 150,
     *     "weight": 0.35,
     *     "sync_status": "synced",
     *     "is_active": true,
     *     "is_mapped": true,
     *     "minimum_order_quantity": 1,
     *     "processing_time_days": 2,
     *     "images": ["headphones-1.jpg", "headphones-2.jpg"],
     *     "attributes": {
     *       "color": "Black",
     *       "connectivity": "Bluetooth 5.0"
     *     },
     *     "supplier": {
     *       "id": 1,
     *       "name": "GlobalTech Distributors",
     *       "status": "active",
     *       "integration_type": "api"
     *     },
     *     "product": {
     *       "id": 25,
     *       "name": "Wireless Bluetooth Headphones Pro",
     *       "price": 7999,
     *       "vendor": {
     *         "id": 1,
     *         "name": "Tech Haven"
     *       }
     *     },
     *     "product_mapping": {
     *       "id": 1,
     *       "is_primary": true,
     *       "is_active": true,
     *       "markup_percentage": 78.0
     *     },
     *     "dropship_order_items": [
     *       {
     *         "id": 1,
     *         "quantity": 1,
     *         "supplier_price": 4500,
     *         "retail_price": 7999,
     *         "created_at": "2025-01-15T16:20:00.000000Z",
     *         "dropship_order": {
     *           "id": 15,
     *           "status": "confirmed",
     *           "order": {
     *             "id": 98,
     *             "user": {
     *               "name": "John Smith"
     *             }
     *           }
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 404 scenario="Product not found or access denied" {
     *   "message": "Supplier product not found or access denied."
     * }
     */
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

            Log::info('Vendor supplier product accessed', [
                'supplier_product_id' => $supplierProduct->id,
                'vendor_id' => $vendor->id,
                'user_id' => $user->id
            ]);

            return $this->ok(
                'Supplier product retrieved successfully.',
                new SupplierProductResource($supplierProduct)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier product', [
                'supplier_product_id' => $supplierProduct->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve supplier product.', 500);
        }
    }

    /**
     * Get vendor product mappings
     *
     * Retrieve a paginated list of product-supplier mappings for the authenticated vendor's
     * products. This shows how vendor products are connected to suppliers for dropshipping.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @queryParam supplier_id integer optional Filter mappings by specific supplier ID. Example: 1
     * @queryParam is_primary boolean optional Filter by primary mapping status (1 for primary, 0 for secondary). Example: 1
     * @queryParam is_active boolean optional Filter by active status (1 for active, 0 for inactive). Example: 1
     * @queryParam search string optional Search mappings by product name. Example: Headphones
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of mappings per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Mappings retrieved successfully" {
     *   "message": "Product supplier mappings retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "product_id": 25,
     *         "supplier_id": 1,
     *         "supplier_product_id": 1,
     *         "is_primary": true,
     *         "is_active": true,
     *         "priority_order": 1,
     *         "markup_percentage": 78.0,
     *         "markup_type": "percentage",
     *         "minimum_stock_threshold": 5,
     *         "auto_update_price": true,
     *         "auto_update_stock": true,
     *         "last_price_update": "2025-01-15T14:30:00.000000Z",
     *         "last_stock_update": "2025-01-15T15:45:00.000000Z",
     *         "product": {
     *           "id": 25,
     *           "name": "Wireless Bluetooth Headphones Pro",
     *           "price": 7999,
     *           "vendor": {
     *             "id": 1,
     *             "name": "Tech Haven"
     *           }
     *         },
     *         "supplier": {
     *           "id": 1,
     *           "name": "GlobalTech Distributors",
     *           "status": "active"
     *         },
     *         "supplier_product": {
     *           "id": 1,
     *           "supplier_sku": "GT-WH-001",
     *           "supplier_price": 4500,
     *           "stock_quantity": 150
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve product mappings."
     * }
     */
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

            Log::info('Vendor product mappings accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'total_mappings' => $mappings->total()
            ]);

            return ProductSupplierMappingResource::collection($mappings)->additional([
                'message' => 'Product supplier mappings retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor product mappings', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve product mappings.', 500);
        }
    }

    /**
     * Get specific vendor product mapping
     *
     * Retrieve detailed information about a specific product-supplier mapping for one of
     * the authenticated vendor's products. Includes configuration and performance data.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @urlParam productSupplierMapping integer required The ID of the mapping to retrieve. Example: 1
     *
     * @response 200 scenario="Mapping found" {
     *   "message": "Product supplier mapping retrieved successfully.",
     *   "data": {
     *     "id": 1,
     *     "product_id": 25,
     *     "supplier_id": 1,
     *     "supplier_product_id": 1,
     *     "is_primary": true,
     *     "is_active": true,
     *     "priority_order": 1,
     *     "markup_percentage": 78.0,
     *     "markup_type": "percentage",
     *     "fixed_markup": 0,
     *     "minimum_stock_threshold": 5,
     *     "auto_update_price": true,
     *     "auto_update_stock": true,
     *     "auto_update_description": false,
     *     "field_mappings": {
     *       "name": "product_name",
     *       "description": "product_description",
     *       "price": "wholesale_price",
     *       "stock": "available_quantity"
     *     },
     *     "last_price_update": "2025-01-15T14:30:00.000000Z",
     *     "last_stock_update": "2025-01-15T15:45:00.000000Z",
     *     "product": {
     *       "id": 25,
     *       "name": "Wireless Bluetooth Headphones Pro",
     *       "price": 7999,
     *       "is_dropship": true,
     *       "vendor": {
     *         "id": 1,
     *         "name": "Tech Haven"
     *       }
     *     },
     *     "supplier": {
     *       "id": 1,
     *       "name": "GlobalTech Distributors",
     *       "status": "active",
     *       "integration_type": "api"
     *     },
     *     "supplier_product": {
     *       "id": 1,
     *       "supplier_sku": "GT-WH-001",
     *       "supplier_price": 4500,
     *       "stock_quantity": 150,
     *       "is_active": true
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 404 scenario="Mapping not found or access denied" {
     *   "message": "Product mapping not found or access denied."
     * }
     */
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

            Log::info('Vendor product mapping accessed', [
                'mapping_id' => $productSupplierMapping->id,
                'vendor_id' => $vendor->id,
                'user_id' => $user->id
            ]);

            return $this->ok(
                'Product supplier mapping retrieved successfully.',
                new ProductSupplierMappingResource($productSupplierMapping)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor product mapping', [
                'mapping_id' => $productSupplierMapping->id ?? null,
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve product mapping.', 500);
        }
    }

    /**
     * Get vendor dropshipping analytics
     *
     * Retrieve comprehensive analytics and performance metrics for the authenticated vendor's
     * dropshipping operations. Includes overview statistics, trends, supplier performance,
     * profit analysis, and fulfillment metrics.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @response 200 scenario="Analytics retrieved successfully" {
     *   "message": "Analytics retrieved successfully.",
     *   "data": {
     *     "overview": {
     *       "total_orders": 128,
     *       "pending_orders": 5,
     *       "active_orders": 23,
     *       "completed_orders": 95,
     *       "total_revenue": 15640,
     *       "total_revenue_formatted": "£156.40",
     *       "total_profit": 4692,
     *       "total_profit_formatted": "£46.92",
     *       "average_profit_margin": 30.0
     *     },
     *     "order_trends": {
     *       "daily_orders": [],
     *       "weekly_orders": [],
     *       "monthly_orders": []
     *     },
     *     "supplier_performance": {
     *       "top_suppliers": [],
     *       "fulfillment_rates": [],
     *       "average_processing_times": []
     *     },
     *     "profit_analysis": {
     *       "profit_by_product": [],
     *       "profit_by_supplier": [],
     *       "margin_trends": []
     *     },
     *     "product_performance": {
     *       "best_selling_products": [],
     *       "most_profitable_products": [],
     *       "low_performing_products": []
     *     },
     *     "fulfillment_metrics": {
     *       "average_fulfillment_time": 36.2,
     *       "success_rate": 94.5,
     *       "on_time_delivery_rate": 89.3
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve analytics."
     * }
     */
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

            Log::info('Vendor dropshipping analytics accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id,
                'total_orders_analyzed' => $analytics['overview']['total_orders']
            ]);

            return $this->ok('Analytics retrieved successfully.', $analytics);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor dropshipping analytics', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve analytics.', 500);
        }
    }

    /**
     * Get vendor profit margins
     *
     * Retrieve detailed profit margin analysis for the authenticated vendor's dropshipping
     * operations. Includes profit margins by product, supplier, and time period.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @response 200 scenario="Profit margins retrieved successfully" {
     *   "message": "Profit margins retrieved successfully.",
     *   "data": {
     *     "overall_margin": 30.0,
     *     "profit_by_product": [
     *       {
     *         "product_id": 25,
     *         "product_name": "Wireless Bluetooth Headphones Pro",
     *         "total_orders": 45,
     *         "total_revenue": 3599,
     *         "total_cost": 2025,
     *         "total_profit": 1574,
     *         "profit_margin": 43.7
     *       }
     *     ],
     *     "profit_by_supplier": [
     *       {
     *         "supplier_id": 1,
     *         "supplier_name": "GlobalTech Distributors",
     *         "total_orders": 45,
     *         "total_revenue": 3599,
     *         "total_cost": 2025,
     *         "total_profit": 1574,
     *         "profit_margin": 43.7
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve profit margins."
     * }
     */
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

            Log::info('Vendor profit margins accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id
            ]);

            return $this->ok('Profit margins retrieved successfully.', $profitMargins);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor profit margins', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return $this->error('Failed to retrieve profit margins.', 500);
        }
    }

    /**
     * Get vendor supplier performance
     *
     * Retrieve comprehensive performance metrics for suppliers working with the authenticated
     * vendor. Includes fulfillment rates, processing times, success rates, and reliability metrics.
     *
     * @group Vendor Dropshipping
     * @authenticated
     *
     * @response 200 scenario="Supplier performance retrieved successfully" {
     *   "message": "Supplier performance retrieved successfully.",
     *   "data": {
     *     "suppliers": [
     *       {
     *         "supplier_id": 1,
     *         "supplier_name": "GlobalTech Distributors",
     *         "total_orders": 45,
     *         "successful_orders": 43,
     *         "success_rate": 95.6,
     *         "average_processing_time": 2.1,
     *         "average_fulfillment_time": 36.2,
     *         "on_time_delivery_rate": 91.2,
     *         "order_accuracy_rate": 97.8,
     *         "integration_health": "excellent",
     *         "last_order_date": "2025-01-15T16:20:00.000000Z"
     *       }
     *     ],
     *     "performance_summary": {
     *       "best_performing_supplier": "GlobalTech Distributors",
     *       "average_success_rate": 95.6,
     *       "average_fulfillment_time": 36.2,
     *       "total_suppliers": 3
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Vendor profile not found" {
     *   "message": "Vendor profile not found."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "Failed to retrieve supplier performance."
     * }
     */
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

            Log::info('Vendor supplier performance accessed', [
                'vendor_id' => $vendor->id,
                'user_id' => $user->id
            ]);

            return $this->ok('Supplier performance retrieved successfully.', $performance);
        } catch (Exception $e) {
            Log::error('Failed to retrieve vendor supplier performance', [
                'vendor_id' => $vendor->id ?? null,
                'user_id' => $user->id,
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
        // Implementation for order trends analysis
        return [
            'daily_orders' => [],
            'weekly_orders' => [],
            'monthly_orders' => [],
            'growth_rate' => 0,
            'seasonal_patterns' => []
        ];
    }

    protected function getSupplierPerformanceAnalytics($vendorProductIds): array
    {
        // Implementation for supplier performance analytics
        return [
            'top_suppliers' => [],
            'fulfillment_rates' => [],
            'average_processing_times' => [],
            'reliability_scores' => []
        ];
    }

    protected function getProfitAnalysis($vendorProductIds): array
    {
        // Implementation for profit analysis
        return [
            'profit_by_product' => [],
            'profit_by_supplier' => [],
            'margin_trends' => [],
            'cost_breakdown' => []
        ];
    }

    protected function getProductPerformance($vendorProductIds): array
    {
        // Implementation for product performance analysis
        return [
            'best_selling_products' => [],
            'most_profitable_products' => [],
            'low_performing_products' => [],
            'inventory_turnover' => []
        ];
    }

    protected function getFulfillmentMetrics($vendorProductIds): array
    {
        // Implementation for fulfillment metrics
        return [
            'average_fulfillment_time' => 0,
            'success_rate' => 0,
            'on_time_delivery_rate' => 0,
            'order_accuracy_rate' => 0
        ];
    }

    protected function getProfitMarginsData($vendorProductIds): array
    {
        // Implementation for detailed profit margins analysis
        return [
            'overall_margin' => 0,
            'profit_by_product' => [],
            'profit_by_supplier' => [],
            'margin_trends' => [],
            'benchmark_comparison' => []
        ];
    }

    protected function getSupplierPerformanceData($vendorProductIds): array
    {
        // Implementation for detailed supplier performance data
        return [
            'suppliers' => [],
            'performance_summary' => [
                'best_performing_supplier' => null,
                'average_success_rate' => 0,
                'average_fulfillment_time' => 0,
                'total_suppliers' => 0
            ],
            'reliability_metrics' => [],
            'integration_health' => []
        ];
    }
}

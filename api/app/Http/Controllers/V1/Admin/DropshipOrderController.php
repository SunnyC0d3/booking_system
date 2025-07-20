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

    /**
     * Retrieve paginated list of dropship orders
     *
     * Get a paginated list of all dropship orders in the system with comprehensive filtering options.
     * This endpoint supports filtering by supplier, status, order ID, search terms, date ranges, and
     * special conditions like overdue or retry-needed orders. Essential for dropshipping management.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @queryParam supplier_id integer optional Filter by specific supplier ID. Example: 5
     * @queryParam status string optional Filter by dropship status (pending, confirmed, shipped, delivered, etc.). Example: pending
     * @queryParam order_id integer optional Filter by specific order ID. Example: 123
     * @queryParam search string optional Search in supplier order ID, tracking number, or customer details. Example: AB123456
     * @queryParam date_from string optional Filter orders created from this date (YYYY-MM-DD). Example: 2025-01-01
     * @queryParam date_to string optional Filter orders created until this date (YYYY-MM-DD). Example: 2025-01-31
     * @queryParam overdue boolean optional Show only overdue orders (past estimated delivery). Example: true
     * @queryParam needs_retry boolean optional Show only orders that need retry. Example: true
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of orders per page (max 100). Default: 15. Example: 25
     *
     * @response 200 scenario="Success with dropship orders" {
     *   "message": "Dropship orders retrieved successfully.",
     *   "status": 200,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "order_id": 123,
     *         "supplier_id": 5,
     *         "supplier_order_id": "SUP-2025-001",
     *         "status": "confirmed",
     *         "total_cost": 1599,
     *         "total_cost_formatted": "£15.99",
     *         "total_retail": 2999,
     *         "total_retail_formatted": "£29.99",
     *         "profit_margin": 1400,
     *         "profit_margin_formatted": "£14.00",
     *         "tracking_number": "AB123456789GB",
     *         "estimated_delivery": "2025-01-20T17:00:00.000000Z",
     *         "shipping_address": {
     *           "name": "John Smith",
     *           "address_line_1": "123 Main St",
     *           "city": "London",
     *           "postcode": "SW1A 1AA",
     *           "country": "GB"
     *         },
     *         "auto_retry_enabled": true,
     *         "retry_count": 0,
     *         "notes": "Handle with care",
     *         "order": {
     *           "id": 123,
     *           "total_amount_formatted": "£29.99",
     *           "created_at": "2025-01-15T10:00:00.000000Z",
     *           "user": {
     *             "id": 45,
     *             "name": "John Smith",
     *             "email": "john@example.com"
     *           }
     *         },
     *         "supplier": {
     *           "id": 5,
     *           "name": "Global Suppliers Ltd",
     *           "status": "active",
     *           "integration_type": "api"
     *         },
     *         "dropship_order_items": [
     *           {
     *             "id": 1,
     *             "quantity": 2,
     *             "supplier_price": 799,
     *             "supplier_price_formatted": "£7.99",
     *             "retail_price": 1499,
     *             "retail_price_formatted": "£14.99",
     *             "profit_per_item": 700,
     *             "profit_per_item_formatted": "£7.00",
     *             "supplier_product": {
     *               "id": 10,
     *               "name": "Wireless Mouse",
     *               "supplier_sku": "WM-001"
     *             }
     *           }
     *         ],
     *         "created_at": "2025-01-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-16T14:20:00.000000Z"
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 47,
     *     "last_page": 4
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *   "errors": [
     *     "The supplier id must be an integer.",
     *     "The date from must be a valid date."
     *   ]
     * }
     */
    public function index(IndexDropshipOrderRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropship_orders')) {
            Log::warning('Unauthorized access attempt to view dropship orders', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            Log::info('Retrieving dropship orders', [
                'user_id' => $user->id,
                'filters' => $data,
                'ip_address' => $request->ip()
            ]);

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

            Log::info('Dropship orders retrieved successfully', [
                'user_id' => $user->id,
                'total_orders' => $dropshipOrders->total(),
                'current_page' => $dropshipOrders->currentPage()
            ]);

            return DropshipOrderResource::collection($dropshipOrders)->additional([
                'message' => 'Dropship orders retrieved successfully.',
                'status' => 200
            ]);
        } catch (Exception $e) {
            Log::error('Failed to retrieve dropship orders', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'filters' => $data ?? [],
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to retrieve dropship orders.', 500);
        }
    }

    /**
     * Create a new dropship order
     *
     * Create a new dropship order for an existing order with specified supplier and items.
     * The system validates that the supplier is active and calculates profit margins automatically.
     * This endpoint creates both the dropship order and associated order items.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @bodyParam order_id integer required The ID of the order to create dropship order for. Example: 123
     * @bodyParam supplier_id integer required The ID of the supplier to fulfill this order. Example: 5
     * @bodyParam total_cost numeric required The total cost to pay the supplier in pounds. Example: 15.99
     * @bodyParam total_retail numeric required The total retail price charged to customer in pounds. Example: 29.99
     * @bodyParam shipping_address object required The shipping address for delivery.
     * @bodyParam shipping_address.name string required Customer name. Example: John Smith
     * @bodyParam shipping_address.address_line_1 string required First line of address. Example: 123 Main St
     * @bodyParam shipping_address.city string required City name. Example: London
     * @bodyParam shipping_address.postcode string required Postal code. Example: SW1A 1AA
     * @bodyParam shipping_address.country string required Country code. Example: GB
     * @bodyParam notes string optional Special instructions or notes. Example: Handle with care
     * @bodyParam auto_retry_enabled boolean optional Enable automatic retry on failure. Default: true. Example: true
     * @bodyParam items array required Array of items to be dropshipped.
     * @bodyParam items.*.order_item_id integer required The order item ID. Example: 1
     * @bodyParam items.*.supplier_product_id integer required The supplier product ID. Example: 10
     * @bodyParam items.*.supplier_sku string required The supplier SKU. Example: WM-001
     * @bodyParam items.*.quantity integer required Quantity to order. Example: 2
     * @bodyParam items.*.supplier_price numeric required Price per unit from supplier in pounds. Example: 7.99
     * @bodyParam items.*.retail_price numeric required Retail price per unit in pounds. Example: 14.99
     * @bodyParam items.*.product_details object optional Additional product details.
     *
     * @response 200 scenario="Dropship order created successfully" {
     *   "message": "Dropship order created successfully.",
     *   "data": {
     *     "id": 48,
     *     "order_id": 123,
     *     "supplier_id": 5,
     *     "supplier_order_id": null,
     *     "status": "pending",
     *     "total_cost": 1599,
     *     "total_cost_formatted": "£15.99",
     *     "total_retail": 2999,
     *     "total_retail_formatted": "£29.99",
     *     "profit_margin": 1400,
     *     "profit_margin_formatted": "£14.00",
     *     "tracking_number": null,
     *     "estimated_delivery": null,
     *     "shipping_address": {
     *       "name": "John Smith",
     *       "address_line_1": "123 Main St",
     *       "city": "London",
     *       "postcode": "SW1A 1AA",
     *       "country": "GB"
     *     },
     *     "auto_retry_enabled": true,
     *     "retry_count": 0,
     *     "notes": "Handle with care",
     *     "order": {
     *       "id": 123,
     *       "total_amount_formatted": "£29.99",
     *       "user": {
     *         "id": 45,
     *         "name": "John Smith",
     *         "email": "john@example.com"
     *       }
     *     },
     *     "supplier": {
     *       "id": 5,
     *       "name": "Global Suppliers Ltd",
     *       "status": "active"
     *     },
     *     "dropship_order_items": [
     *       {
     *         "id": 25,
     *         "quantity": 2,
     *         "supplier_price": 799,
     *         "retail_price": 1499,
     *         "profit_per_item": 700,
     *         "status": "pending"
     *       }
     *     ],
     *     "created_at": "2025-01-15T14:30:00.000000Z",
     *     "updated_at": "2025-01-15T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The order id field is required.",
     *     "The supplier id field is required.",
     *     "The items field is required."
     *   ]
     * }
     *
     * @response 400 scenario="Supplier not active" {
     *   "message": "Failed to create dropship order: Supplier is not active."
     * }
     */
    public function store(StoreDropshipOrderRequest $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('create_dropship_orders')) {
            Log::warning('Unauthorized access attempt to create dropship order', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            Log::info('Creating dropship order', [
                'user_id' => $user->id,
                'order_id' => $data['order_id'],
                'supplier_id' => $data['supplier_id'],
                'total_cost' => $data['total_cost'],
                'ip_address' => $request->ip()
            ]);

            $dropshipOrder = DB::transaction(function () use ($data, $user) {
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

                Log::info('Dropship order created successfully', [
                    'user_id' => $user->id,
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
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data ?? [],
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to create dropship order: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Retrieve a specific dropship order
     *
     * Get detailed information about a specific dropship order including order details,
     * supplier information, and all associated order items. This endpoint loads comprehensive
     * relationship data for complete order visibility.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to retrieve. Example: 48
     *
     * @response 200 scenario="Dropship order found" {
     *   "message": "Dropship order retrieved successfully.",
     *   "data": {
     *     "id": 48,
     *     "order_id": 123,
     *     "supplier_id": 5,
     *     "supplier_order_id": "SUP-2025-001",
     *     "status": "confirmed",
     *     "total_cost": 1599,
     *     "total_cost_formatted": "£15.99",
     *     "total_retail": 2999,
     *     "total_retail_formatted": "£29.99",
     *     "profit_margin": 1400,
     *     "profit_margin_formatted": "£14.00",
     *     "tracking_number": "AB123456789GB",
     *     "estimated_delivery": "2025-01-20T17:00:00.000000Z",
     *     "shipping_address": {
     *       "name": "John Smith",
     *       "address_line_1": "123 Main St",
     *       "city": "London",
     *       "postcode": "SW1A 1AA",
     *       "country": "GB"
     *     },
     *     "auto_retry_enabled": true,
     *     "retry_count": 0,
     *     "notes": "Handle with care",
     *     "order": {
     *       "id": 123,
     *       "total_amount_formatted": "£29.99",
     *       "created_at": "2025-01-15T10:00:00.000000Z",
     *       "user": {
     *         "id": 45,
     *         "name": "John Smith",
     *         "email": "john@example.com"
     *       }
     *     },
     *     "supplier": {
     *       "id": 5,
     *       "name": "Global Suppliers Ltd",
     *       "status": "active",
     *       "integration_type": "api"
     *     },
     *     "dropship_order_items": [
     *       {
     *         "id": 25,
     *         "order_item_id": 1,
     *         "supplier_product_id": 10,
     *         "supplier_sku": "WM-001",
     *         "quantity": 2,
     *         "supplier_price": 799,
     *         "supplier_price_formatted": "£7.99",
     *         "retail_price": 1499,
     *         "retail_price_formatted": "£14.99",
     *         "profit_per_item": 700,
     *         "profit_per_item_formatted": "£7.00",
     *         "status": "pending",
     *         "supplier_product": {
     *           "id": 10,
     *           "name": "Wireless Mouse",
     *           "supplier_sku": "WM-001"
     *         },
     *         "order_item": {
     *           "id": 1,
     *           "product": {
     *             "id": 15,
     *             "name": "Wireless Mouse",
     *             "price_formatted": "£14.99"
     *           }
     *         }
     *       }
     *     ],
     *     "created_at": "2025-01-15T14:30:00.000000Z",
     *     "updated_at": "2025-01-16T14:20:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Dropship order not found" {
     *   "message": "No query results for model [App\\Models\\DropshipOrder] 999"
     * }
     */
    public function show(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropship_orders')) {
            Log::warning('Unauthorized access attempt to view dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Retrieving dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip()
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

            return $this->ok(
                'Dropship order retrieved successfully.',
                new DropshipOrderResource($dropshipOrder)
            );
        } catch (Exception $e) {
            Log::error('Failed to retrieve dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id ?? null,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to retrieve dropship order.', 500);
        }
    }

    /**
     * Update an existing dropship order
     *
     * Update a dropship order's details including status, tracking information, and notes.
     * The system validates status transitions and logs all changes for audit purposes.
     * Status changes trigger appropriate notifications and order updates.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to update. Example: 48
     *
     * @bodyParam status string optional The new status for the order. Example: confirmed
     * @bodyParam supplier_order_id string optional The supplier's order reference. Example: SUP-2025-001
     * @bodyParam tracking_number string optional The tracking number from supplier. Example: AB123456789GB
     * @bodyParam estimated_delivery string optional Estimated delivery date (YYYY-MM-DD HH:MM:SS). Example: 2025-01-20 17:00:00
     * @bodyParam notes string optional Updated notes or instructions. Example: Updated delivery instructions
     *
     * @response 200 scenario="Dropship order updated successfully" {
     *   "message": "Dropship order updated successfully.",
     *   "data": {
     *     "id": 48,
     *     "order_id": 123,
     *     "supplier_id": 5,
     *     "supplier_order_id": "SUP-2025-001",
     *     "status": "confirmed",
     *     "tracking_number": "AB123456789GB",
     *     "estimated_delivery": "2025-01-20T17:00:00.000000Z",
     *     "notes": "Updated delivery instructions",
     *     "updated_at": "2025-01-16T15:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Dropship order not found" {
     *   "message": "No query results for model [App\\Models\\DropshipOrder] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The status must be a valid dropship status.",
     *     "The estimated delivery must be a valid date."
     *   ]
     * }
     */
    public function update(UpdateDropshipOrderRequest $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_dropship_orders')) {
            Log::warning('Unauthorized access attempt to update dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            $data = $request->validated();

            Log::info('Updating dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'changes' => $data,
                'ip_address' => $request->ip()
            ]);

            $updatedOrder = DB::transaction(function () use ($dropshipOrder, $data, $user) {
                $originalStatus = $dropshipOrder->status;

                $dropshipOrder->update($data);

                if (isset($data['status']) && $originalStatus !== $data['status']) {
                    Log::info('Dropship order status changed', [
                        'user_id' => $user->id,
                        'dropship_order_id' => $dropshipOrder->id,
                        'old_status' => $originalStatus,
                        'new_status' => $data['status']
                    ]);
                }

                return $dropshipOrder;
            });

            Log::info('Dropship order updated successfully', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'order_id' => $dropshipOrder->order_id
            ]);

            return $this->ok(
                'Dropship order updated successfully.',
                new DropshipOrderResource($updatedOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to update dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'data' => $data ?? [],
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to update dropship order.', 500);
        }
    }

    /**
     * Delete a dropship order
     *
     * Delete a dropship order if it's in a valid state (pending or cancelled only).
     * This removes the order and all associated order items. Orders that have been
     * sent to suppliers cannot be deleted for audit purposes.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to delete. Example: 48
     *
     * @response 200 scenario="Dropship order deleted successfully" {
     *   "message": "Dropship order deleted successfully."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot delete order" {
     *   "message": "Cannot delete dropship order that has been sent to supplier."
     * }
     *
     * @response 404 scenario="Dropship order not found" {
     *   "message": "No query results for model [App\\Models\\DropshipOrder] 999"
     * }
     */
    public function destroy(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('delete_dropship_orders')) {
            Log::warning('Unauthorized access attempt to delete dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Attempting to delete dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'current_status' => $dropshipOrder->status,
                'ip_address' => $request->ip()
            ]);

            if (!in_array($dropshipOrder->status, [DropshipStatuses::PENDING, DropshipStatuses::CANCELLED])) {
                Log::warning('Cannot delete dropship order - invalid status', [
                    'user_id' => $user->id,
                    'dropship_order_id' => $dropshipOrder->id,
                    'status' => $dropshipOrder->status
                ]);
                return $this->error('Cannot delete dropship order that has been sent to supplier.', 400);
            }

            DB::transaction(function () use ($dropshipOrder, $user) {
                $dropshipOrder->dropshipOrderItems()->delete();
                $dropshipOrder->delete();

                Log::info('Dropship order deleted successfully', [
                    'user_id' => $user->id,
                    'dropship_order_id' => $dropshipOrder->id,
                    'order_id' => $dropshipOrder->order_id,
                    'supplier_id' => $dropshipOrder->supplier_id
                ]);
            });

            return $this->ok('Dropship order deleted successfully.');
        } catch (Exception $e) {
            Log::error('Failed to delete dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to delete dropship order.', 500);
        }
    }

    /**
     * Send dropship order to supplier
     *
     * Send a pending dropship order to the supplier for fulfillment. This endpoint validates
     * that the order is in pending status and the supplier is active before sending.
     * The order status is updated and integration data is recorded.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to send. Example: 48
     *
     * @response 200 scenario="Order sent to supplier successfully" {
     *   "message": "Dropship order sent to supplier successfully.",
     *   "data": {
     *     "id": 48,
     *     "status": "sent_to_supplier",
     *     "sent_to_supplier_at": "2025-01-16T16:00:00.000000Z",
     *     "integration_type": "api",
     *     "supplier": {
     *       "id": 5,
     *       "name": "Global Suppliers Ltd"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Order already sent" {
     *   "message": "Dropship order has already been sent to supplier."
     * }
     *
     * @response 400 scenario="Supplier not active" {
     *   "message": "Supplier is not active."
     * }
     */
    public function sendToSupplier(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_dropship_orders')) {
            Log::warning('Unauthorized access attempt to send dropship order to supplier', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Attempting to send dropship order to supplier', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_id' => $dropshipOrder->supplier_id,
                'current_status' => $dropshipOrder->status,
                'ip_address' => $request->ip()
            ]);

            if (!$dropshipOrder->isPending()) {
                Log::warning('Cannot send dropship order - not in pending status', [
                    'user_id' => $user->id,
                    'dropship_order_id' => $dropshipOrder->id,
                    'status' => $dropshipOrder->status
                ]);
                return $this->error('Dropship order has already been sent to supplier.', 400);
            }

            $supplier = $dropshipOrder->supplier;
            if (!$supplier->isActive()) {
                Log::warning('Cannot send dropship order - supplier not active', [
                    'user_id' => $user->id,
                    'dropship_order_id' => $dropshipOrder->id,
                    'supplier_id' => $supplier->id,
                    'supplier_status' => $supplier->status
                ]);
                return $this->error('Supplier is not active.', 400);
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

            return $this->ok(
                'Dropship order sent to supplier successfully.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to send dropship order to supplier', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to send dropship order to supplier.', 500);
        }
    }

    /**
     * Mark dropship order as confirmed
     *
     * Mark a dropship order as confirmed by the supplier with their order ID and response data.
     * This endpoint updates the order status and records supplier confirmation details.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to mark as confirmed. Example: 48
     *
     * @bodyParam supplier_order_id string required The supplier's order reference ID. Example: SUP-2025-001
     * @bodyParam estimated_delivery string optional Estimated delivery date (YYYY-MM-DD). Example: 2025-01-25
     * @bodyParam supplier_response object optional Additional response data from supplier.
     *
     * @response 200 scenario="Order marked as confirmed" {
     *   "message": "Dropship order marked as confirmed.",
     *   "data": {
     *     "id": 48,
     *     "status": "confirmed",
     *     "supplier_order_id": "SUP-2025-001",
     *     "estimated_delivery": "2025-01-25T17:00:00.000000Z",
     *     "confirmed_at": "2025-01-16T16:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The supplier order id field is required.",
     *     "The estimated delivery must be after today."
     *   ]
     * }
     */
    public function markAsConfirmed(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_dropship_orders')) {
            Log::warning('Unauthorized access attempt to mark dropship order as confirmed', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'supplier_order_id' => 'required|string|max:255',
            'estimated_delivery' => 'nullable|date|after:today',
            'supplier_response' => 'nullable|array'
        ]);

        try {
            $data = $request->all();

            Log::info('Marking dropship order as confirmed', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_order_id' => $data['supplier_order_id'],
                'ip_address' => $request->ip()
            ]);

            $dropshipOrder->markAsConfirmed(
                $data['supplier_order_id'],
                $data['supplier_response'] ?? []
            );

            if (isset($data['estimated_delivery'])) {
                $dropshipOrder->update(['estimated_delivery' => $data['estimated_delivery']]);
            }

            Log::info('Dropship order confirmed by supplier', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'supplier_order_id' => $data['supplier_order_id']
            ]);

            return $this->ok(
                'Dropship order marked as confirmed.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to mark dropship order as confirmed', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to mark as confirmed.', 500);
        }
    }

    /**
     * Mark dropship order as shipped
     *
     * Mark a dropship order as shipped with tracking information from the supplier.
     * This updates the order status and provides tracking details for customer visibility.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to mark as shipped. Example: 48
     *
     * @bodyParam tracking_number string required The tracking number from carrier. Example: AB123456789GB
     * @bodyParam carrier string optional The carrier name. Example: Royal Mail
     * @bodyParam estimated_delivery string optional Estimated delivery date (YYYY-MM-DD). Example: 2025-01-25
     *
     * @response 200 scenario="Order marked as shipped" {
     *   "message": "Dropship order marked as shipped.",
     *   "data": {
     *     "id": 48,
     *     "status": "shipped",
     *     "tracking_number": "AB123456789GB",
     *     "carrier": "Royal Mail",
     *     "estimated_delivery": "2025-01-25T17:00:00.000000Z",
     *     "shipped_at": "2025-01-18T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The tracking number field is required.",
     *     "The tracking number must be at least 8 characters."
     *   ]
     * }
     */
    public function markAsShipped(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_dropship_orders')) {
            Log::warning('Unauthorized access attempt to mark dropship order as shipped', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'tracking_number' => 'required|string|max:255',
            'carrier' => 'nullable|string|max:255',
            'estimated_delivery' => 'nullable|date|after:today'
        ]);

        try {
            $data = $request->all();

            Log::info('Marking dropship order as shipped', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'tracking_number' => $data['tracking_number'],
                'carrier' => $data['carrier'] ?? 'Unknown',
                'ip_address' => $request->ip()
            ]);

            $dropshipOrder->markAsShipped(
                $data['tracking_number'],
                $data['carrier'] ?? null,
                isset($data['estimated_delivery']) ? \Carbon\Carbon::parse($data['estimated_delivery']) : null
            );

            Log::info('Dropship order marked as shipped', [
                'user_id' => $user->id,
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
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to mark as shipped.', 500);
        }
    }

    /**
     * Mark dropship order as delivered
     *
     * Mark a dropship order as delivered, completing the fulfillment process.
     * This is typically called when delivery confirmation is received from the carrier.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to mark as delivered. Example: 48
     *
     * @response 200 scenario="Order marked as delivered" {
     *   "message": "Dropship order marked as delivered.",
     *   "data": {
     *     "id": 48,
     *     "status": "delivered",
     *     "delivered_at": "2025-01-22T14:30:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Dropship order not found" {
     *   "message": "No query results for model [App\\Models\\DropshipOrder] 999"
     * }
     */
    public function markAsDelivered(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('edit_dropship_orders')) {
            Log::warning('Unauthorized access attempt to mark dropship order as delivered', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Marking dropship order as delivered', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip()
            ]);

            $dropshipOrder->markAsDelivered();

            Log::info('Dropship order marked as delivered', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id
            ]);

            return $this->ok(
                'Dropship order marked as delivered.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to mark dropship order as delivered', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to mark as delivered.', 500);
        }
    }

    /**
     * Cancel a dropship order
     *
     * Cancel a dropship order with an optional reason. This marks the order as cancelled
     * and can trigger refund processes if applicable. Cannot cancel delivered orders.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to cancel. Example: 48
     *
     * @bodyParam reason string optional Reason for cancellation. Example: Customer requested cancellation
     *
     * @response 200 scenario="Order cancelled successfully" {
     *   "message": "Dropship order cancelled successfully.",
     *   "data": {
     *     "id": 48,
     *     "status": "cancelled",
     *     "cancelled_at": "2025-01-17T09:15:00.000000Z",
     *     "cancellation_reason": "Customer requested cancellation"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot cancel delivered order" {
     *   "message": "Cannot cancel delivered dropship order."
     * }
     */
    public function cancel(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('cancel_dropship_orders')) {
            Log::warning('Unauthorized access attempt to cancel dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:1000'
        ]);

        try {
            $reason = $request->input('reason');

            Log::info('Cancelling dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'reason' => $reason,
                'current_status' => $dropshipOrder->status,
                'ip_address' => $request->ip()
            ]);

            if ($dropshipOrder->isDelivered()) {
                Log::warning('Cannot cancel delivered dropship order', [
                    'user_id' => $user->id,
                    'dropship_order_id' => $dropshipOrder->id,
                    'status' => $dropshipOrder->status
                ]);
                return $this->error('Cannot cancel delivered dropship order.', 400);
            }

            $dropshipOrder->markAsCancelled($reason);

            Log::info('Dropship order cancelled', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'reason' => $reason
            ]);

            return $this->ok(
                'Dropship order cancelled successfully.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to cancel dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to cancel dropship order.', 500);
        }
    }

    /**
     * Retry a failed dropship order
     *
     * Retry a failed dropship order by resetting its status to pending and incrementing
     * the retry count. This is used when orders fail due to temporary issues.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @urlParam dropshipOrder integer required The ID of the dropship order to retry. Example: 48
     *
     * @response 200 scenario="Order retry initiated" {
     *   "message": "Dropship order retry initiated.",
     *   "data": {
     *     "id": 48,
     *     "status": "pending",
     *     "retry_count": 1,
     *     "last_retry_at": "2025-01-17T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 400 scenario="Cannot retry order" {
     *   "message": "Dropship order cannot be retried."
     * }
     */
    public function retry(Request $request, DropshipOrder $dropshipOrder)
    {
        $user = $request->user();

        if (!$user->hasPermission('retry_dropship_orders')) {
            Log::warning('Unauthorized access attempt to retry dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Retrying dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'current_retry_count' => $dropshipOrder->retry_count,
                'ip_address' => $request->ip()
            ]);

            if (!$dropshipOrder->canRetry()) {
                Log::warning('Cannot retry dropship order', [
                    'user_id' => $user->id,
                    'dropship_order_id' => $dropshipOrder->id,
                    'status' => $dropshipOrder->status,
                    'retry_count' => $dropshipOrder->retry_count
                ]);
                return $this->error('Dropship order cannot be retried.', 400);
            }

            $dropshipOrder->incrementRetryCount();
            $dropshipOrder->updateStatus(DropshipStatuses::PENDING);

            Log::info('Dropship order retry initiated', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'retry_count' => $dropshipOrder->retry_count
            ]);

            return $this->ok(
                'Dropship order retry initiated.',
                new DropshipOrderResource($dropshipOrder->load(['order.user', 'supplier', 'dropshipOrderItems']))
            );
        } catch (Exception $e) {
            Log::error('Failed to retry dropship order', [
                'user_id' => $user->id,
                'dropship_order_id' => $dropshipOrder->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to retry dropship order.', 500);
        }
    }

    /**
     * Bulk update status of multiple dropship orders
     *
     * Update the status of multiple dropship orders simultaneously. This is useful for
     * batch processing of orders that need status changes.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @bodyParam dropship_order_ids array required Array of dropship order IDs to update. Example: [1, 2, 3]
     * @bodyParam dropship_order_ids.* integer required Each order ID must be valid. Example: 1
     * @bodyParam status string required The new status to apply to all orders. Example: confirmed
     * @bodyParam notes string optional Optional notes for the status change. Example: Bulk status update
     *
     * @response 200 scenario="Orders updated successfully" {
     *   "message": "Successfully updated status for 3 dropship orders.",
     *   "data": {
     *     "updated_count": 3,
     *     "new_status": "confirmed"
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The dropship order ids field is required.",
     *     "The status field is required."
     *   ]
     * }
     */
    public function bulkUpdateStatus(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('bulk_manage_dropship_orders')) {
            Log::warning('Unauthorized access attempt to bulk update dropship order status', [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

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

            Log::info('Bulk updating dropship order status', [
                'user_id' => $user->id,
                'order_ids' => $orderIds,
                'new_status' => $status,
                'order_count' => count($orderIds),
                'ip_address' => $request->ip()
            ]);

            $updated = DB::transaction(function () use ($orderIds, $status, $notes, $user) {
                $orders = DropshipOrder::whereIn('id', $orderIds)->get();

                foreach ($orders as $order) {
                    $order->updateStatus($status, ['notes' => $notes]);
                }

                return $orders->count();
            });

            Log::info('Bulk dropship order status update completed', [
                'user_id' => $user->id,
                'orders_updated' => $updated,
                'new_status' => $status
            ]);

            return $this->ok("Successfully updated status for {$updated} dropship orders.", [
                'updated_count' => $updated,
                'new_status' => $status
            ]);
        } catch (Exception $e) {
            Log::error('Failed to bulk update dropship order status', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to bulk update status.', 500);
        }
    }

    /**
     * Get dropship order statistics
     *
     * Retrieve comprehensive statistics about dropship orders including totals, status breakdowns,
     * supplier performance, and recent activity. Essential for dashboards and reporting.
     *
     * @group Dropship Order Management
     * @authenticated
     *
     * @response 200 scenario="Statistics retrieved successfully" {
     *   "message": "Dropship order stats retrieved successfully.",
     *   "data": {
     *     "totals": {
     *       "all_orders": 156,
     *       "pending": 12,
     *       "active": 45,
     *       "completed": 89,
     *       "overdue": 3
     *     },
     *     "by_status": {
     *       "pending": 12,
     *       "confirmed": 25,
     *       "shipped": 20,
     *       "delivered": 89,
     *       "cancelled": 5,
     *       "rejected": 5
     *     },
     *     "by_supplier": [
     *       {
     *         "supplier_name": "Global Suppliers Ltd",
     *         "count": 45
     *       },
     *       {
     *         "supplier_name": "Fast Ship Co",
     *         "count": 38
     *       }
     *     ],
     *     "recent_activity": [
     *       {
     *         "id": 48,
     *         "order_id": 123,
     *         "supplier_name": "Global Suppliers Ltd",
     *         "customer_name": "John Smith",
     *         "status": "shipped",
     *         "total_cost": "£15.99",
     *         "created_at": "2025-01-15T14:30:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     */
    public function getStats(Request $request)
    {
        $user = $request->user();

        if (!$user->hasPermission('view_dropshipping_analytics')) {
            Log::warning('Unauthorized access attempt to view dropship order stats', [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            return $this->error('You do not have the required permissions.', 403);
        }

        try {
            Log::info('Retrieving dropship order statistics', [
                'user_id' => $user->id,
                'ip_address' => $request->ip()
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

            return $this->ok('Dropship order stats retrieved successfully.', $stats);
        } catch (Exception $e) {
            Log::error('Failed to get dropship order stats', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);
            return $this->error('Failed to retrieve stats.', 500);
        }
    }
}

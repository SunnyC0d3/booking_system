<?php

namespace App\Http\Controllers\V1\Admin;

use App\Services\V1\Orders\Order;
use App\Requests\V1\StoreOrderRequest;
use App\Requests\V1\UpdateOrderRequest;
use App\Requests\V1\IndexOrderRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\V1\ApiResponses;
use App\Models\Order as DB;
use \Exception;

class OrderController extends Controller
{
    use ApiResponses;

    private $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Retrieve paginated list of orders
     *
     * Get a paginated list of all orders in the system. This endpoint supports filtering by user and status,
     * and includes comprehensive order information including items, user details, and current status.
     *
     * @group Order Management
     * @authenticated
     *
     * @queryParam user_id integer optional Filter orders by specific user ID. Example: 123
     * @queryParam status_id integer optional Filter orders by specific status ID. Example: 2
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 1
     * @queryParam per_page integer optional Number of orders per page (max 50). Default: 15. Example: 20
     *
     * @response 200 scenario="Success with orders" {
     *   "message": "Orders retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "total_amount": 29.99,
     *         "total_amount_formatted": "£29.99",
     *         "created_at": "2025-01-15T10:30:00.000000Z",
     *         "updated_at": "2025-01-15T10:35:00.000000Z",
     *         "deleted_at": null,
     *         "user": {
     *           "id": 5,
     *           "name": "John Smith",
     *           "email": "john.smith@example.com",
     *           "email_verified_at": "2025-01-10T08:00:00.000000Z"
     *         },
     *         "status": {
     *           "id": 2,
     *           "name": "Processing"
     *         },
     *         "order_items": [
     *           {
     *             "id": 1,
     *             "quantity": 2,
     *             "price": 1499,
     *             "price_formatted": "£14.99",
     *             "line_total": 2998,
     *             "line_total_formatted": "£29.98",
     *             "product": {
     *               "id": 10,
     *               "name": "Wireless Headphones",
     *               "description": "High-quality wireless headphones with noise cancellation"
     *             },
     *             "product_variant": {
     *               "id": 3,
     *               "value": "Black",
     *               "additional_price": 0,
     *               "product_attribute": {
     *                 "id": 1,
     *                 "name": "Color"
     *               }
     *             },
     *             "order_return": null
     *           }
     *         ],
     *         "payments": [
     *           {
     *             "id": 1,
     *             "amount": 2999,
     *             "amount_formatted": "£29.99",
     *             "status": "Paid",
     *             "method": "stripe",
     *             "transaction_reference": "pi_1234567890abcdef",
     *             "processed_at": "2025-01-15T10:32:00.000000Z"
     *           }
     *         ]
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 42,
     *     "last_page": 3,
     *     "from": 1,
     *     "to": 15
     *   }
     * }
     *
     * @response 200 scenario="No orders found" {
     *   "message": "Orders retrieved successfully.",
     *   "data": {
     *     "data": [],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 0,
     *     "last_page": 1,
     *     "from": null,
     *     "to": null
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *   "errors": [
     *     "The user id field must be an integer.",
     *     "The status id field must exist in order_statuses table."
     *   ]
     * }
     */
    public function index(IndexOrderRequest $request)
    {
        try {
            return $this->order->all($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Create a new order
     *
     * Create a new order with multiple order items. The system will automatically calculate the total amount
     * based on the items and their quantities. All monetary values should be provided in pennies.
     *
     * @group Order Management
     * @authenticated
     *
     * @bodyParam user_id integer required The ID of the user placing the order. Example: 5
     * @bodyParam status_id integer optional The initial status ID for the order. If not provided, defaults to "Pending Payment". Example: 1
     * @bodyParam order_items array required Array of order items. Must contain at least one item.
     * @bodyParam order_items.*.product_id integer required The ID of the product being ordered. Example: 10
     * @bodyParam order_items.*.product_variant_id integer optional The ID of the product variant (if applicable). Example: 3
     * @bodyParam order_items.*.quantity integer required The quantity of this item (minimum 1). Example: 2
     * @bodyParam order_items.*.price numeric required The price per item in pounds (will be converted to pennies). Example: 14.99
     *
     * @response 200 scenario="Order created successfully" {
     *   "message": "Order created successfully.",
     *   "data": {
     *     "id": 43,
     *     "total_amount": 2999,
     *     "total_amount_formatted": "£29.99",
     *     "created_at": "2025-01-15T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T14:20:00.000000Z",
     *     "deleted_at": null,
     *     "user": {
     *       "id": 5,
     *       "name": "John Smith",
     *       "email": "john.smith@example.com"
     *     },
     *     "status": {
     *       "id": 1,
     *       "name": "Pending Payment"
     *     },
     *     "order_items": [
     *       {
     *         "id": 85,
     *         "quantity": 2,
     *         "price": 1499,
     *         "price_formatted": "£14.99",
     *         "line_total": 2998,
     *         "line_total_formatted": "£29.98",
     *         "product": {
     *           "id": 10,
     *           "name": "Wireless Headphones",
     *           "price": 1499,
     *           "price_formatted": "£14.99"
     *         },
     *         "product_variant": {
     *           "id": 3,
     *           "value": "Black",
     *           "additional_price": 0
     *         }
     *       }
     *     ],
     *     "payments": []
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The user id field is required.",
     *     "The order items field is required.",
     *     "The order items.0.product id field is required.",
     *     "The order items.0.quantity must be at least 1.",
     *     "The order items.0.price must be greater than 0."
     *   ]
     * }
     *
     * @response 404 scenario="User not found" {
     *   "message": "The selected user id is invalid."
     * }
     *
     * @response 500 scenario="Server error" {
     *   "message": "An error occurred while creating the order."
     * }
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            return $this->order->create($request);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Retrieve a specific order
     *
     * Get detailed information about a specific order including all order items, user details,
     * payment information, and any associated returns or refunds.
     *
     * @group Order Management
     * @authenticated
     *
     * @urlParam order integer required The ID of the order to retrieve. Example: 43
     *
     * @response 200 scenario="Order found" {
     *   "message": "Order retrieved successfully.",
     *   "data": {
     *     "id": 43,
     *     "total_amount": 5998,
     *     "total_amount_formatted": "£59.98",
     *     "created_at": "2025-01-15T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T14:25:00.000000Z",
     *     "deleted_at": null,
     *     "user": {
     *       "id": 5,
     *       "name": "John Smith",
     *       "email": "john.smith@example.com",
     *       "email_verified_at": "2025-01-10T08:00:00.000000Z"
     *     },
     *     "status": {
     *       "id": 3,
     *       "name": "Confirmed"
     *     },
     *     "order_items": [
     *       {
     *         "id": 85,
     *         "quantity": 2,
     *         "price": 1499,
     *         "price_formatted": "£14.99",
     *         "line_total": 2998,
     *         "line_total_formatted": "£29.98",
     *         "created_at": "2025-01-15T14:20:00.000000Z",
     *         "product": {
     *           "id": 10,
     *           "name": "Wireless Headphones",
     *           "description": "High-quality wireless headphones with noise cancellation",
     *           "price": 1499,
     *           "price_formatted": "£14.99"
     *         },
     *         "product_variant": {
     *           "id": 3,
     *           "value": "Black",
     *           "additional_price": 0,
     *           "product_attribute": {
     *             "id": 1,
     *             "name": "Color"
     *           }
     *         },
     *         "order_return": {
     *           "id": 12,
     *           "reason": "Product arrived damaged",
     *           "status": {
     *             "id": 1,
     *             "name": "Requested"
     *           },
     *           "created_at": "2025-01-16T09:15:00.000000Z"
     *         }
     *       },
     *       {
     *         "id": 86,
     *         "quantity": 1,
     *         "price": 2999,
     *         "price_formatted": "£29.99",
     *         "line_total": 2999,
     *         "line_total_formatted": "£29.99",
     *         "created_at": "2025-01-15T14:20:00.000000Z",
     *         "product": {
     *           "id": 15,
     *           "name": "Bluetooth Speaker",
     *           "description": "Portable waterproof Bluetooth speaker",
     *           "price": 2999,
     *           "price_formatted": "£29.99"
     *         },
     *         "product_variant": null,
     *         "order_return": null
     *       }
     *     ],
     *     "payments": [
     *       {
     *         "id": 25,
     *         "amount": 5998,
     *         "amount_formatted": "£59.98",
     *         "status": "Paid",
     *         "method": "stripe",
     *         "transaction_reference": "pi_1234567890abcdef",
     *         "processed_at": "2025-01-15T14:22:00.000000Z",
     *         "created_at": "2025-01-15T14:21:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "message": "No query results for model [App\\Models\\Order] 999"
     * }
     */
    public function show(Request $request, DB $order)
    {
        try {
            return $this->order->find($request, $order);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Update an existing order
     *
     * Update an existing order including its items. This will replace all existing order items with the new ones
     * and recalculate the total amount. Use with caution as this will affect order history.
     *
     * @group Order Management
     * @authenticated
     *
     * @urlParam order integer required The ID of the order to update. Example: 43
     *
     * @bodyParam user_id integer optional The ID of the user (can be changed if needed). Example: 5
     * @bodyParam status_id integer optional The new status ID for the order. Example: 2
     * @bodyParam order_items array required Array of order items (replaces existing items).
     * @bodyParam order_items.*.product_id integer required The ID of the product. Example: 10
     * @bodyParam order_items.*.product_variant_id integer optional The ID of the product variant. Example: 3
     * @bodyParam order_items.*.quantity integer required The quantity (minimum 1). Example: 3
     * @bodyParam order_items.*.price numeric required The price per item in pounds. Example: 14.99
     *
     * @response 200 scenario="Order updated successfully" {
     *   "message": "Order updated successfully.",
     *   "data": {
     *     "id": 43,
     *     "total_amount": 4497,
     *     "total_amount_formatted": "£44.97",
     *     "created_at": "2025-01-15T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T15:30:00.000000Z",
     *     "deleted_at": null,
     *     "user": {
     *       "id": 5,
     *       "name": "John Smith",
     *       "email": "john.smith@example.com"
     *     },
     *     "status": {
     *       "id": 2,
     *       "name": "Processing"
     *     },
     *     "order_items": [
     *       {
     *         "id": 87,
     *         "quantity": 3,
     *         "price": 1499,
     *         "price_formatted": "£14.99",
     *         "line_total": 4497,
     *         "line_total_formatted": "£44.97",
     *         "product": {
     *           "id": 10,
     *           "name": "Wireless Headphones"
     *         }
     *       }
     *     ],
     *     "payments": []
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "message": "No query results for model [App\\Models\\Order] 999"
     * }
     *
     * @response 422 scenario="Validation errors" {
     *   "errors": [
     *     "The order items field is required.",
     *     "The order items.0.quantity must be at least 1."
     *   ]
     * }
     *
     * @response 400 scenario="Order cannot be updated" {
     *   "message": "Cannot update order that has been shipped or completed."
     * }
     */
    public function update(UpdateOrderRequest $request, DB $order)
    {
        try {
            return $this->order->update($request, $order);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Soft delete an order
     *
     * Soft delete an order, making it inactive but preserving the data for audit purposes.
     * The order will be hidden from normal queries but can be restored if needed.
     *
     * @group Order Management
     * @authenticated
     *
     * @urlParam order integer required The ID of the order to soft delete. Example: 43
     *
     * @response 200 scenario="Order soft deleted successfully" {
     *   "message": "Order deleted (soft)."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "message": "No query results for model [App\\Models\\Order] 999"
     * }
     *
     * @response 400 scenario="Order cannot be deleted" {
     *   "message": "Cannot delete order with active payments or shipments."
     * }
     */
    public function destroy(Request $request, DB $order)
    {
        try {
            return $this->order->delete($request, $order);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Restore a soft deleted order
     *
     * Restore a previously soft deleted order, making it active again in the system.
     * Only orders that have been soft deleted can be restored.
     *
     * @group Order Management
     * @authenticated
     *
     * @urlParam id integer required The ID of the soft deleted order to restore. Example: 43
     *
     * @response 200 scenario="Order restored successfully" {
     *   "message": "Order restored successfully.",
     *   "data": {
     *     "id": 43,
     *     "total_amount": 2999,
     *     "total_amount_formatted": "£29.99",
     *     "created_at": "2025-01-15T14:20:00.000000Z",
     *     "updated_at": "2025-01-15T16:45:00.000000Z",
     *     "deleted_at": null,
     *     "user": {
     *       "id": 5,
     *       "name": "John Smith",
     *       "email": "john.smith@example.com"
     *     },
     *     "status": {
     *       "id": 1,
     *       "name": "Pending Payment"
     *     }
     *   }
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "message": "No query results for model [App\\Models\\Order] 43"
     * }
     *
     * @response 400 scenario="Order not deleted" {
     *   "message": "Order is not deleted."
     * }
     */
    public function restore(Request $request, int $id)
    {
        try {
            return $this->order->restore($request, $id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Permanently delete an order
     *
     * Permanently remove an order from the database. This action is irreversible and should be used
     * with extreme caution. The order must be soft deleted first before it can be permanently deleted.
     *
     * @group Order Management
     * @authenticated
     *
     * @urlParam id integer required The ID of the soft deleted order to permanently delete. Example: 43
     *
     * @response 200 scenario="Order permanently deleted" {
     *   "message": "Order permanently deleted."
     * }
     *
     * @response 403 scenario="Insufficient permissions" {
     *   "message": "You do not have the required permissions."
     * }
     *
     * @response 404 scenario="Order not found" {
     *   "message": "No query results for model [App\\Models\\Order] 43"
     * }
     *
     * @response 400 scenario="Order must be soft deleted first" {
     *   "message": "Order must be soft deleted before force deleting."
     * }
     *
     * @response 409 scenario="Order has dependencies" {
     *   "message": "Cannot permanently delete order with associated payments or returns."
     * }
     */
    public function forceDelete(Request $request, int $id)
    {
        try {
            return $this->order->forceDelete($request, $id);
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}

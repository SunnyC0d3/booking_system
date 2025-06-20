<?php

namespace App\Http\Controllers\V1\Public;

use App\Requests\V1\IndexOrderRequest;
use App\Services\V1\Orders\Order;
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
     * Retrieve user's orders
     *
     * Get a paginated list of orders for the authenticated user. This endpoint allows users to view
     * their order history with optional filtering by status. Orders include complete information about
     * items purchased, payment status, delivery details, and current order status. Users can only
     * access their own orders for privacy and security.
     *
     * @group User Orders
     * @authenticated
     *
     * @queryParam user_id integer optional Filter orders by user ID (must match authenticated user). Example: 123
     * @queryParam status_id integer optional Filter orders by specific status ID. See order statuses endpoint for available options. Example: 2
     * @queryParam page integer optional Page number for pagination. Default: 1. Example: 2
     * @queryParam per_page integer optional Number of orders per page (max 50). Default: 15. Example: 10
     *
     * @response 200 scenario="User orders retrieved successfully" {
     *   "message": "Orders retrieved successfully.",
     *   "data": {
     *     "data": [
     *       {
     *         "id": 45,
     *         "total_amount": 8498,
     *         "total_amount_formatted": "£84.98",
     *         "created_at": "2025-01-15T14:20:00.000000Z",
     *         "updated_at": "2025-01-16T09:45:00.000000Z",
     *         "deleted_at": null,
     *         "user": {
     *           "id": 123,
     *           "name": "Sarah Johnson",
     *           "email": "sarah.johnson@example.com",
     *           "email_verified_at": "2025-01-10T08:00:00.000000Z"
     *         },
     *         "status": {
     *           "id": 4,
     *           "name": "Shipped"
     *         },
     *         "order_items": [
     *           {
     *             "id": 89,
     *             "quantity": 1,
     *             "price": 7999,
     *             "price_formatted": "£79.99",
     *             "line_total": 7999,
     *             "line_total_formatted": "£79.99",
     *             "created_at": "2025-01-15T14:20:00.000000Z",
     *             "product": {
     *               "id": 15,
     *               "name": "Wireless Bluetooth Headphones",
     *               "description": "Premium quality wireless headphones with active noise cancellation",
     *               "price": 7999,
     *               "price_formatted": "£79.99"
     *             },
     *             "product_variant": {
     *               "id": 24,
     *               "value": "White",
     *               "additional_price": 500,
     *               "additional_price_formatted": "£5.00",
     *               "product_attribute": {
     *                 "id": 1,
     *                 "name": "Color"
     *               }
     *             },
     *             "order_return": null
     *           },
     *           {
     *             "id": 90,
     *             "quantity": 1,
     *             "price": 499,
     *             "price_formatted": "£4.99",
     *             "line_total": 499,
     *             "line_total_formatted": "£4.99",
     *             "created_at": "2025-01-15T14:20:00.000000Z",
     *             "product": {
     *               "id": 28,
     *               "name": "USB-C Charging Cable",
     *               "description": "High-speed USB-C to USB-A charging cable",
     *               "price": 499,
     *               "price_formatted": "£4.99"
     *             },
     *             "product_variant": null,
     *             "order_return": null
     *           }
     *         ],
     *         "payments": [
     *           {
     *             "id": 67,
     *             "amount": 8498,
     *             "amount_formatted": "£84.98",
     *             "status": "Paid",
     *             "method": "stripe",
     *             "transaction_reference": "pi_1Hxxxxxxxxxxxx",
     *             "processed_at": "2025-01-15T14:22:00.000000Z",
     *             "created_at": "2025-01-15T14:21:00.000000Z"
     *           }
     *         ]
     *       },
     *       {
     *         "id": 38,
     *         "total_amount": 2999,
     *         "total_amount_formatted": "£29.99",
     *         "created_at": "2025-01-10T16:30:00.000000Z",
     *         "updated_at": "2025-01-12T11:15:00.000000Z",
     *         "deleted_at": null,
     *         "user": {
     *           "id": 123,
     *           "name": "Sarah Johnson",
     *           "email": "sarah.johnson@example.com",
     *           "email_verified_at": "2025-01-10T08:00:00.000000Z"
     *         },
     *         "status": {
     *           "id": 6,
     *           "name": "Delivered"
     *         },
     *         "order_items": [
     *           {
     *             "id": 75,
     *             "quantity": 1,
     *             "price": 2999,
     *             "price_formatted": "£29.99",
     *             "line_total": 2999,
     *             "line_total_formatted": "£29.99",
     *             "created_at": "2025-01-10T16:30:00.000000Z",
     *             "product": {
     *               "id": 22,
     *               "name": "Bluetooth Portable Speaker",
     *               "description": "Compact waterproof speaker with 12-hour battery",
     *               "price": 2999,
     *               "price_formatted": "£29.99"
     *             },
     *             "product_variant": null,
     *             "order_return": {
     *               "id": 12,
     *               "reason": "Product quality issue - speaker has crackling sound",
     *               "status": {
     *                 "id": 3,
     *                 "name": "Approved"
     *               },
     *               "created_at": "2025-01-14T09:30:00.000000Z",
     *               "updated_at": "2025-01-15T14:20:00.000000Z"
     *             }
     *           }
     *         ],
     *         "payments": [
     *           {
     *             "id": 58,
     *             "amount": 2999,
     *             "amount_formatted": "£29.99",
     *             "status": "Paid",
     *             "method": "stripe",
     *             "transaction_reference": "pi_1Gxxxxxxxxxx",
     *             "processed_at": "2025-01-10T16:32:00.000000Z",
     *             "created_at": "2025-01-10T16:31:00.000000Z"
     *           }
     *         ]
     *       }
     *     ],
     *     "current_page": 1,
     *     "per_page": 15,
     *     "total": 12,
     *     "last_page": 1,
     *     "from": 1,
     *     "to": 12,
     *     "path": "https://yourapi.com/api/v1/orders",
     *     "first_page_url": "https://yourapi.com/api/v1/orders?page=1",
     *     "last_page_url": "https://yourapi.com/api/v1/orders?page=1",
     *     "next_page_url": null,
     *     "prev_page_url": null
     *   }
     * }
     *
     * @response 200 scenario="No orders found for user" {
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
     * @response 401 scenario="User not authenticated" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 403 scenario="Access denied to other user's orders" {
     *   "message": "You can only access your own orders."
     * }
     *
     * @response 422 scenario="Invalid filter parameters" {
     *   "errors": [
     *     "The user id must be an integer.",
     *     "The status id must exist in order_statuses table."
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
     * Retrieve a specific order
     *
     * Get detailed information about a specific order belonging to the authenticated user.
     * This includes complete order details, all purchased items with product information,
     * payment records, shipping status, and any returns or refunds. Users can only access
     * their own orders. This endpoint is perfect for order confirmation pages and order tracking.
     *
     * @group User Orders
     * @authenticated
     *
     * @urlParam order integer required The ID of the order to retrieve. Must belong to the authenticated user. Example: 45
     *
     * @response 200 scenario="Order details retrieved successfully" {
     *   "message": "Order retrieved successfully.",
     *   "data": {
     *     "id": 45,
     *     "total_amount": 8498,
     *     "total_amount_formatted": "£84.98",
     *     "created_at": "2025-01-15T14:20:00.000000Z",
     *     "updated_at": "2025-01-16T09:45:00.000000Z",
     *     "deleted_at": null,
     *     "user": {
     *       "id": 123,
     *       "name": "Sarah Johnson",
     *       "email": "sarah.johnson@example.com",
     *       "email_verified_at": "2025-01-10T08:00:00.000000Z",
     *       "user_address": {
     *         "id": 45,
     *         "address_line1": "123 Oak Street",
     *         "address_line2": "Apartment 4B",
     *         "city": "London",
     *         "state": "England",
     *         "country": "United Kingdom",
     *         "postal_code": "SW1A 1AA"
     *       }
     *     },
     *     "status": {
     *       "id": 4,
     *       "name": "Shipped"
     *     },
     *     "order_items": [
     *       {
     *         "id": 89,
     *         "quantity": 1,
     *         "price": 7999,
     *         "price_formatted": "£79.99",
     *         "line_total": 7999,
     *         "line_total_formatted": "£79.99",
     *         "created_at": "2025-01-15T14:20:00.000000Z",
     *         "updated_at": "2025-01-15T14:20:00.000000Z",
     *         "product": {
     *           "id": 15,
     *           "name": "Wireless Bluetooth Headphones",
     *           "description": "Premium quality wireless headphones with active noise cancellation, 30-hour battery life, and superior sound quality.",
     *           "price": 7999,
     *           "price_formatted": "£79.99",
     *           "featured_image": "https://yourapi.com/storage/products/headphones-featured.jpg"
     *         },
     *         "product_variant": {
     *           "id": 24,
     *           "value": "White",
     *           "additional_price": 500,
     *           "additional_price_formatted": "£5.00",
     *           "quantity": 20,
     *           "product_attribute": {
     *             "id": 1,
     *             "name": "Color"
     *           }
     *         },
     *         "order_return": null
     *       },
     *       {
     *         "id": 90,
     *         "quantity": 1,
     *         "price": 499,
     *         "price_formatted": "£4.99",
     *         "line_total": 499,
     *         "line_total_formatted": "£4.99",
     *         "created_at": "2025-01-15T14:20:00.000000Z",
     *         "updated_at": "2025-01-15T14:20:00.000000Z",
     *         "product": {
     *           "id": 28,
     *           "name": "USB-C Charging Cable",
     *           "description": "High-speed USB-C to USB-A charging cable - 1 meter length",
     *           "price": 499,
     *           "price_formatted": "£4.99",
     *           "featured_image": "https://yourapi.com/storage/products/usb-cable-featured.jpg"
     *         },
     *         "product_variant": null,
     *         "order_return": null
     *       }
     *     ],
     *     "payments": [
     *       {
     *         "id": 67,
     *         "amount": 8498,
     *         "amount_formatted": "£84.98",
     *         "status": "Paid",
     *         "method": "stripe",
     *         "transaction_reference": "pi_1Hxxxxxxxxxxxx",
     *         "processed_at": "2025-01-15T14:22:00.000000Z",
     *         "created_at": "2025-01-15T14:21:00.000000Z",
     *         "updated_at": "2025-01-15T14:22:00.000000Z",
     *         "payment_method": {
     *           "id": 1,
     *           "name": "stripe"
     *         }
     *       }
     *     ]
     *   }
     * }
     *
     * @response 200 scenario="Order with returns and refunds" {
     *   "message": "Order retrieved successfully.",
     *   "data": {
     *     "id": 38,
     *     "total_amount": 2999,
     *     "total_amount_formatted": "£29.99",
     *     "created_at": "2025-01-10T16:30:00.000000Z",
     *     "updated_at": "2025-01-15T11:30:00.000000Z",
     *     "deleted_at": null,
     *     "user": {
     *       "id": 123,
     *       "name": "Sarah Johnson",
     *       "email": "sarah.johnson@example.com"
     *     },
     *     "status": {
     *       "id": 8,
     *       "name": "Partially Refunded"
     *     },
     *     "order_items": [
     *       {
     *         "id": 75,
     *         "quantity": 1,
     *         "price": 2999,
     *         "price_formatted": "£29.99",
     *         "line_total": 2999,
     *         "line_total_formatted": "£29.99",
     *         "product": {
     *           "id": 22,
     *           "name": "Bluetooth Portable Speaker",
     *           "description": "Compact waterproof speaker with 12-hour battery"
     *         },
     *         "product_variant": null,
     *         "order_return": {
     *           "id": 12,
     *           "reason": "Product quality issue - speaker has crackling sound at high volume",
     *           "status": {
     *             "id": 7,
     *             "name": "Completed"
     *           },
     *           "created_at": "2025-01-14T09:30:00.000000Z",
     *           "updated_at": "2025-01-15T11:30:00.000000Z",
     *           "has_refunds": true,
     *           "total_refunded_amount": 2999,
     *           "total_refunded_amount_formatted": "£29.99",
     *           "order_refunds": [
     *             {
     *               "id": 8,
     *               "amount": 2999,
     *               "amount_formatted": "£29.99",
     *               "processed_at": "2025-01-15T11:25:00.000000Z",
     *               "notes": "Refund processed for defective product",
     *               "status": {
     *                 "id": 3,
     *                 "name": "Refunded"
     *               },
     *               "created_at": "2025-01-15T11:20:00.000000Z",
     *               "updated_at": "2025-01-15T11:25:00.000000Z"
     *             }
     *           ]
     *         }
     *       }
     *     ],
     *     "payments": [
     *       {
     *         "id": 58,
     *         "amount": 2999,
     *         "amount_formatted": "£29.99",
     *         "status": "Partially Refunded",
     *         "method": "stripe",
     *         "transaction_reference": "pi_1Gxxxxxxxxxx",
     *         "processed_at": "2025-01-10T16:32:00.000000Z"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 401 scenario="User not authenticated" {
     *   "message": "Unauthenticated."
     * }
     *
     * @response 403 scenario="Access denied to other user's order" {
     *   "message": "You can only access your own orders."
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
}

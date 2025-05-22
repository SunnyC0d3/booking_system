<?php

namespace App\Http\Controllers\V1\Public;

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
     * Retrieve a specific order.
     *
     * @group Orders
     * @authenticated
     *
     * @response 200 {
     *     "message": "Order retrieved successfully.",
     *     "data": {}
     * }
     *
     * @response 403 {
     *     "message": "You do not have the required permissions."
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

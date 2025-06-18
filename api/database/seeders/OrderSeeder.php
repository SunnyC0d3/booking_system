<?php

namespace Database\Seeders;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderRefund;
use App\Models\OrderStatus;
use Illuminate\Database\Seeder;
use App\Models\OrderReturnStatus;
use App\Models\OrderRefundStatus;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::pluck('id')->toArray();
        $products = Product::with('variants')->get();
        $statuses = OrderStatus::pluck('id')->toArray();
        $returnStatuses = OrderReturnStatus::pluck('id', 'name')->toArray();
        $refundStatuses = OrderRefundStatus::pluck('id', 'name')->toArray();

        $orders = [];
        $orderItems = [];
        $returns = [];
        $refunds = [];

        for ($i = 0; $i < 100; $i++) {
            $orderId = $i + 1;
            $createdAt = Carbon::now()->subDays(rand(1, 90));
            $totalAmountInPennies = 0;

            $orders[] = [
                'id'           => $orderId,
                'user_id'      => $users[array_rand($users)],
                'status_id'    => $statuses[array_rand($statuses)],
                'total_amount' => 0,
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
            ];

            $orderProducts = $products->random(rand(1, 5));
            foreach ($orderProducts as $product) {
                $quantity = rand(1, 5);
                $variant = $product->variants->count() > 0 ? $product->variants->random() : null;
                $priceInPennies = $product->price + ($variant ? ($variant->additional_price ?? 0) : 0);

                $orderItems[] = [
                    'order_id'           => $orderId,
                    'product_id'         => $product->id,
                    'product_variant_id' => $variant ? $variant->id : null,
                    'quantity'           => $quantity,
                    'price'              => $priceInPennies,
                    'created_at'         => $createdAt,
                    'updated_at'         => $createdAt,
                ];

                $totalAmountInPennies += $priceInPennies * $quantity;
            }

            $orders[$i]['total_amount'] = $totalAmountInPennies;
        }

        Order::insert($orders);
        OrderItem::insert($orderItems);

        $allOrderItems = OrderItem::pluck('id')->toArray();
        $returnItemIds = array_rand(array_flip($allOrderItems), (int) (count($allOrderItems) * 0.2));

        foreach ($returnItemIds as $itemId) {
            $returnId = count($returns) + 1;
            $createdAt = Carbon::now()->subDays(rand(1, 30));

            $returns[] = [
                'id'            => $returnId,
                'order_item_id' => $itemId,
                'reason'        => fake()->sentence(),
                'order_return_status_id' => $returnStatuses[array_rand($returnStatuses)],
                'created_at'    => $createdAt,
                'updated_at'    => $createdAt,
            ];

            if (rand(1, 100) <= 60) {
                $refunds[] = [
                    'order_return_id' => $returnId,
                    'amount'          => rand(100, 1000) * 100,
                    'order_refund_status_id' => $refundStatuses[array_rand($refundStatuses)],
                    'processed_at'    => $createdAt->addDays(rand(1, 5)),
                    'created_at'      => $createdAt,
                    'updated_at'      => $createdAt,
                ];
            }
        }

        OrderReturn::insert($returns);
        OrderRefund::insert($refunds);
    }
}

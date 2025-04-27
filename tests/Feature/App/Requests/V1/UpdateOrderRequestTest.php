<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Requests\V1\UpdateOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdateOrderRequestTest extends TestCase
{
    public function test_validation_fails_when_required_fields_are_missing()
    {
        $data = [];
        $rules = (new UpdateOrderRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when required fields are missing.');
        $this->assertArrayHasKey('total_amount', $validator->errors()->toArray());
        $this->assertArrayHasKey('order_items', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_order_items_are_invalid()
    {
        $data = [
            'user_id' => 1,
            'total_amount' => 200,
            'order_items' => [
                [
                    'product_id' => null,
                    'quantity' => 0,
                    'price' => -50,
                ],
            ],
        ];
        $rules = (new UpdateOrderRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when order items are invalid.');
        $this->assertArrayHasKey('order_items.0.product_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('order_items.0.quantity', $validator->errors()->toArray());
        $this->assertArrayHasKey('order_items.0.price', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $user = User::factory()->create();
        $status = OrderStatus::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);

        $data = [
            'user_id' => $user->id,
            'status_id' => $status->id,
            'total_amount' => 100.50,
            'order_items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'price' => 50.25,
                ],
            ],
        ];

        $rules = (new UpdateOrderRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}

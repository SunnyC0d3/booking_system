<?php

namespace Tests\Feature\App\Requests\V1;

use App\Models\OrderStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Requests\V1\StoreOrderRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreOrderRequestTest extends TestCase
{
    public function test_validation_fails_when_required_fields_are_missing()
    {
        $data = [];
        $rules = (new StoreOrderRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when required fields are missing.');
        $this->assertArrayHasKey('user_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('order_items', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_order_items_are_invalid()
    {
        $data = [
            'user_id' => 1,
            'order_items' => [
                [
                    'product_id' => null,
                    'quantity' => 0,
                    'price' => -10,
                ],
            ],
        ];
        $rules = (new StoreOrderRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when order items are invalid.');
        $this->assertArrayHasKey('order_items.0.product_id', $validator->errors()->toArray());
        $this->assertArrayHasKey('order_items.0.quantity', $validator->errors()->toArray());
        $this->assertArrayHasKey('order_items.0.price', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $productVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
        ]);
        $status = OrderStatus::factory()->create();

        $data = [
            'user_id' => $user->id,
            'status_id' => $status->id,
            'order_items' => [
                [
                    'product_id' => $product->id,
                    'product_variant_id' => $productVariant->id,
                    'quantity' => 2,
                    'price' => 99.99,
                ],
            ],
        ];

        $rules = (new StoreOrderRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with valid data.');
    }
}

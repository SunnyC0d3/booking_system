<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\StorePaymentRequest;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StorePaymentRequestTest extends TestCase
{
    public function test_validation_fails_when_order_id_is_missing()
    {
        $data = [];
        $rules = (new StorePaymentRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when order_id is missing.');
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_order_id_does_not_exist()
    {
        $data = [
            'order_id' => 9999,
        ];
        $rules = (new StorePaymentRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when order_id does not exist.');
        $this->assertArrayHasKey('order_id', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_existing_order_id()
    {
        $order = Order::factory()->create();

        $data = [
            'order_id' => $order->id,
        ];
        $rules = (new StorePaymentRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails(), 'Validation should pass with a valid existing order_id.');
    }
}

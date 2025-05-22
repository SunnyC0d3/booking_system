<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\StorePaymentMethodRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\PaymentMethod;

class StorePaymentMethodRequestTest extends TestCase
{

    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new StorePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new StorePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_exceeds_max_length()
    {
        $data = ['name' => str_repeat('a', 256)];
        $rules = (new StorePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        PaymentMethod::factory()->create(['name' => 'PayPal']);
        $data = ['name' => 'PayPal'];
        $rules = (new StorePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_in_allowed_list()
    {
        $data = ['name' => 'Bitcoin'];
        $rules = (new StorePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $data = ['name' => 'Apple Pay'];
        $rules = (new StorePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}

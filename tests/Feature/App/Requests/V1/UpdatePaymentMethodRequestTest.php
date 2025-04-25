<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\UpdatePaymentMethodRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;
use App\Models\PaymentMethod;

class UpdatePaymentMethodRequestTest extends TestCase
{
    public function test_validation_fails_when_name_is_missing()
    {
        $data = [];
        $rules = (new UpdatePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_a_string()
    {
        $data = ['name' => 12345];
        $rules = (new UpdatePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_exceeds_max_length()
    {
        $data = ['name' => str_repeat('a', 256)];
        $rules = (new UpdatePaymentMethodRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_unique()
    {
        $existing = PaymentMethod::factory()->create(['name' => 'PayPal']);
        $target = PaymentMethod::factory()->create(['name' => 'Credit Card']);

        $request = new UpdatePaymentMethodRequest();
        $this->app->instance(UpdatePaymentMethodRequest::class, $request);

        $this->app['request']->setRouteResolver(function () use ($target) {
            return (object)['parameters' => ['payment_method' => $target->id]];
        });

        $data = ['name' => 'PayPal'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_name_is_not_in_allowed_list()
    {
        $method = PaymentMethod::factory()->create(['name' => 'Bank Transfer']);

        $request = new UpdatePaymentMethodRequest();
        $this->app->instance(UpdatePaymentMethodRequest::class, $request);

        $this->app['request']->setRouteResolver(function () use ($method) {
            return (object)['parameters' => ['payment_method' => $method->id]];
        });

        $data = ['name' => 'Bitcoin'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    public function test_validation_passes_with_valid_data()
    {
        $method = PaymentMethod::factory()->create(['name' => 'Google Pay']);

        $request = new UpdatePaymentMethodRequest();
        $this->app->instance(UpdatePaymentMethodRequest::class, $request);

        $this->app['request']->setRouteResolver(function () use ($method) {
            return (object)['parameters' => ['payment_method' => $method->id]];
        });

        $data = ['name' => 'Apple Pay'];
        $rules = $request->rules();
        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}

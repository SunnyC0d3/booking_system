<?php

namespace Tests\Feature\App\Requests\V1;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use App\Requests\V1\DeleteProductRequest;

class DeleteProductRequestTest extends TestCase
{
    public function test_authorize_method_returns_true()
    {
        $request = new DeleteProductRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_rules_method_returns_correct_validation_rules()
    {
        $request = new DeleteProductRequest();

        $expectedRules = [
            'id' => 'required|integer|exists:products,id',
        ];

        $this->assertEquals($expectedRules, $request->rules());
    }

    public function test_validation_fails_when_id_is_missing()
    {
        $request = new DeleteProductRequest();

        $data = [];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
        $this->assertContains('The id field is required.', $validator->errors()->get('id'));
    }

    public function test_validation_fails_when_id_is_not_an_integer()
    {
        $request = new DeleteProductRequest();

        $data = ['id' => 'string'];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
    }

    public function test_validation_fails_when_id_does_not_exist_in_database()
    {
        $request = new DeleteProductRequest();

        $data = ['id' => 9999];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('id', $validator->errors()->toArray());
        $this->assertContains('The selected id is invalid.', $validator->errors()->get('id'));
    }

    public function test_validation_fails_as_no_product_exists_provided_data()
    {
        $request = new DeleteProductRequest();

        $data = ['id' => 1];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
    }
}
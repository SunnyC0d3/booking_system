<?php

namespace Tests\Feature\App\Traits\V1;

use Tests\TestCase;
use Illuminate\Testing\TestResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiResponseTest extends TestCase
{
    use RefreshDatabase;

    protected function getTestClass()
    {
        return new class {
            use \App\Traits\V1\ApiResponses;

            public function testOk($message, $data = [])
            {
                return $this->ok($message, $data);
            }

            public function testSuccess($message, $data = [], $statusCode = 200)
            {
                return $this->success($message, $data, $statusCode);
            }

            public function testError($errors = [], $statusCode = null)
            {
                return $this->error($errors, $statusCode);
            }
        };
    }

    public function test_returnsASuccessfulOkResponse()
    {
        $trait = $this->getTestClass();

        $response = TestResponse::fromBaseResponse($trait->testOk('Operation successful', ['key' => 'value']));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Operation successful',
                'status' => 200,
                'data' => ['key' => 'value'],
            ]);
    }

    public function test_returnsAnErrorResponseWithAStringMessage()
    {
        $trait = $this->getTestClass();

        $response = TestResponse::fromBaseResponse($trait->testError('Something went wrong', 400));

        $response->assertJson([
                'message' => 'Something went wrong',
                'status' => 400,
            ]);
    }

    public function test_returnsAnErrorResponseWithAnArrayOfErrors()
    {
        $trait = $this->getTestClass();

        $response = TestResponse::fromBaseResponse($trait->testError(['field' => 'Field is required']));

        $response->assertStatus(200)
            ->assertJson([
                'errors' => ['field' => 'Field is required'],
            ]);
    }
}

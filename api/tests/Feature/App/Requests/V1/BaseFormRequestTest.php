<?php

namespace Tests\Feature\App\Requests\V1;

use Tests\TestCase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use App\Requests\V1\BaseFormRequest;

class BaseFormRequestTest extends TestCase
{
    public function test_failed_validation_returns_errors()
    {
        $request = new BaseFormRequest();

        $this->expectException(HttpResponseException::class);

        $validator = Validator::make([], [
            'name' => 'required'
        ]);

        $reflection = new \ReflectionClass($request);
        $method = $reflection->getMethod('failedValidation');
        $method->setAccessible(true);

        try {
            $method->invoke($request, $validator);
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();

            $content = json_decode($response->getContent(), true);
            $this->assertArrayHasKey('errors', $content);
            
            throw $e;
        }
    }
}

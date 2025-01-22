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

    public function test_passed_validation_throws_for_invalid_content_type()
    {
        $request = new BaseFormRequest();

        $mockRequest = Request::create('/dummy', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'text/plain'
        ]);

        $request->setContainer(app())
        ->setRedirector(app('redirect'))
        ->initialize(
            $mockRequest->query->all(),
            $mockRequest->request->all(),
            $mockRequest->attributes->all(),
            $mockRequest->cookies->all(),
            $mockRequest->files->all(),
            $mockRequest->server->all(),
            $mockRequest->getContent()
        );

        $this->expectException(HttpResponseException::class);

        $request->passedValidation();
    }

    public function test_passed_validation_allows_valid_content_type()
    {
        $request = new BaseFormRequest();

        $mockRequest = Request::create('/dummy', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json'
        ]);

        $request->setContainer(app())
        ->setRedirector(app('redirect'))
        ->initialize(
            $mockRequest->query->all(),
            $mockRequest->request->all(),
            $mockRequest->attributes->all(),
            $mockRequest->cookies->all(),
            $mockRequest->files->all(),
            $mockRequest->server->all(),
            $mockRequest->getContent()
        );

        $this->expectNotToPerformAssertions();

        $request->passedValidation();
    }
}

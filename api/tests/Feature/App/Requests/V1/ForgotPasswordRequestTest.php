<?php

namespace Tests\Feature\App\Requests\V1;

use App\Requests\V1\ForgotPasswordRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ForgotPasswordRequestTest extends TestCase
{
    public function test_validation_fails_with_valid_email_but_not_existing_in_user_table()
    {
        $data = ['email' => 'user@example.com'];

        $rules = (new ForgotPasswordRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should pass for valid email.');
    }

    public function test_validation_fails_when_email_is_missing()
    {
        $data = [];

        $rules = (new ForgotPasswordRequest())->rules();
        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails(), 'Validation should fail when email is missing.');
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
    }
} 

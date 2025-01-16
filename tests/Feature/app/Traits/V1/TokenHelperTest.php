<?php

namespace Tests\Unit\App\Traits\V1;

use Tests\TestCase;
use Illuminate\Http\Request;
use App\Traits\V1\TokenHelper;

class TokenHelperTest extends TestCase
{
    use TokenHelper;

    public function test_getTokenFromRequestWithAccessToken()
    {
        $request = Request::create('/test', 'POST', [
            'access_token' => '1|valid-access-token'
        ]);

        $hashedToken = $this->getTokenFromRequest($request, 'access');

        $this->assertEquals(hash('sha256', 'valid-access-token'), $hashedToken);
    }

    public function test_getTokenFromRequestWithRefreshToken()
    {
        $request = Request::create('/test', 'POST', [
            'refresh_token' => 'valid-refresh-token'
        ]);

        $hashedToken = $this->getTokenFromRequest($request, 'refresh');

        $this->assertEquals(hash('sha256', 'valid-refresh-token'), $hashedToken);
    }

    public function test_getTokenFromStringWithAccessToken()
    {
        $token = '1|valid-access-token';

        $hashedToken = $this->getTokenFromString($token, 'access');

        $this->assertEquals(hash('sha256', 'valid-access-token'), $hashedToken);
    }

    public function test_getTokenFromStringWithRefreshToken()
    {
        $token = 'valid-refresh-token';

        $hashedToken = $this->getTokenFromString($token, 'refresh');

        $this->assertEquals(hash('sha256', 'valid-refresh-token'), $hashedToken);
    }

    public function test_getTokenFromRequestWithInvalidAccessToken()
    {
        $request = Request::create('/test', 'POST', [
            'access_token' => 'invalid-access-token'
        ]);

        $hashedToken = $this->getTokenFromRequest($request, 'access');

        $this->assertEquals(hash('sha256', 'invalid-access-token'), $hashedToken);
    }

    public function test_getTokenFromStringWithInvalidAccessToken()
    {
        $token = 'invalid-access-token';

        $hashedToken = $this->getTokenFromString($token, 'access');

        $this->assertEquals(hash('sha256', 'invalid-access-token'), $hashedToken);
    }
}

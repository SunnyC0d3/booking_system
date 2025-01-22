<?php

namespace Tests\Feature\App\Http\Middleware\V1;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class VerifyHmacTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('hmac')->post('/test-hmac', function () {
            return response()->json(['message' => 'Access granted.']);
        });
    }

    private function generateHmac($timestamp, $body, $secretKey)
    {
        return hash_hmac('sha256', $timestamp . $body, $secretKey);
    }

    public function test_denies_access_when_hmac_header_is_missing()
    {
        $response = $this->postJson('/test-hmac', ['data' => 'test'], [
            'X-Timestamp' => time(),
        ]);

        $response->assertJson([
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
    }

    public function test_denies_access_when_timestamp_header_is_missing()
    {
        $response = $this->postJson('/test-hmac', ['data' => 'test'], [
            'X-Hmac' => 'fake-hmac',
        ]);

        $response->assertJson([
                'message' => 'Unauthorized',
                'status' => 401,
            ]);
    }

    public function test_denies_access_when_request_is_expired()
    {
        $timestamp = time() - 600;
        $body = json_encode(['data' => 'test']);
        $secretKey = env('HMAC_SECRET_KEY');
        $hmac = $this->generateHmac($timestamp, $body, $secretKey);

        $response = $this->postJson('/test-hmac', json_decode($body, true), [
            'X-Timestamp' => $timestamp,
            'X-Hmac' => $hmac,
        ]);

        $response->assertJson([
                'message' => 'Request expired',
                'status' => 401,
            ]);
    }

    public function test_denies_access_when_hmac_is_invalid()
    {
        $timestamp = time();
        $body = json_encode(['data' => 'test']);
        $fakeSecretKey = 'wrong-key';
        $hmac = $this->generateHmac($timestamp, $body, $fakeSecretKey);

        $response = $this->postJson('/test-hmac', json_decode($body, true), [
            'X-Timestamp' => $timestamp,
            'X-Hmac' => $hmac,
        ]);

        $response->assertJson([
                'message' => 'Invalid HMAC',
                'status' => 401,
            ]);
    }

    public function test_allows_access_with_valid_hmac_and_headers()
    {
        $timestamp = time();
        $body = '{"data":"test"}';
        $secretKey = env('HMAC_SECRET_KEY');
        $hmac = $this->generateHmac($timestamp, $body, $secretKey);

        $response = $this->postJson('/test-hmac', ['data' => 'test'], [
            'X-Timestamp' => $timestamp,
            'X-Hmac' => $hmac,
        ]);

        $response->assertJson([
                'message' => 'Access granted.',
            ]);
    }
}

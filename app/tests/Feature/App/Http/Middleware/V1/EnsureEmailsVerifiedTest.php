<?php

namespace Tests\Feature\App\Http\Middleware\V1;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class EnsureEmailsVerifiedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('emailVerified')->get('/test-email-verification', function () {
            return response()->json(['message' => 'Access granted.']);
        });
    }

    public function test_denies_access_to_users_with_unverified_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/test-email-verification');

        $response->assertJson([
            'message' => 'Your email address is not verified.',
            'status' => 403,
        ]);
    }

    public function test_allows_access_to_users_with_verified_email()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/test-email-verification');

        $response->assertJson([
            'message' => 'Access granted.',
        ]);
    }

    public function test_denies_access_to_unauthenticated_users()
    {
        $response = $this->getJson('/test-email-verification');

        $response->assertJson([
            'message' => 'Your email address is not verified.',
            'status' => 403,
        ]);
    }
}

<?php

namespace Tests\Feature\App\Http\Middleware\V1;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class RoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('role:admin,manager')->get('/test-role', function () {
            return response()->json(['message' => 'Access granted.']);
        });
    }

    public function test_denies_access_to_users_with_an_unauthorized_role()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'api')->getJson('/test-role');

        $response->assertJson([
            'message' => 'Unauthorized action',
            'status' => 403,
        ]);
    }

    public function test_allows_access_to_users_with_an_authorized_role()
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user, 'api')->getJson('/test-role');

        $response->assertJson([
            'message' => 'Access granted.',
        ]);
    }

    public function test_denies_access_to_unauthenticated_users()
    {
        $response = $this->getJson('/test-role');

        $response->assertJson([
            'message' => 'Unauthorized action',
            'status' => 403,
        ]);
    }
}

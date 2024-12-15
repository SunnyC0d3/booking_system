<?php

namespace Tests\Unit\App\Console\Commands\V1;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class DeleteExpiredTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_runCommandSuccessfully()
    {
        $this->artisan('tokens:delete-expired')->assertSuccessful();
    }

    public function test_deletesExpiredTokensAndOutputsTheCorrectMessage()
    {
        $expiredTokens = [
            ['id' => 1, 'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => 1, 'name' => 'ExpiredToken1', 'token' => 'expired-token-1', 'expires_at' => now()->subDay()],
            ['id' => 2, 'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => 2, 'name' => 'ExpiredToken2', 'token' => 'expired-token-2', 'expires_at' => now()->subDay()],
            ['id' => 3, 'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => 3, 'name' => 'ExpiredToken3', 'token' => 'expired-token-3', 'expires_at' => now()->subDay()],
        ];
        DB::table('personal_access_tokens')->insert($expiredTokens);

        $validTokens = [
            ['id' => 4, 'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => 4, 'name' => 'ValidToken1', 'token' => 'valid-token-1', 'expires_at' => now()->addDay()],
            ['id' => 5, 'tokenable_type' => 'App\\Models\\User', 'tokenable_id' => 5, 'name' => 'ValidToken2', 'token' => 'valid-token-2', 'expires_at' => now()->addDay()],
        ];
        DB::table('personal_access_tokens')->insert($validTokens);

        $this->artisan('tokens:delete-expired')
            ->expectsOutput('3 expired tokens have been deleted.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('personal_access_tokens', 2);

        foreach ($expiredTokens as $token) {
            $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token['id']]);
        }

        foreach ($validTokens as $token) {
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $token['id']]);
        }
    }
}

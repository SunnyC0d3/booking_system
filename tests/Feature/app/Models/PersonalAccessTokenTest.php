<?php

namespace Tests\Unit\App\Models;

use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class PersonalAccessTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_returnsTokensWithExpiredAccessOrRefreshDates()
    {
        PersonalAccessToken::create([
            'name' => 'Valid Token',
            'token' => 'valid-token',
            'abilities' => ['*'],
            'expires_at' => Carbon::now()->addDay(),
            'refresh_token_expires_at' => Carbon::now()->addDay(),
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => 1,
        ]);

        $expiredAccessToken = PersonalAccessToken::create([
            'name' => 'Expired Access Token',
            'token' => 'expired-access-token',
            'abilities' => ['*'],
            'expires_at' => Carbon::now()->subDay(),
            'refresh_token_expires_at' => Carbon::now()->addDay(),
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => 1,
        ]);

        $expiredRefreshToken = PersonalAccessToken::create([
            'name' => 'Expired Refresh Token',
            'token' => 'expired-refresh-token',
            'abilities' => ['*'],
            'expires_at' => Carbon::now()->addDay(),
            'refresh_token_expires_at' => Carbon::now()->subDay(),
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => 1,
        ]);

        $expiredTokens = PersonalAccessToken::expired()->get();

        $this->assertCount(2, $expiredTokens);
        $this->assertTrue($expiredTokens->contains($expiredAccessToken));
        $this->assertTrue($expiredTokens->contains($expiredRefreshToken));
    }

    public function test_doesNotReturnNonExpiredTokens()
    {
        $validToken = PersonalAccessToken::create([
            'name' => 'Valid Token',
            'token' => 'valid-token',
            'abilities' => ['*'],
            'expires_at' => Carbon::now()->addDay(),
            'refresh_token_expires_at' => Carbon::now()->addDay(),
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => 1,
        ]);

        $expiredTokens = PersonalAccessToken::expired()->get();

        $this->assertCount(0, $expiredTokens);
        $this->assertFalse($expiredTokens->contains($validToken));
    }
}

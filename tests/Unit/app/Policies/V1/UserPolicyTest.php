<?php

namespace Tests\Unit\App\Policies\V1;

use Tests\TestCase;
use App\Models\User;
use App\Policies\V1\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private $userPolicy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userPolicy = new UserPolicy();
    }

    public function test_create()
    {
        $user = User::factory()->create(['role' => 'user']);
        $accessToken = $user->createToken('valid-token', ['user:create'])->plainTextToken;

        $this->assertTrue($this->userPolicy->create($user, $accessToken));
    }

    public function test_update()
    {
        $user = User::factory()->create(['role' => 'user']);
        $accessToken = $user->createToken('valid-token', ['user:update'])->plainTextToken;

        $this->assertTrue($this->userPolicy->update($user, $accessToken));
    }

    public function test_delete()
    {
        $user = User::factory()->create(['role' => 'user']);
        $accessToken = $user->createToken('valid-token', ['user:delete'])->plainTextToken;

        $this->assertTrue($this->userPolicy->delete($user, $accessToken));
    }

    public function test_replace()
    {
        $user = User::factory()->create(['role' => 'user']);
        $accessToken = $user->createToken('valid-token', ['user:replace'])->plainTextToken;

        $this->assertTrue($this->userPolicy->replace($user, $accessToken));
    }

    public function test_only()
    {
        $user = User::factory()->create(['role' => 'user']);
        $accessToken = $user->createToken('valid-token', ['user:only'])->plainTextToken;

        $this->assertTrue($this->userPolicy->only($user, $accessToken));
    }
}
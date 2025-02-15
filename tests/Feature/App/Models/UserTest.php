<?php

namespace Tests\Feature\App\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            'name' => 'User'
        ]);
    }

    public function test_allowsMassAssignmentOfFillableAttributes()
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', [
            'name' => $user->name
        ]);

        $this->assertNotEquals('password123', $user->password);
    }

    public function test_hidesPasswordAndRememberTokenWhenSerialized()
    {
        $user = User::factory()->create([
            'password' => 'password123',
        ]);

        $serializedUser = $user->toArray();

        $this->assertArrayNotHasKey('password', $serializedUser);
        $this->assertArrayNotHasKey('remember_token', $serializedUser);
    }

    public function test_castsEmailVerifiedAtAndPasswordCorrectly()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'password' => 'password123',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $user->email_verified_at);
        $this->assertNotEquals('password123', $user->password);
    }
}

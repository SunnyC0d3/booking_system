<?php

namespace Tests\Feature\App\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowsMassAssignmentOfFillableAttributes()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'role' => 'admin',
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

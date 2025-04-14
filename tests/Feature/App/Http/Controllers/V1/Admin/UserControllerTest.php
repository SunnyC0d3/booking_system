<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_users_with_permission()
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('view_users');

        $this->actingAs($admin);

        $response = $this->getJson(route('admin.users.index'));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Users retrieved successfully.']);
    }

    public function test_store_creates_user_with_permission()
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('create_users');

        $this->actingAs($admin);

        $payload = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret123',
            'role_id' => Role::factory()->create()->id,
            'address' => [
                'address_line1' => '123 Main Street',
                'city' => 'Coventry',
                'country' => 'UK',
                'postal_code' => 'CV1 2WT',
                'address_line2' => null,
                'state' => 'West Midlands',
            ],
        ];

        $response = $this->postJson(route('admin.users.store'), $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Users created successfully!']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_show_returns_specific_user_with_permission()
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('view_users');

        $this->actingAs($admin);

        $user = User::factory()->create();

        $response = $this->getJson(route('admin.users.show', $user->id));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Users details retrieved.']);
    }

    public function test_destroy_deletes_user_with_permission()
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('delete_users');

        $this->actingAs($admin);

        $user = User::factory()->create();
        $user->userAddress()->create([
            'address_line1' => '123',
            'city' => 'London',
            'country' => 'UK',
            'postal_code' => 'W1A',
        ]);

        $response = $this->deleteJson(route('admin.users.destroy', $user->id));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Users deleted successfully.']);

        $this->assertModelMissing($user);
    }
}

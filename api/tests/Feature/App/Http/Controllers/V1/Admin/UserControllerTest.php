<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\User;
use App\Models\Role;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            'name' => 'Super Admin'
        ]);

        $this->user = User::factory()->superAdmin()->create([
            'email_verified_at' => now()
        ]);

        DB::table('permissions')->insert([
            ['name' => 'view_users'],
            ['name' => 'create_users'],
            ['name' => 'edit_users'],
            ['name' => 'delete_users'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_users')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_users')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_users')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_users')->first()->id,
            ],
        ]);
    }

    public function test_index_returns_users_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson(route('admin.users.index'));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Users retrieved successfully.']);
    }

    public function test_store_creates_user_with_permission()
    {
        $this->actingAs($this->user, 'api');

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
            ]
        ];

        $response = $this->postJson(route('admin.users.store'), $payload);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User created successfully!']);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_show_returns_specific_user_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson(route('admin.users.show', $this->user->id));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User details retrieved.']);
    }

    public function test_destroy_deletes_user_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $this->user->userAddress()->create([
            'address_line1' => '123',
            'city' => 'London',
            'country' => 'UK',
            'postal_code' => 'W1A',
        ]);

        $response = $this->deleteJson(route('admin.users.destroy', $this->user->id));

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User deleted successfully.']);

        $this->assertModelMissing($this->user);
    }
}

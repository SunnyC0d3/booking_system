<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\Permission;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class PermissionControllerTest extends TestCase
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
            ['name' => 'view_permissions'],
            ['name' => 'create_permissions'],
            ['name' => 'edit_permissions'],
            ['name' => 'delete_permissions'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_permissions')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_permissions')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_permissions')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_permissions')->first()->id,
            ],
        ]);
    }

    public function test_user_can_retrieve_all_permissions_with_view_permission()
    {
        Permission::factory()->count(3)->create();

        $this->actingAs($this->user, 'api');

        $response = $this->getJson(route('admin.permissions.index'));

        $response->assertOk();
    }

    public function test_user_can_create_permission_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson(route('admin.permissions.store'), [
            'name' => 'create-manager'
        ]);

        $response->assertOk();
    }
}

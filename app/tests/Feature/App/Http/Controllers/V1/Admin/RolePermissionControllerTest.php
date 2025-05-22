<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RolePermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $role;

    protected function setUp(): void
    {
        parent::setUp();

        Role::factory()->create(['name' => 'Super Admin']);

        $this->role = Role::factory()->create(['name' => 'Manager']);

        $this->user = User::factory()->superAdmin()->create([
            'email_verified_at' => now()
        ]);

        $permissions = [
            'view_roles',
            'edit_roles',
            'update_roles',
            'delete_roles',
            'view_permissions',
            'edit_permissions',
            'update_permissions',
            'delete_permissions',
        ];

        foreach ($permissions as $name) {
            Permission::create(['name' => $name]);
        }

        foreach ($permissions as $name) {
            DB::table('role_permission')->insert([
                'role_id' => $this->user->role->id,
                'permission_id' => Permission::where('name', $name)->first()->id,
            ]);
        }
    }

    public function test_user_can_view_role_permissions()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->getJson("/api/admin/roles/{$this->role->id}/permissions");

        $response->assertOk();
    }

    public function test_user_can_assign_specific_permissions_to_role()
    {
        $this->actingAs($this->user, 'api');

        $permissions = ['view_roles', 'edit_roles'];

        $response = $this->postJson("/api/admin/roles/{$this->role->id}/permissions", [
            'permissions' => $permissions
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('role_permission', [
            'role_id' => $this->role->id,
            'permission_id' => Permission::where('name', 'view_roles')->first()->id
        ]);
    }

    public function test_user_cannot_assign_invalid_permissions()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/admin/roles/{$this->role->id}/permissions", [
            'permissions' => ['invalid_permission']
        ]);

        $response->assertContent('{"errors":["The selected permissions.0 is invalid."]}');
    }

    public function test_user_can_assign_all_permissions_to_role()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson("/api/admin/roles/{$this->role->id}/permissions/assign-all");

        $response->assertOk();

        $this->assertEquals(
            Permission::count(),
            DB::table('role_permission')->where('role_id', $this->role->id)->count()
        );
    }

    public function test_user_can_revoke_permission_from_role()
    {
        $this->actingAs($this->user, 'api');

        $permission = Permission::where('name', 'view_roles')->first();
        $this->role->permissions()->attach($permission->id);

        $response = $this->deleteJson("/api/admin/roles/{$this->role->id}/permissions/{$permission->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('role_permission', [
            'role_id' => $this->role->id,
            'permission_id' => $permission->id,
        ]);
    }
}

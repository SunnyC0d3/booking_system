<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VendorControllerTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            'name' => 'Super Admin'
        ]);

        $this->admin = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        DB::table('permissions')->insert([
            ['name' => 'view_vendors'],
            ['name' => 'create_vendors'],
            ['name' => 'edit_vendors'],
            ['name' => 'delete_vendors'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->admin->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_vendors')->first()->id,
            ],
            [
                'role_id' => $this->admin->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_vendors')->first()->id,
            ],
            [
                'role_id' => $this->admin->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_vendors')->first()->id,
            ],
            [
                'role_id' => $this->admin->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_vendors')->first()->id,
            ],
        ]);
    }

    public function test_index_returns_vendors_with_permission()
    {
        $this->actingAs($this->admin, 'api');

        Vendor::factory()->count(2)->create();

        $response = $this->getJson(route('admin.vendors.index'));

        $response->assertStatus(200);
    }

    public function test_store_creates_vendor_with_permission()
    {
        $this->actingAs($this->admin, 'api');

        $payload = [
            'name' => 'My Cool Vendor',
            'description' => 'Best vendor in town',
            'user_id' => User::factory()->create()->id,
        ];

        $response = $this->postJson(route('admin.vendors.store'), $payload);

        $response->assertStatus(200);
    }

    public function test_show_returns_vendor_with_permission()
    {
        $this->actingAs($this->admin, 'api');

        $vendor = Vendor::factory()->create();

        $response = $this->getJson(route('admin.vendors.show', $vendor->id));

        $response->assertStatus(200);
    }

    public function test_destroy_deletes_vendor_with_permission()
    {
        $this->actingAs($this->admin, 'api');

        $vendor = Vendor::factory()->create();

        $response = $this->deleteJson(route('admin.vendors.destroy', $vendor->id));

        $response->assertStatus(200);
    }
}

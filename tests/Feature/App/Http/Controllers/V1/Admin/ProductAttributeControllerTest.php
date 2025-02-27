<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\ProductAttribute;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ProductAttributeControllerTest extends TestCase
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
            ['name' => 'view_product_attributes'],
            ['name' => 'create_product_attributes'],
            ['name' => 'edit_product_attributes'],
            ['name' => 'delete_product_attributes'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_product_attributes')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_product_attributes')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_product_attributes')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_product_attributes')->first()->id,
            ],
        ]);
    }

    public function test_user_can_retrieve_all_product_attributes_with_view_permission()
    {
        ProductAttribute::factory()->count(3)->create();

        $this->actingAs($this->user, 'api');

        $response = $this->getJson(route('admin.products.attributes.index'));

        $response->assertOk();
    }

    public function test_user_can_create_product_attribute_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson(route('admin.products.attributes.store'), [
            'name' => 'Color'
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['message', 'data']);
    }
}

<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\ProductCategory;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ProductCategoryControllerTest extends TestCase
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
            ['name' => 'view_product_categories'],
            ['name' => 'create_product_categories'],
            ['name' => 'edit_product_categories'],
            ['name' => 'delete_product_categories'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_product_categories')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_product_categories')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_product_categories')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_product_categories')->first()->id,
            ],
        ]);
    }

    public function test_user_can_retrieve_all_product_categories_with_view_permission()
    {
        ProductCategory::factory()->count(3)->create();

        $this->actingAs($this->user, 'api');

        $response = $this->getJson(route('admin.products.categories.index'));

        $response->assertOk();
    }

    public function test_user_can_create_product_category_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson(route('admin.products.categories.store'), [
            'name' => 'Color'
        ]);

        $response->assertOk()
                 ->assertJsonStructure(['message', 'data']);
    }
}
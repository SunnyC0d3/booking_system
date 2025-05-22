<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $vendor;
    protected $product;

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
            ['name' => 'view_products'],
            ['name' => 'create_products'],
            ['name' => 'edit_products'],
            ['name' => 'delete_products'],
            ['name' => 'force_delete_products'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_products')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_products')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_products')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_products')->first()->id,
            ],            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'force_delete_products')->first()->id,
            ],
        ]);

        $this->vendor = Vendor::factory()->create(['user_id' => $this->user->id]);
        $this->product = Product::factory()->create(['vendor_id' => $this->vendor->id]);
    }

    public function test_user_can_retrieve_paginated_products()
    {
        $response = $this->actingAs($this->user, 'api')->getJson(route('admin.products.index'));

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'data']);
    }

    public function test_user_can_view_a_single_product()
    {
        $response = $this->actingAs($this->user, 'api')->getJson(route('admin.products.show', $this->product));

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'data']);
    }

    public function test_user_can_create_a_product()
    {
        $data = Product::factory()->make()->toArray();
        $data['vendor_id'] = $this->vendor->id;

        $response = $this->actingAs($this->user, 'api')->postJson(route('admin.products.store'), $data);

        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'data']);
    }

    public function test_user_can_update_a_product()
    {
        $updatedData = ['name' => 'Updated Product Name'];

        $response = $this->actingAs($this->user, 'api')->postJson(route('admin.products.update', $this->product), $updatedData);

        $response->assertStatus(200);
    }

    public function test_user_can_soft_delete_a_product()
    {
        $response = $this->actingAs($this->user, 'api')->deleteJson(route('admin.products.softDestroy', $this->product));

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Product deleted successfully.']);
    }

    public function test_user_can_permanently_delete_a_product()
    {
        $productId = $this->product->id;

        $this->actingAs($this->user, 'api')->deleteJson(route('admin.products.softDestroy', $this->product));
        $response = $this->actingAs($this->user, 'api')->deleteJson(route('admin.products.destroy', $productId));

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Product deleted successfully.']);
    }
}

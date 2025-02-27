<?php

namespace Tests\Feature\App\Http\Controllers\V1\Public;

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
}
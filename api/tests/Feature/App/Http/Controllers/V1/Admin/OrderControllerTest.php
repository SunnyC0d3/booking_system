<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private $user;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            'name' => 'Super Admin',
        ]);

        $this->user = User::factory()->superAdmin()->create([
            'email_verified_at' => now(),
        ]);

        DB::table('permissions')->insert([
            ['name' => 'view_orders'],
            ['name' => 'create_orders'],
            ['name' => 'edit_orders'],
            ['name' => 'delete_orders'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_orders')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_orders')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_orders')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_orders')->first()->id,
            ],
        ]);
    }

    public function test_user_can_retrieve_all_orders_with_view_permission()
    {
        Order::factory()->count(3)->create();

        $this->actingAs($this->user, 'api');

        $response = $this->getJson(route('admin.orders.index'));

        $response->assertOk();
    }

    public function test_user_can_create_order_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $status = OrderStatus::factory()->create();
        $user = User::factory()->create();

        $response = $this->postJson(route('admin.orders.store'), [
            'user_id' => $user->id,
            'status_id' => $status->id,
            'order_items' => [
                [
                    'product_id' => 1,
                    'product_variant_id' => 1,
                    'quantity' => 2,
                    'price' => 50.00,
                ],
            ],
        ]);

        $response->assertOk();
    }
}

<?php

namespace Tests\Feature\App\Http\Controllers\V1\Admin;

use App\Models\PaymentMethod;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class PaymentMethodControllerTest extends TestCase
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
            ['name' => 'view_payment_methods'],
            ['name' => 'create_payment_methods'],
            ['name' => 'edit_payment_methods'],
            ['name' => 'delete_payment_methods'],
        ]);

        DB::table('role_permission')->insert([
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'view_payment_methods')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'create_payment_methods')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'edit_payment_methods')->first()->id,
            ],
            [
                'role_id' => $this->user->role->id,
                'permission_id' => DB::table('permissions')->where('name', 'delete_payment_methods')->first()->id,
            ],
        ]);
    }

    public function test_user_can_retrieve_all_payment_methods_with_view_permission()
    {
        PaymentMethod::factory()->count(3)->create();

        $this->actingAs($this->user, 'api');

        $response = $this->getJson(route('admin.paymentmethods.index'));

        $response->assertOk();
    }

    public function test_user_can_create_permission_with_permission()
    {
        $this->actingAs($this->user, 'api');

        $response = $this->postJson(route('admin.paymentmethods.store'), [
            'name' => 'create-manager'
        ]);

        $response->assertOk();
    }
}

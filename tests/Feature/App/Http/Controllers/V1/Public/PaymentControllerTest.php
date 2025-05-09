<?php

namespace Tests\Feature\App\Http\Controllers\V1\Public;

use App\Constants\PaymentMethods;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentMethod;
use \App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\Stripe;
use Stripe\Webhook;
use Tests\TestCase;
use Mockery;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Stripe::setApiKey(config('services.stripe_secret'));
    }

    public function test_creates_a_stripe_payment_intent()
    {
        $paymentMethod = PaymentMethod::factory()->create(['name' => PaymentMethods::STRIPE]);

        $user = User::factory()->create();

        $orderStatus = OrderStatus::factory()->create(['name' => 'PENDING']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status_id' => $orderStatus->id,
            'total_amount' => 1000,
        ]);

        $mockIntent = Mockery::mock('overload:\Stripe\PaymentIntent');
        $mockIntent->shouldReceive('create')
            ->andReturn((object) [
                'id' => 'pi_test_123',
                'client_secret' => 'test_client_secret_123'
            ]);

        $mockCustomer = Mockery::mock('overload:\Stripe\Customer');
        $mockCustomer->shouldReceive('create')
            ->andReturn((object) ['id' => 'cus_test_123']);

        $payload = [
            'order_id' => $order->id,
        ];

        $response = $this->postJson('/api/payments/stripe/create', $payload);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => ['client_secret'],
        ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'transaction_reference' => 'pi_test_123',
            'status' => 'PENDING',
        ]);
    }
}

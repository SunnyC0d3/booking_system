<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'amount'                => fake()->numberBetween(1000, 50000),
            'payment_method_id'     => PaymentMethod::factory(),
            'status'                => fake()->word(),
            'transaction_reference' => 'pi_' . $this->faker->regexify('[A-Za-z0-9]{24}'),
            'processed_at'          => Carbon::now(),
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),
            'order_id'              => Order::factory(),
            'user_id'               => User::factory(),
        ];
    }
}

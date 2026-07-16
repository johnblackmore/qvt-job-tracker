<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount' => fake()->randomFloat(2, 50, 3000),
            'method' => fake()->randomElement(['bank_transfer', 'card', 'cash']),
            'reference' => fn (array $attrs) => 'PAY-'.fake()->unique()->numerify('########'),
            'paid_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'notes' => null,
            'recorded_by_user_id' => User::factory(),
            'bank_transaction_id' => null,
        ];
    }
}

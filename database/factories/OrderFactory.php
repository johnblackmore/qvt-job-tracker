<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $total = fake()->randomFloat(2, 500, 5000);
        $depositRequired = round($total * 0.3, 2);
        $depositPaid = 0;

        return [
            'customer_id' => Customer::factory(),
            'quote_id' => null,
            'reference_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(fake()->lexify('????')),
            'status' => fake()->randomElement(['pending', 'deposit_paid', 'scheduled', 'in_progress', 'completed', 'cancelled']),
            'total_amount' => $total,
            'deposit_required' => $depositRequired,
            'deposit_paid' => $depositPaid,
            'balance_due' => $total - $depositPaid,
            'scheduled_date' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
            'completed_at' => null,
            'staff_user_id' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

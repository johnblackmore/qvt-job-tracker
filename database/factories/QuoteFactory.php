<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteFactory extends Factory
{
    public function definition(): array
    {
        $products = fake()->randomFloat(2, 500, 5000);
        $trade = $products * 0.6;
        $labour = fake()->randomFloat(2, 200, 1500);

        return [
            'customer_id' => Customer::factory(),
            'reference_number' => 'Q-'.now()->format('Ymd').'-'.strtoupper(fake()->lexify('????')),
            'status' => fake()->randomElement(['draft', 'sent', 'accepted', 'declined', 'expired']),
            'total_retail' => $products,
            'total_trade' => $trade,
            'labour_total' => $labour,
            'grand_total' => $products + $labour,
            'notes' => fake()->optional()->sentence(),
            'valid_until' => fake()->optional()->dateTimeBetween('+1 week', '+1 month'),
            'staff_user_id' => null,
        ];
    }
}

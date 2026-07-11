<?php

namespace Database\Factories;

use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuoteLineItemFactory extends Factory
{
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 5);
        $retail = fake()->randomFloat(2, 50, 1000);
        $trade = $retail * 0.6;

        return [
            'quote_id' => Quote::factory(),
            'line_type' => fake()->randomElement(['product', 'labour', 'ad_hoc']),
            'product_id' => null,
            'product_supplier_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => $qty,
            'unit_retail_price' => $retail,
            'unit_trade_price' => $trade,
            'line_total_retail' => $qty * $retail,
            'line_total_trade' => $qty * $trade,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

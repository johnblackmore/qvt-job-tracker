<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->bothify('???-####')),
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'category_id' => ProductCategory::factory(),
            'retail_price' => fake()->randomFloat(2, 50, 2000),
            'stock_qty' => fake()->numberBetween(0, 100),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\SampleQuote;
use Illuminate\Database\Eloquent\Factories\Factory;

class SampleQuoteFactory extends Factory
{
    protected $model = SampleQuote::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'line_items' => [],
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

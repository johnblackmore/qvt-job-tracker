<?php

namespace Database\Factories;

use App\Models\AiExtraction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiExtraction>
 */
class AiExtractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'assistant_name' => 'product-url-extractor',
            'source_url' => fake()->url(),
            'status' => 'completed',
        ];
    }
}

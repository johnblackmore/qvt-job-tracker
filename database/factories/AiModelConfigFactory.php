<?php

namespace Database\Factories;

use App\Models\AiModelConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiModelConfig>
 */
class AiModelConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label' => fake()->words(3, true),
            'provider' => 'opencode',
            'model' => 'deepseek-v4-flash-free',
            'description' => fake()->sentence(),
        ];
    }
}

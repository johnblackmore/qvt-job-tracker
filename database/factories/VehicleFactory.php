<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'make' => fake()->randomElement(['Ford', 'Volkswagen', 'Mercedes', 'Renault', 'Peugeot']),
            'model' => fake()->word(),
            'registration' => strtoupper(fake()->bothify('??## ???')),
            'year' => fake()->numberBetween(2000, 2026),
            'type' => fake()->randomElement(['campervan', 'motorhome', 'van']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
